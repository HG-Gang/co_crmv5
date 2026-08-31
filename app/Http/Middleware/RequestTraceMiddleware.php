<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/10
 * Time: 21:14
 */

namespace App\Http\Middleware;

use App\Support\RequestTrace;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 请求链路追踪与接口日志中间件。
 *
 * 文件功能：
 * - 每个请求生成 request_id（ULID）与 trace_id（UUID v4），
 *   注入响应头 X-Request-Id / X-Trace-Id；JSON 对象响应体写入同名字段。
 * - 通过 Log::withContext 关联当前请求的链路标识，后续所有日志自动携带。
 * - 按 config/trace.php 的模块前缀映射判定请求归属模块；
 *   命中模块且开关开启时，记录“请求参数 + 响应摘要”日志到模块 daily channel。
 *
 * 适用场景：
 * - 全局中间件（web 与 api 组），覆盖普通用户、代理商、后台管理员全部接口。
 *
 * 入参例子：
 * - GET /user/flow/depositFlowSearch -> 归属 front_flow，记录请求/响应日志。
 * - POST /api/admin/withdraw/approve -> 归属 admin_withdraw，记录请求/响应日志。
 * - GET /whstest -> 未命中任何模块，仅注入链路标识，不记录接口日志。
 *
 * 返回值：
 * - 响应头：X-Request-Id（26 位 ULID）、X-Trace-Id（36 位 UUID）。
 * - JSON 对象响应体追加 request_id / trace_id 字段；JSON 列表、标量及非 JSON 响应仅注入响应头。
 *
 * 异常或失败场景：
 * - 日志通道不存在时回退默认通道（channel 内部容错）。
 * - 请求参数中的敏感字段（password、Authorization 等）统一脱敏后再落盘。
 */
class RequestTraceMiddleware
{
    /** @var array<string, string> 已脱敏字段的掩码值。 */
    private const MASKED = '******';

    /**
     * 请求入口：生成链路标识、注入响应、记录请求日志。
     *
     * @param Request $request 当前请求对象。
     * @param Closure $next 下一层中间件。
     * @return mixed 响应对象（已注入链路标识）。
     */
    public function handle(Request $request, Closure $next)
    {
        $requestId = RequestTrace::ulid();
        $traceId = RequestTrace::uuid();

        // 贯穿当前请求所有执行链路：供 Monolog Processor 注入到所有日志通道。
        RequestTrace::begin($requestId, $traceId);

        // 关联到 Laravel 日志上下文：本请求内所有 Log::* 自动携带两个标识。
        Log::withContext([
            'request_id' => $requestId,
            'trace_id' => $traceId,
        ]);

        $module = $this->resolveModule($request->path());
        // 链路标识与归属模块写入请求属性，供 terminate 阶段与全局异常兜底统一读取。
        $request->attributes->set('crm_request_id', $requestId);
        $request->attributes->set('crm_trace_id', $traceId);
        $request->attributes->set('crm_module', $module);

        if ($module && config('trace.log_requests_enabled', true)) {
            $this->logRequest($request, $module, $requestId, $traceId);
        }

        $response = $next($request);

        return $this->injectTrace($response, $requestId, $traceId);
    }

    /**
     * 请求结束后记录响应摘要日志（含状态码与正文摘要）。
     *
     * @param Request $request 当前请求对象。
     * @param mixed $response 响应对象。
     * @return void 无返回值。
     */
    public function terminate(Request $request, $response): void
    {
        $module = (string) $request->attributes->get('crm_module', '');
        if (! $module || ! config('trace.log_requests_enabled', true)) {
            return;
        }

        $body = '';
        $status = 0;
        if ($response instanceof Response || $response instanceof JsonResponse) {
            $status = $response->getStatusCode();
            // 二进制/流式响应（图片、文件、ZIP 等）不记录正文，仅记录状态与类型，避免污染日志。
            $contentType = (string) $response->headers->get('Content-Type', '');
            if (preg_match('#^application/json#i', $contentType)) {
                $body = $this->truncate((string) $response->getContent());
            } elseif (preg_match('#^text/#i', $contentType)) {
                // HTML 等文本页面只记开头摘要 + 总长度，避免整页 HTML 撑爆日志。
                $raw = (string) $response->getContent();
                $body = sprintf('[text %s, %d bytes] %s', $contentType, strlen($raw), mb_substr($raw, 0, 300));
            } else {
                $body = sprintf('[binary %s, %d bytes]', $contentType ?: 'unknown', strlen((string) $response->getContent()));
            }
        } elseif ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            $status = $response->getStatusCode();
        }

        Log::channel($this->channelFor($module))->info(
            sprintf('%s=响应日志', $module),
            [
                'status' => $status,
                'body' => $body,
                'url' => $request->url(),
                'method' => $request->method(),
                'request_id' => (string) $request->attributes->get('crm_request_id', ''),
                'trace_id' => (string) $request->attributes->get('crm_trace_id', ''),
            ]
        );
    }

    /**
     * 记录请求参数日志（按用户约定格式：模块名=请求参数）。
     *
     * @param Request $request 当前请求对象。
     * @param string $module 模块名。
     * @param string $requestId 请求 ULID。
     * @param string $traceId 链路 UUID。
     * @return void 无返回值。
     */
    private function logRequest(Request $request, string $module, string $requestId, string $traceId): void
    {
        Log::channel($this->channelFor($module))->info(
            sprintf('%s=请求参数', $module),
            [
                'data' => $this->sanitizeParams($request->all()),
                'url' => $request->url(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
                'request_id' => $requestId,
                'trace_id' => $traceId,
            ]
        );
    }

    /**
     * 按模块名解析日志通道（通道不存在时回退默认通道）。
     *
     * @param string $module 模块名。
     * @return string 日志通道名。
     */
    private function channelFor(string $module): string
    {
        $channels = config('logging.channels', []);
        return isset($channels[$module]) ? $module : config('logging.default', 'stack');
    }

    /**
     * 按 URL 路径前缀匹配模块。
     *
     * @param string $path 请求路径（不含前导斜杠）。
     * @return string 模块名；未命中返回空字符串。
     */
    private function resolveModule(string $path): string
    {
        $path = ltrim($path, '/');
        foreach (config('trace.modules', []) as $module => $prefixes) {
            foreach ($prefixes as $prefix) {
                // 前缀匹配三种形态：路径完全相等、子路径前缀、点号子资源（如 module.action），避免同前缀模块互相误判。
                if ($path === $prefix || strpos($path, $prefix . '/') === 0 || strpos($path, $prefix) === 0 && strpos($path, $prefix . '.') !== false) {
                    return $module;
                }
            }
        }
        return '';
    }

    /**
     * 将链路标识注入响应头与 JSON 对象响应体。
     *
     * @param mixed $response 响应对象。
     * @param string $requestId 请求 ULID。
     * @param string $traceId 链路 UUID。
     * @return mixed 注入后的响应对象。
     */
    private function injectTrace($response, string $requestId, string $traceId)
    {
        // JSON object roots receive trace fields; list and scalar roots keep their body shape unchanged.
        if ($response instanceof JsonResponse) {
            $data = $response->getData();
            if (is_object($data)) {
                $data->request_id = $requestId;
                $data->trace_id = $traceId;
                $response->setData($data);
            }
            $response->headers->set('X-Request-Id', $requestId);
            $response->headers->set('X-Trace-Id', $traceId);
        } elseif ($response instanceof Response) {
            $response->headers->set('X-Request-Id', $requestId);
            $response->headers->set('X-Trace-Id', $traceId);
        }

        return $response;
    }

    /**
     * 请求参数脱敏：password 类字段掩码。
     *
     * @param array<string, mixed> $params 原始请求参数。
     * @return array<string, mixed> 脱敏后的参数。
     */
    private function sanitizeParams(array $params): array
    {
        // 参数名命中密码/令牌/签名类关键字即整体掩码，避免敏感值落盘。
        foreach ($params as $key => $value) {
            if (preg_match('/(password|passwd|pwd|secret|token|code|sign)/i', (string) $key)) {
                $params[$key] = self::MASKED;
            }
        }
        return $params;
    }

    /**
     * Header 脱敏：authorization / cookie / csrf 类掩码。
     *
     * @param array<string, mixed> $headers 原始 Header 集合。
     * @return array<string, mixed> 脱敏后的 Header 集合。
     */
    private function sanitizeHeaders(array $headers): array
    {
        $masked = (array) config('trace.masked_headers', []);
        foreach ($headers as $key => $value) {
            if (in_array(strtolower((string) $key), $masked, true)) {
                $headers[$key] = self::MASKED;
            }
        }
        return $headers;
    }

    /**
     * 截断超长正文。
     *
     * @param string $content 原始正文。
     * @return string 截断后的正文。
     */
    private function truncate(string $content): string
    {
        $limit = (int) config('trace.response_body_limit', 2048);
        return mb_strlen($content) > $limit ? mb_substr($content, 0, $limit) . '...(truncated)' : $content;
    }
}
