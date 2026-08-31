using System;
using System.Collections.Generic;
using System.IO;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading;

internal static class WindowsProcessTreeControl
{
    private const uint SnapshotProcesses = 0x00000002;
    private const uint ProcessTerminate = 0x0001;
    private const uint ProcessQueryLimitedInformation = 0x1000;
    private const uint Synchronize = 0x00100000;
    private const uint WaitObject0 = 0x00000000;
    private const uint WaitAbandoned = 0x00000080;
    private const uint WaitTimeout = 0x00000102;
    private const uint WaitFailed = 0xFFFFFFFF;
    private const int ErrorInvalidParameter = 87;
    private static readonly IntPtr InvalidHandle = new IntPtr(-1);

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct ProcessEntry
    {
        public uint Size;
        public uint Usage;
        public uint ProcessId;
        public IntPtr DefaultHeapId;
        public uint ModuleId;
        public uint Threads;
        public uint ParentProcessId;
        public int BasePriority;
        public uint Flags;

        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 260)]
        public string ExecutableFile;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct FileTime
    {
        public uint Low;
        public uint High;
    }

    private sealed class ProcessRecord
    {
        public uint ProcessId;
        public uint ParentProcessId;
        public ulong CreationTime;
        public int Depth;
        public bool HasRegistrationIdentity;
        public long RegisteredAtUnixMilliseconds;
        public bool HasExitIdentity;
        public long ExitedAtUnixMilliseconds;
    }

    private enum ProcessWaitState
    {
        Signaled,
        Running,
        Failed
    }

    private enum IdentityRunningState
    {
        NotRunning,
        SameIdentityRunning,
        DifferentIdentityRunning,
        Failed
    }

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern IntPtr CreateToolhelp32Snapshot(uint flags, uint processId);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool Process32FirstW(IntPtr snapshot, ref ProcessEntry entry);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool Process32NextW(IntPtr snapshot, ref ProcessEntry entry);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern IntPtr OpenProcess(uint access, bool inheritHandle, uint processId);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool GetProcessTimes(
        IntPtr process,
        out FileTime creation,
        out FileTime exit,
        out FileTime kernel,
        out FileTime user
    );

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool TerminateProcess(IntPtr process, uint exitCode);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern uint WaitForSingleObject(IntPtr handle, uint milliseconds);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool CloseHandle(IntPtr handle);

    public static int Main(string[] arguments)
    {
        try
        {
            bool probeOnly = arguments.Length > 0 && arguments[0] == "--probe";
            if (arguments.Length > 0 && arguments[0] == "--watch-exit")
            {
                return WatchExit(arguments);
            }
            if (arguments.Length > 0 && arguments[0] == "--identity")
            {
                return ReadIdentity(arguments);
            }
            if (probeOnly)
            {
                string[] probeArguments = new string[arguments.Length - 1];
                Array.Copy(arguments, 1, probeArguments, 0, probeArguments.Length);
                List<ProcessRecord> probeRecords = ParseSeeds(probeArguments);
                List<string> runningIds = new List<string>();
                foreach (ProcessRecord probeRecord in probeRecords)
                {
                    if (ShouldKeepProcessInProbe(probeRecord))
                    {
                        runningIds.Add(probeRecord.ProcessId.ToString());
                    }
                }
                Console.Out.Write("[" + string.Join(",", runningIds.ToArray()) + "]");
                return 0;
            }

            List<ProcessRecord> seeds = ParseSeeds(arguments);
            List<string> failures = new List<string>();
            List<ProcessRecord> targets = ReachableProcesses(seeds, failures);
            targets.Sort(delegate(ProcessRecord left, ProcessRecord right)
            {
                return right.Depth.CompareTo(left.Depth);
            });

            foreach (ProcessRecord target in targets)
            {
                TerminateIdentity(target, failures);
            }

            if (failures.Count > 0)
            {
                Console.Error.Write(string.Join(",", failures.ToArray()));
                return 3;
            }

            Console.Out.Write("OK");
            return 0;
        }
        catch (Exception error)
        {
            Console.Error.Write(error.GetType().Name + ":" + error.Message);
            return 2;
        }
    }

    private static int WatchExit(string[] arguments)
    {
        if (arguments.Length != 4)
        {
            throw new ArgumentException(
                "--watch-exit requires a canonical PID, parent PID, and registry path."
            );
        }

        uint processId = (uint)ParseCanonicalPositiveDecimal(
            arguments[1],
            uint.MaxValue,
            "Every watcher PID must be a canonical uint32 decimal value."
        );
        uint parentProcessId = (uint)ParseCanonicalPositiveDecimal(
            arguments[2],
            uint.MaxValue,
            "Every watcher parent PID must be a canonical positive uint32 decimal value."
        );
        string registryPath = arguments[3];
        if (registryPath.Length == 0 || !Path.IsPathRooted(registryPath))
        {
            throw new ArgumentException("The watcher registry path must be an absolute path.");
        }

        IntPtr process = OpenProcess(ProcessQueryLimitedInformation | Synchronize, false, processId);
        if (process == IntPtr.Zero)
        {
            Console.Error.Write("watch-open:" + processId + ":" + Marshal.GetLastWin32Error());
            return 3;
        }

        try
        {
            ulong creationTime;
            int queryError;
            if (!TryReadCreationTimeFromHandle(process, out creationTime, out queryError))
            {
                Console.Error.Write("watch-query:" + processId + ":" + queryError);
                return 3;
            }
            string processMarker = Environment.NewLine + "// CRM_NODE_PROCESS " + processId + " " + parentProcessId
                + " " + creationTime + Environment.NewLine;
            int writeError;
            if (!AppendExitMarker(registryPath, processMarker, out writeError))
            {
                Console.Error.Write("watch-write:" + processId + ":" + writeError);
                return 3;
            }

            int waitError;
            ProcessWaitState waitState = ReadProcessWaitState(process, 0xFFFFFFFF, out waitError);
            if (waitState == ProcessWaitState.Failed)
            {
                Console.Error.Write("watch-wait:" + processId + ":" + waitError);
                return 3;
            }
            if (waitState != ProcessWaitState.Signaled)
            {
                Console.Error.Write("watch-wait:" + processId + ":unexpected");
                return 3;
            }

            ulong exitTime;
            if (!TryReadExitTimeFromHandle(process, out exitTime, out queryError))
            {
                Console.Error.Write("watch-query-exit:" + processId + ":" + queryError);
                return 3;
            }
            string marker = "// CRM_NODE_EXIT " + processId + " " + exitTime
                + Environment.NewLine;
            if (!AppendExitMarker(registryPath, marker, out writeError))
            {
                Console.Error.Write("watch-write:" + processId + ":" + writeError);
                return 3;
            }

            Console.Out.Write("OK");
            return 0;
        }
        finally
        {
            CloseHandle(process);
        }
    }

    private static int ReadIdentity(string[] arguments)
    {
        if (arguments.Length != 2)
        {
            throw new ArgumentException("--identity requires one canonical PID.");
        }
        uint processId = (uint)ParseCanonicalPositiveDecimal(
            arguments[1],
            uint.MaxValue,
            "The identity PID must be a canonical uint32 decimal value."
        );
        ulong creationTime;
        int queryError;
        if (!TryReadCreationTime(processId, out creationTime, out queryError))
        {
            Console.Error.Write("query:" + processId + ":" + queryError);
            return 3;
        }

        Console.Out.Write(creationTime.ToString());
        return 0;
    }

    private static bool AppendExitMarker(string registryPath, string marker, out int writeError)
    {
        writeError = 0;
        byte[] bytes = new UTF8Encoding(false).GetBytes(marker);
        DateTime retryDeadline = DateTime.UtcNow.AddMilliseconds(250);
        do
        {
            try
            {
                using (FileStream stream = new FileStream(
                    registryPath,
                    FileMode.Append,
                    FileAccess.Write,
                    FileShare.Read
                ))
                {
                    stream.Write(bytes, 0, bytes.Length);
                    stream.Flush();
                }
                return true;
            }
            catch (IOException error)
            {
                writeError = Marshal.GetHRForException(error);
                if (DateTime.UtcNow >= retryDeadline)
                {
                    return false;
                }
                Thread.Sleep(5);
            }
            catch (UnauthorizedAccessException error)
            {
                writeError = Marshal.GetHRForException(error);
                return false;
            }
        }
        while (true);
    }

    private static List<ProcessRecord> ParseSeeds(string[] arguments)
    {
        List<ProcessRecord> seeds = new List<ProcessRecord>();
        Dictionary<uint, ProcessRecord> seedsById = new Dictionary<uint, ProcessRecord>();
        foreach (string argument in arguments)
        {
            string[] parts = argument.Split(new char[] { ':' });
            if (parts.Length > 3)
            {
                throw new ArgumentException("Every seed must contain at most two identity separators.");
            }

            uint processId = (uint)ParseCanonicalPositiveDecimal(
                parts[0],
                uint.MaxValue,
                "Every seed PID must be a canonical uint32 decimal value."
            );
            ProcessRecord seed = new ProcessRecord { ProcessId = processId, Depth = 0 };
            if (parts.Length >= 2)
            {
                long registeredAt = (long)ParseCanonicalPositiveDecimal(
                    parts[1],
                    long.MaxValue,
                    "Every registration identity must be a canonical positive int64 decimal value."
                );
                seed.HasRegistrationIdentity = true;
                seed.RegisteredAtUnixMilliseconds = registeredAt;
            }
            if (parts.Length == 3)
            {
                long exitedAt = (long)ParseCanonicalPositiveDecimal(
                    parts[2],
                    long.MaxValue,
                    "Every exit identity must be a canonical positive int64 decimal value."
                );
                if (exitedAt < seed.RegisteredAtUnixMilliseconds)
                {
                    throw new ArgumentException(
                        "Every exit identity must not precede its registration identity."
                    );
                }
                seed.HasExitIdentity = true;
                seed.ExitedAtUnixMilliseconds = exitedAt;
            }

            ProcessRecord existingSeed;
            if (seedsById.TryGetValue(processId, out existingSeed))
            {
                if (existingSeed.HasRegistrationIdentity != seed.HasRegistrationIdentity
                    || (seed.HasRegistrationIdentity
                        && existingSeed.RegisteredAtUnixMilliseconds
                            != seed.RegisteredAtUnixMilliseconds)
                    || existingSeed.HasExitIdentity != seed.HasExitIdentity
                    || (seed.HasExitIdentity
                        && existingSeed.ExitedAtUnixMilliseconds != seed.ExitedAtUnixMilliseconds))
                {
                    throw new ArgumentException(
                        "Duplicate seed PIDs must use exactly the same registration identity."
                    );
                }

                continue;
            }

            seedsById.Add(processId, seed);
            seeds.Add(seed);
        }
        if (seeds.Count == 0)
        {
            throw new ArgumentException("At least one seed PID is required.");
        }

        return seeds;
    }

    private static ulong ParseCanonicalPositiveDecimal(
        string value,
        ulong maximum,
        string errorMessage
    )
    {
        if (value.Length == 0 || value[0] < '1' || value[0] > '9')
        {
            throw new ArgumentException(errorMessage);
        }

        ulong parsed = 0;
        foreach (char character in value)
        {
            if (character < '0' || character > '9')
            {
                throw new ArgumentException(errorMessage);
            }

            ulong digit = (ulong)(character - '0');
            if (parsed > (maximum - digit) / 10UL)
            {
                throw new ArgumentException(errorMessage);
            }
            parsed = parsed * 10UL + digit;
        }

        return parsed;
    }

    private static List<ProcessRecord> ReachableProcesses(
        List<ProcessRecord> seeds,
        List<string> failures
    )
    {
        Dictionary<uint, uint> parentById;
        Dictionary<uint, int> queryFailures;
        Dictionary<uint, ProcessRecord> byId = Snapshot(out parentById, out queryFailures);
        Dictionary<uint, ProcessRecord> seedsById = new Dictionary<uint, ProcessRecord>();
        HashSet<uint> staleSeedIds = new HashSet<uint>();
        foreach (ProcessRecord seed in seeds)
        {
            seedsById.Add(seed.ProcessId, seed);
            ProcessRecord currentSeed;
            if (seed.HasRegistrationIdentity
                && byId.TryGetValue(seed.ProcessId, out currentSeed)
                && (seed.HasExitIdentity
                    || !RegistrationIdentityMatches(
                        currentSeed.CreationTime,
                        seed.RegisteredAtUnixMilliseconds
                    )))
            {
                staleSeedIds.Add(seed.ProcessId);
            }
        }

        Dictionary<uint, List<uint>> children = new Dictionary<uint, List<uint>>();
        foreach (KeyValuePair<uint, uint> parentEntry in parentById)
        {
            List<uint> childIds;
            if (!children.TryGetValue(parentEntry.Value, out childIds))
            {
                childIds = new List<uint>();
                children.Add(parentEntry.Value, childIds);
            }
            childIds.Add(parentEntry.Key);
        }

        Queue<ProcessRecord> queue = new Queue<ProcessRecord>();
        foreach (ProcessRecord seed in seeds)
        {
            queue.Enqueue(seed);
        }
        Dictionary<uint, ProcessRecord> reachable = new Dictionary<uint, ProcessRecord>();
        HashSet<uint> visited = new HashSet<uint>();
        while (queue.Count > 0)
        {
            ProcessRecord queued = queue.Dequeue();
            if (!visited.Add(queued.ProcessId))
            {
                continue;
            }

            ProcessRecord registeredSeed;
            if (seedsById.TryGetValue(queued.ProcessId, out registeredSeed))
            {
                queued.HasRegistrationIdentity = registeredSeed.HasRegistrationIdentity;
                queued.RegisteredAtUnixMilliseconds = registeredSeed.RegisteredAtUnixMilliseconds;
                queued.HasExitIdentity = registeredSeed.HasExitIdentity;
                queued.ExitedAtUnixMilliseconds = registeredSeed.ExitedAtUnixMilliseconds;
            }

            int queuedQueryError;
            if (queryFailures.TryGetValue(queued.ProcessId, out queuedQueryError))
            {
                failures.Add("query:" + queued.ProcessId + ":" + queuedQueryError);
                continue;
            }

            ProcessRecord current;
            bool currentExists = byId.TryGetValue(queued.ProcessId, out current);
            if (currentExists)
            {
                current.Depth = queued.Depth;
                current.HasRegistrationIdentity = queued.HasRegistrationIdentity;
                current.RegisteredAtUnixMilliseconds = queued.RegisteredAtUnixMilliseconds;
                current.HasExitIdentity = queued.HasExitIdentity;
                current.ExitedAtUnixMilliseconds = queued.ExitedAtUnixMilliseconds;
                reachable.Add(current.ProcessId, current);
            }

            if (staleSeedIds.Contains(queued.ProcessId))
            {
                continue;
            }

            if (!currentExists && queued.HasRegistrationIdentity && !queued.HasExitIdentity)
            {
                failures.Add("ambiguous:" + queued.ProcessId);
                continue;
            }

            List<uint> childIds;
            if (children.TryGetValue(queued.ProcessId, out childIds))
            {
                foreach (uint childId in childIds)
                {
                    int childQueryError;
                    if (queryFailures.TryGetValue(childId, out childQueryError))
                    {
                        failures.Add("query:" + childId + ":" + childQueryError);
                        continue;
                    }
                    ProcessRecord child;
                    if (!byId.TryGetValue(childId, out child))
                    {
                        continue;
                    }
                    bool followsParentIdentity = currentExists
                        ? child.CreationTime >= current.CreationTime
                        : queued.HasRegistrationIdentity
                            && queued.HasExitIdentity
                            && IsWithinExitedRegisteredParentWindow(
                                child.CreationTime,
                                queued.RegisteredAtUnixMilliseconds,
                                queued.ExitedAtUnixMilliseconds
                            );
                    if (!followsParentIdentity)
                    {
                        if (!currentExists)
                        {
                            failures.Add("ambiguous:" + queued.ProcessId);
                        }
                        continue;
                    }

                    queue.Enqueue(new ProcessRecord { ProcessId = childId, Depth = queued.Depth + 1 });
                }
            }
        }

        return new List<ProcessRecord>(reachable.Values);
    }

    private static bool IsWithinExitedRegisteredParentWindow(
        ulong childCreationTime,
        long registeredAtUnixMilliseconds,
        long exitedAtUnixMilliseconds
    )
    {
        return childCreationTime >= (ulong)registeredAtUnixMilliseconds
            && childCreationTime <= (ulong)exitedAtUnixMilliseconds;
    }

    private static Dictionary<uint, ProcessRecord> Snapshot(
        out Dictionary<uint, uint> parentById,
        out Dictionary<uint, int> queryFailures
    )
    {
        parentById = new Dictionary<uint, uint>();
        queryFailures = new Dictionary<uint, int>();
        IntPtr snapshot = CreateToolhelp32Snapshot(SnapshotProcesses, 0);
        if (snapshot == InvalidHandle)
        {
            throw new InvalidOperationException("CreateToolhelp32Snapshot failed with " + Marshal.GetLastWin32Error());
        }

        try
        {
            Dictionary<uint, ProcessRecord> records = new Dictionary<uint, ProcessRecord>();
            ProcessEntry entry = new ProcessEntry();
            entry.Size = (uint)Marshal.SizeOf(typeof(ProcessEntry));
            if (!Process32FirstW(snapshot, ref entry))
            {
                throw new InvalidOperationException("Process32First failed with " + Marshal.GetLastWin32Error());
            }
            do
            {
                if (entry.ProcessId != 0)
                {
                    parentById[entry.ProcessId] = entry.ParentProcessId;
                    ulong creationTime;
                    int queryError;
                    if (TryReadCreationTime(entry.ProcessId, out creationTime, out queryError))
                    {
                        records[entry.ProcessId] = new ProcessRecord
                        {
                            ProcessId = entry.ProcessId,
                            ParentProcessId = entry.ParentProcessId,
                            CreationTime = creationTime,
                            Depth = 0
                        };
                    }
                    else if (queryError != ErrorInvalidParameter)
                    {
                        queryFailures[entry.ProcessId] = queryError;
                    }
                }
                entry.Size = (uint)Marshal.SizeOf(typeof(ProcessEntry));
            }
            while (Process32NextW(snapshot, ref entry));

            return records;
        }
        finally
        {
            CloseHandle(snapshot);
        }
    }

    private static bool TryReadCreationTime(
        uint processId,
        out ulong creationTime,
        out int queryError
    )
    {
        creationTime = 0;
        queryError = 0;
        IntPtr process = OpenProcess(ProcessQueryLimitedInformation, false, processId);
        if (process == IntPtr.Zero)
        {
            queryError = Marshal.GetLastWin32Error();
            return false;
        }
        try
        {
            FileTime creation;
            FileTime exit;
            FileTime kernel;
            FileTime user;
            if (!GetProcessTimes(process, out creation, out exit, out kernel, out user))
            {
                queryError = Marshal.GetLastWin32Error();
                return false;
            }
            creationTime = ((ulong)creation.High << 32) | creation.Low;
            return true;
        }
        finally
        {
            CloseHandle(process);
        }
    }

    private static void TerminateIdentity(ProcessRecord target, List<string> failures)
    {
        IntPtr process = OpenProcess(
            ProcessTerminate | ProcessQueryLimitedInformation | Synchronize,
            false,
            target.ProcessId
        );
        if (process == IntPtr.Zero)
        {
            int openError = Marshal.GetLastWin32Error();
            if (openError != ErrorInvalidParameter)
            {
                failures.Add("query:" + target.ProcessId + ":" + openError);
            }
            return;
        }
        try
        {
            int waitError;
            ProcessWaitState initialWaitState = ReadProcessWaitState(process, 0, out waitError);
            if (initialWaitState == ProcessWaitState.Signaled)
            {
                return;
            }
            if (initialWaitState == ProcessWaitState.Failed)
            {
                failures.Add("wait:" + target.ProcessId + ":" + waitError);
                return;
            }

            ulong currentCreation;
            int identityError;
            if (!TryReadCreationTimeFromHandle(process, out currentCreation, out identityError))
            {
                failures.Add("query:" + target.ProcessId + ":" + identityError);
                return;
            }
            if (currentCreation != target.CreationTime)
            {
                failures.Add("reused:" + target.ProcessId);
                return;
            }
            if (target.HasRegistrationIdentity)
            {
                if (target.HasExitIdentity)
                {
                    failures.Add("stale:" + target.ProcessId);
                    return;
                }
                if (!RegistrationIdentityMatches(
                    currentCreation,
                    target.RegisteredAtUnixMilliseconds
                ))
                {
                    failures.Add("stale:" + target.ProcessId);
                    return;
                }
            }
            if (!TerminateProcess(process, 137))
            {
                int terminationError = Marshal.GetLastWin32Error();
                ProcessWaitState raceWaitState = ReadProcessWaitState(process, 50, out waitError);
                if (raceWaitState == ProcessWaitState.Signaled)
                {
                    return;
                }
                if (raceWaitState == ProcessWaitState.Failed)
                {
                    failures.Add("wait:" + target.ProcessId + ":" + waitError);
                    return;
                }
                failures.Add("terminate:" + target.ProcessId + ":" + terminationError);
                return;
            }
            ProcessWaitState terminationWaitState = ReadProcessWaitState(process, 250, out waitError);
            if (terminationWaitState == ProcessWaitState.Running)
            {
                failures.Add("alive:" + target.ProcessId);
                return;
            }
            if (terminationWaitState == ProcessWaitState.Failed)
            {
                failures.Add("wait:" + target.ProcessId + ":" + waitError);
                return;
            }
        }
        finally
        {
            CloseHandle(process);
        }

        string verificationFailure;
        IdentityRunningState runningState = ReadIdentityRunningState(
            target.ProcessId,
            target.CreationTime,
            out verificationFailure
        );
        if (runningState == IdentityRunningState.SameIdentityRunning)
        {
            failures.Add("verify:" + target.ProcessId);
        }
        else if (runningState == IdentityRunningState.Failed)
        {
            failures.Add(verificationFailure);
        }
    }

    private static bool RegistrationIdentityMatches(ulong creationTime, long registeredAtUnixMilliseconds)
    {
        return creationTime == (ulong)registeredAtUnixMilliseconds;
    }

    private static IdentityRunningState ReadIdentityRunningState(
        uint processId,
        ulong expectedCreationTime,
        out string failure
    )
    {
        failure = "";
        IntPtr process = OpenProcess(ProcessQueryLimitedInformation | Synchronize, false, processId);
        if (process == IntPtr.Zero)
        {
            int openError = Marshal.GetLastWin32Error();
            if (openError == ErrorInvalidParameter)
            {
                return IdentityRunningState.NotRunning;
            }
            failure = "query:" + processId + ":" + openError;
            return IdentityRunningState.Failed;
        }
        try
        {
            int waitError;
            ProcessWaitState waitState = ReadProcessWaitState(process, 0, out waitError);
            if (waitState == ProcessWaitState.Signaled)
            {
                return IdentityRunningState.NotRunning;
            }
            if (waitState == ProcessWaitState.Failed)
            {
                failure = "wait:" + processId + ":" + waitError;
                return IdentityRunningState.Failed;
            }
            ulong currentCreationTime;
            int identityError;
            if (!TryReadCreationTimeFromHandle(process, out currentCreationTime, out identityError))
            {
                failure = "query:" + processId + ":" + identityError;
                return IdentityRunningState.Failed;
            }

            return currentCreationTime == expectedCreationTime
                ? IdentityRunningState.SameIdentityRunning
                : IdentityRunningState.DifferentIdentityRunning;
        }
        finally
        {
            CloseHandle(process);
        }
    }

    private static bool ShouldKeepProcessInProbe(ProcessRecord probe)
    {
        if (probe.HasExitIdentity)
        {
            return false;
        }

        uint processId = probe.ProcessId;
        IntPtr process = OpenProcess(ProcessQueryLimitedInformation | Synchronize, false, processId);
        if (process == IntPtr.Zero)
        {
            return Marshal.GetLastWin32Error() != ErrorInvalidParameter;
        }
        try
        {
            int waitError;
            ProcessWaitState waitState = ReadProcessWaitState(process, 0, out waitError);

            if (waitState == ProcessWaitState.Signaled)
            {
                return false;
            }
            if (waitState == ProcessWaitState.Failed || !probe.HasRegistrationIdentity)
            {
                return true;
            }

            ulong currentCreationTime;
            int identityError;
            if (!TryReadCreationTimeFromHandle(process, out currentCreationTime, out identityError))
            {
                return true;
            }

            return RegistrationIdentityMatches(
                currentCreationTime,
                probe.RegisteredAtUnixMilliseconds
            );
        }
        finally
        {
            CloseHandle(process);
        }
    }

    private static ProcessWaitState ReadProcessWaitState(
        IntPtr process,
        uint milliseconds,
        out int waitError
    )
    {
        waitError = 0;
        uint waitResult = WaitForSingleObject(process, milliseconds);
        if (waitResult == WaitObject0 || waitResult == WaitAbandoned)
        {
            return ProcessWaitState.Signaled;
        }
        if (waitResult == WaitTimeout)
        {
            return ProcessWaitState.Running;
        }

        waitError = waitResult == WaitFailed
            ? Marshal.GetLastWin32Error()
            : unchecked((int)waitResult);
        return ProcessWaitState.Failed;
    }

    private static bool TryReadCreationTimeFromHandle(
        IntPtr process,
        out ulong creationTime,
        out int queryError
    )
    {
        creationTime = 0;
        queryError = 0;
        FileTime creation;
        FileTime exit;
        FileTime kernel;
        FileTime user;
        if (!GetProcessTimes(process, out creation, out exit, out kernel, out user))
        {
            queryError = Marshal.GetLastWin32Error();
            return false;
        }
        creationTime = ((ulong)creation.High << 32) | creation.Low;
        return true;
    }

    private static bool TryReadExitTimeFromHandle(
        IntPtr process,
        out ulong exitTime,
        out int queryError
    )
    {
        exitTime = 0;
        queryError = 0;
        FileTime creation;
        FileTime exit;
        FileTime kernel;
        FileTime user;
        if (!GetProcessTimes(process, out creation, out exit, out kernel, out user))
        {
            queryError = Marshal.GetLastWin32Error();
            return false;
        }
        exitTime = ((ulong)exit.High << 32) | exit.Low;
        return exitTime > 0;
    }
}
