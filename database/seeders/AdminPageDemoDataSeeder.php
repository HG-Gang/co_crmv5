<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 12:35
 */

/**
 * 后台页面演示数据 Seeder。
 *
 * 文件功能：
 * - 为 5 组后台功能页补齐主列表数据：大代理（big_agents）、黑名单（blacklists）、
 *   销户申请（cancel_applies）、在线用户（user_onlines）、数据范围绑定（admin_agent_bindings），
 *   以及佣金转账 Saga（commission_transfers + commission_transfer_outbox）。
 *
 * 为什么需要它：
 * - 上述表在隔离验收库里为空，对应的 10 个后台页面（5 组功能 × layui/CrmUI 两套 UI）
 *   主表格只能渲染空态。空态下「长金额撑破单元格」「表格横向溢出」「斑马纹对比度」
 *   这类排版缺陷不可能暴露，浏览器验收给出的「0 缺陷」结论不可信。
 * - 因此演示行刻意采用长短混排：超长用户名/邮箱/备注、满位金额与常规值同时存在，
 *   让列宽自适应与截断策略在真实内容下受检。
 *
 * 安全边界（关键）：
 * - 生产环境绝不允许出现演示数据。本 Seeder 只能由 DatabaseSeeder 在双重闸门后调用：
 *   1) app()->environment('local', 'testing')；
 *   2) config('seeding.admin_page_demo_enabled') === true（对应 ADMIN_PAGE_DEMO_SEEDER_ENABLED）。
 *   run() 内部还会再校验一次，防止有人用 --class= 直接绕过 DatabaseSeeder。
 * - admin_agent_bindings 既是展示表也是授权过滤表：写入会改变对应管理员的可见数据范围。
 *   因此只绑定演示管理员账号，绝不触碰超管（admins.id=1，本身旁路数据范围），
 *   避免演示数据影响权限判定的正确性。
 * - 全部使用 updateOrInsert / insertOrIgnore 并以业务唯一键去重，重复执行不产生重复行。
 * - 演示口令一律走 Hash::make，即使是演示数据也不留明文。
 */

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminPageDemoDataSeeder extends Seeder
{
    /**
     * 超长中文名称样本：用于压测列宽自适应与截断策略。
     *
     * 逻辑说明：
     * - 长度 42 字符，同时满足 big_agents.username(200)、blacklists.name(100)、
     *   cancel_applies.user_name(100) 的字段上限，可在三张表复用。
     * - 全中文字符渲染宽度约为等量 ASCII 的两倍，是最容易撑破单元格的真实形态。
     *
     * @var string
     */
    private const LONG_NAME = '环球金融控股集团华南区机构业务部超长代理商名称列宽压测样本账号甲壹贰叁肆伍陆柒捌玖拾';

    /**
     * 满位金额样本：decimal(18,2) 的整数部分取到 16 位。
     *
     * 逻辑说明：
     * - 金额列通常按常规值（几千）设计列宽，满位金额是暴露溢出的最短路径。
     * - 取不重复数字而非全 9，便于在截图里判断是否发生中间截断。
     *
     * @var string
     */
    private const LONG_AMOUNT = '1234567890123456.78';

    /**
     * 演示口令：仅用于 local/testing 环境的演示账号。
     *
     * @var string
     */
    private const DEMO_PASSWORD = 'abc123';

    /**
     * 写入后台页面演示数据。
     *
     * @return void
     */
    public function run(): void
    {
        // 双重闸门在 Seeder 内部再校验一次：即使有人用
        // `php artisan db:seed --class=AdminPageDemoDataSeeder` 绕过 DatabaseSeeder，
        // 演示数据也不可能落进非 local/testing 环境或未开开关的库。
        if (!$this->demoSeedingAllowed()) {
            if ($this->command !== null) {
                $this->command->warn(
                    'Admin page demo seeding skipped: requires local/testing env and ADMIN_PAGE_DEMO_SEEDER_ENABLED=true.'
                );
            }

            return;
        }

        DB::transaction(function (): void {
            $this->seedBigAgents();
            $this->seedBlacklists();
            $this->seedCancelApplies();
            $this->seedUserOnlines();
            $this->seedAdminAgentBindings();
            $this->seedCommissionTransfers();
        });

        if ($this->command !== null) {
            $this->command->info('Admin page demo data seeded (big agents, blacklist, cancel applies, online users, data scopes, commission transfers).');
        }
    }

    /**
     * 判断当前进程是否允许写入后台页面演示数据。
     *
     * 双重闸门：
     * - 环境必须是 local 或 testing。
     * - config('seeding.admin_page_demo_enabled') 必须显式为布尔 true。
     *
     * @return bool 允许写入返回 true；任一条件不满足都失败关闭。
     */
    private function demoSeedingAllowed(): bool
    {
        return app()->environment('local', 'testing')
            && config('seeding.admin_page_demo_enabled', false) === true;
    }

    /**
     * 写入大代理演示账号（/admin/big-agents 与 /admin-crmui/big-agents 的主列表源）。
     *
     * 数据形态说明：
     * - 首行 username 取超长值、sub_agent_ids 放 8 个标识，让这两列成为最易溢出的列。
     * - 另两行取常规长度并含一行停用态，形成长短混排与状态列对比。
     *
     * @return void
     */
    private function seedBigAgents(): void
    {
        if (!Schema::hasTable('big_agents')) {
            return;
        }

        $now = time();
        $rows = [
            [
                'email' => 'institutional.prime.brokerage.master.agent@demo-crmv5.example.com',
                'username' => self::LONG_NAME,
                'sub_agent_ids' => '1001,1101,1102,600101,600102,600103,600104,600105',
                'is_enabled' => 1,
            ],
            [
                'email' => 'big.agent.a@demo-crmv5.example.com',
                'username' => 'Big Agent A',
                'sub_agent_ids' => '1101,1102',
                'is_enabled' => 1,
            ],
            [
                'email' => 'big.agent.b@demo-crmv5.example.com',
                'username' => '大代理演示乙',
                'sub_agent_ids' => '600101',
                // 停用行用于验收状态列与禁用态行的实际观感与对比度。
                'is_enabled' => 0,
            ],
        ];

        foreach ($rows as $row) {
            // email 是业务唯一键：按它幂等写入，重复执行不产生重复账号。
            DB::table('big_agents')->updateOrInsert(
                ['email' => $row['email']],
                array_merge($row, [
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'jwt_token_id' => '',
                    'created_by' => 'seeder',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * 写入黑名单演示记录（/admin/blacklist 与 /admin-crmui/blacklist 的主列表源）。
     *
     * 数据形态说明：
     * - 身份证号取满 18 位真实位长，邮箱取超长值，都是该页最宽的列。
     * - 电话覆盖「带国际区号 + 分隔符」与「纯 11 位数字」两种真实形态。
     *
     * @return void
     */
    private function seedBlacklists(): void
    {
        if (!Schema::hasTable('blacklists')) {
            return;
        }

        $now = time();
        $rows = [
            [
                'name' => self::LONG_NAME,
                'id_card' => '441312198608097755',
                'email' => 'blacklisted.institutional.account.compliance@demo-crmv5.example.com',
                'phone' => '+86 138-0000-0001',
            ],
            [
                'name' => '张三',
                'id_card' => '440321199112125566',
                'email' => 'zhangsan@demo-crmv5.example.com',
                'phone' => '13900000002',
            ],
            [
                'name' => 'John Fitzgerald Doe-Williamson',
                'id_card' => 'HK1234567890123456',
                'email' => 'john.doe@demo-crmv5.example.com',
                'phone' => '+852 9000 0003',
            ],
        ];

        foreach ($rows as $row) {
            // id_card 是业务唯一键：同一身份证不应重复入黑名单。
            DB::table('blacklists')->updateOrInsert(
                ['id_card' => $row['id_card']],
                array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * 写入销户申请演示记录（/admin/cancel-applies 与 /admin-crmui/cancel-applies 的主列表源）。
     *
     * 数据形态说明：
     * - status 三态齐全（0=待处理、1=通过、-1=拒绝），让状态标签的三种配色都受检。
     * - 首行 cancel_remark 取接近 500 字段上限的长文本，是该页最容易撑破行高的列；
     *   被拒行同时带长 reject_reason，验收双长文本列并存时的布局。
     * - user_id 全部取真实存在的业务用户，保证列表 join 出的用户信息不是空值。
     *
     * @return void
     */
    private function seedCancelApplies(): void
    {
        if (!Schema::hasTable('cancel_applies')) {
            return;
        }

        $now = time();
        $longRemark = '客户主动申请销户。原因说明：该账户长期无交易活动，客户已通过工单与电话双重确认'
            . '不再继续使用本平台的经纪服务，要求关闭账户并结清全部剩余权益。已核验客户身份证件与'
            . '预留手机号，确认无未平仓持仓、无在途出入金、无未结算返佣，符合销户受理条件。';
        $rows = [
            [
                'user_id' => 600101,
                'user_name' => self::LONG_NAME,
                'status' => 0,
                'cancel_remark' => $longRemark,
                'reject_reason' => '',
                'updated_by' => '',
            ],
            [
                'user_id' => 600102,
                'user_name' => 'customer2',
                'status' => 1,
                'cancel_remark' => '不再使用，申请销户。',
                'reject_reason' => '',
                'updated_by' => 'superadmin',
            ],
            [
                'user_id' => 600103,
                'user_name' => '客户三号',
                'status' => -1,
                'cancel_remark' => '换平台。',
                'reject_reason' => '账户仍存在未平仓持仓与未结算返佣，按风控要求需先行结清后重新提交销户申请；'
                    . '同时该账户在最近 30 天内有在途出金申请尚未终态，不满足受理条件。',
                'updated_by' => 'superadmin',
            ],
        ];

        foreach ($rows as $row) {
            // 演示数据按「每个用户一条申请」构造，user_id 即幂等键。
            DB::table('cancel_applies')->updateOrInsert(
                ['user_id' => $row['user_id']],
                array_merge($row, [
                    'created_by' => 'seeder',
                    'created_at' => $now - 7200,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * 写入在线用户演示记录（/admin/online-users 与 /admin-crmui/online-users 的主列表源）。
     *
     * 数据形态说明：
     * - user_agent 用真实完整 UA 串：这一列是该页唯一的超长文本列，且长度天然不可控，
     *   不用真实 UA 就测不出它是否会把表格顶出横向滚动。
     * - ip_address 覆盖 IPv4 与满位 IPv6（varchar(45) 的设计上限即为 IPv6）。
     * - last_activity 分布在「刚刚」到「30 分钟前」之间，让在线时长列有梯度。
     *
     * @return void
     */
    private function seedUserOnlines(): void
    {
        if (!Schema::hasTable('user_onlines')) {
            return;
        }

        $now = time();
        $rows = [
            [
                'user_id' => 1001,
                'last_activity' => $now - 15,
                'ip_address' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.6613.138 Safari/537.36 Edg/128.0.2739.79',
            ],
            [
                'user_id' => 1101,
                'last_activity' => $now - 120,
                'ip_address' => '203.0.113.24',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            ],
            [
                'user_id' => 600101,
                'last_activity' => $now - 600,
                'ip_address' => '198.51.100.7',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
            ],
            [
                'user_id' => 600102,
                'last_activity' => $now - 1800,
                'ip_address' => '192.0.2.155',
                'user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-S9110) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.6478.71 Mobile Safari/537.36',
            ],
        ];

        foreach ($rows as $row) {
            // 在线状态按用户维度唯一：同一用户只保留一条最新活跃记录。
            DB::table('user_onlines')->updateOrInsert(
                ['user_id' => $row['user_id']],
                array_merge($row, [
                    'created_at' => $now - 3600,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * 写入管理员数据范围绑定演示记录（/admin/data-scopes 与 /admin-crmui/data-scopes 的主列表源）。
     *
     * 安全边界（必须遵守）：
     * - 本表不只是展示表，它同时是 AdminDataScopeService 的授权过滤源：写入会实际放大
     *   对应管理员的可见数据范围。因此只绑定演示管理员账号，绝不绑定超管（admins.id=1）。
     *   超管本身旁路数据范围，绑定它既无意义又会掩盖过滤逻辑的真实行为。
     * - 只绑定实际存在的管理员与代理，避免列表 join 出空行掩盖排版缺陷。
     *
     * 数据形态说明：
     * - binding_type 覆盖 primary 与 extra 两种，status 覆盖启用与禁用，让该页标签列受检。
     *
     * @return void
     */
    private function seedAdminAgentBindings(): void
    {
        if (!Schema::hasTable('admin_agent_bindings')) {
            return;
        }

        // 演示绑定只挂在真实存在的非超管管理员上；缺任一侧就整体跳过，不制造悬空引用。
        $adminIds = DB::table('admins')
            ->where('id', '>', 1)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->all();
        $agentIds = DB::table('user_infos')
            ->where('account_type', 1)
            ->orderBy('user_id')
            ->limit(2)
            ->pluck('user_id')
            ->all();

        if ($adminIds === [] || $agentIds === []) {
            return;
        }

        $now = time();
        $rows = [
            [
                'admin_id' => (int) $adminIds[0],
                'agent_id' => (int) $agentIds[0],
                'binding_type' => 'primary',
                'status' => 1,
            ],
        ];

        // 第二条绑定用于覆盖 extra + 禁用态；管理员或代理不足两个时自动省略。
        if (isset($adminIds[1], $agentIds[1])) {
            $rows[] = [
                'admin_id' => (int) $adminIds[1],
                'agent_id' => (int) $agentIds[1],
                'binding_type' => 'extra',
                'status' => 0,
            ];
        }

        foreach ($rows as $row) {
            // (admin_id, agent_id) 是业务唯一键：同一对绑定不应重复。
            DB::table('admin_agent_bindings')->updateOrInsert(
                ['admin_id' => $row['admin_id'], 'agent_id' => $row['agent_id']],
                array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * 写入佣金转账 Saga 演示记录（/admin/commissions 与 /admin-crmui/commissions 的主列表源）。
     *
     * 数据形态说明：
     * - status 覆盖 pending / processing / completed / failed / compensated 五态，
     *   让人工对账页的状态筛选与状态标签配色全部受检。
     * - 首行金额取满位值（decimal(18,2) 整数部分 16 位），备注取长文本，
     *   这两列是该页最容易溢出的列。
     * - failed 行带 last_error_code / last_error_message，compensated 行带对账结论，
     *   保证「错误信息」与「对账凭证」这两列不是空列。
     *
     * 安全边界：
     * - payload_ciphertext 保持 null：演示数据不伪造密文，避免与真实加密负载混淆。
     *
     * @return void
     */
    private function seedCommissionTransfers(): void
    {
        if (!Schema::hasTable('commission_transfers')) {
            return;
        }

        $now = time();
        $longRemark = '代理商佣金转账演示备注：本笔为月度返佣结算后的下级代理佣金划转，'
            . '已通过风控校验与小额限额校验，转出方与转入方均为同一代理树内的直属关系。';

        foreach ($this->commissionTransferRows($now, $longRemark) as $row) {
            // local_order_no 是幂等与对账唯一键：按它幂等写入。
            DB::table('commission_transfers')->updateOrInsert(
                ['local_order_no' => $row['local_order_no']],
                array_merge($row, [
                    'idempotency_key' => 'demo-idem-' . $row['local_order_no'],
                    'payload_hash' => hash('sha256', $row['local_order_no']),
                    'created_at' => $now - 86400,
                    'updated_at' => $now,
                ])
            );
        }

        $this->seedCommissionTransferOutbox($now);
    }

    /**
     * 构造佣金转账演示行。
     *
     * 逻辑说明：
     * - 单独拆出来是为了让 seedCommissionTransfers() 保持「取数—写入」的扁平结构，
     *   五态数据集本身较长，混在写入逻辑里会掩盖幂等键的处理。
     *
     * @param int    $now        当前 10 位时间戳。
     * @param string $longRemark 长备注文本，用于压测备注列宽。
     * @return array<int, array<string, mixed>> 演示行集合。
     */
    private function commissionTransferRows(int $now, string $longRemark): array
    {
        return [
            [
                'local_order_no' => 'CMT20260901DEMO0001',
                'source_user_id' => 1001,
                'target_user_id' => 1101,
                'request_purpose' => 'withdraw',
                'amount' => self::LONG_AMOUNT,
                'remark' => $longRemark,
                'status' => 'pending',
                'current_step' => 'verify',
                'reservation_status' => 'pending',
            ],
            [
                'local_order_no' => 'CMT20260901DEMO0002',
                'source_user_id' => 1001,
                'target_user_id' => 600101,
                'request_purpose' => 'withdraw',
                'amount' => '15800.00',
                'remark' => '月度返佣划转',
                'status' => 'processing',
                'current_step' => 'withdraw',
                'reservation_status' => 'reserved',
                'withdraw_ticket' => 'MT4-W-90210001',
                'attempts' => 1,
                'locked_at' => $now - 30,
            ],
            [
                'local_order_no' => 'CMT20260901DEMO0003',
                'source_user_id' => 1101,
                'target_user_id' => 600103,
                'request_purpose' => 'deposit',
                'amount' => '88.88',
                'remark' => '小额测试划转',
                'status' => 'completed',
                'current_step' => 'deposit',
                'reservation_status' => 'reserved',
                'withdraw_ticket' => 'MT4-W-90210002',
                'deposit_ticket' => 'MT4-D-90210002',
                'source_balance_after' => '20411.12',
                'target_balance_after' => '1088.88',
                'processed_at' => $now - 3600,
                'provider_reference' => 'MT4-REF-90210002',
            ],
            [
                'local_order_no' => 'CMT20260901DEMO0004',
                'source_user_id' => 1102,
                'target_user_id' => 600105,
                'request_purpose' => 'verify',
                'amount' => '2500.00',
                'remark' => '认证校验未通过',
                'status' => 'failed',
                'current_step' => 'verify',
                'reservation_status' => 'pending',
                'attempts' => 3,
                'last_error_code' => 'MT4_GATEWAY_TIMEOUT',
                'last_error_message' => 'MT4 网关在 30 秒内未返回出金结果，已达最大重试次数 3，转人工对账处理。'
                    . '最近一次请求引用：MT4-REQ-90210004。',
                'processed_at' => $now - 1800,
            ],
            [
                'local_order_no' => 'CMT20260901DEMO0005',
                'source_user_id' => 1001,
                'target_user_id' => 600102,
                'request_purpose' => 'withdraw',
                'amount' => '6400.50',
                'remark' => '出金后入金失败，已补偿退回',
                'status' => 'compensated',
                'current_step' => 'deposit',
                'manual_origin_step' => 'deposit',
                'reservation_status' => 'reserved',
                'withdraw_ticket' => 'MT4-W-90210005',
                'compensation_ticket' => 'MT4-C-90210005',
                'attempts' => 2,
                'processed_at' => $now - 900,
                'reconcile_decision' => 'refunded',
                'reconcile_external_reference' => 'MT4-REF-90210005',
                'reconciled_by' => 1,
                'reconciled_at' => $now - 600,
            ],
        ];
    }

    /**
     * 写入佣金转账 Outbox 演示记录。
     *
     * 逻辑说明：
     * - Outbox 行必须挂在真实存在的 commission_transfers.id 上，因此在主表写入之后
     *   按 local_order_no 反查主键，而不是硬编码 ID。
     * - 只为已进入资金分支的三笔转账生成投递事件，保持与主表状态自洽：
     *   processing 生成待投递的 withdraw 事件，completed 生成已完成的 deposit 事件，
     *   compensated 生成已完成的 compensate 事件。
     *
     * @param int $now 当前 10 位时间戳。
     * @return void
     */
    private function seedCommissionTransferOutbox(int $now): void
    {
        if (!Schema::hasTable('commission_transfer_outbox')) {
            return;
        }

        $transferIds = DB::table('commission_transfers')
            ->whereIn('local_order_no', [
                'CMT20260901DEMO0002',
                'CMT20260901DEMO0003',
                'CMT20260901DEMO0005',
            ])
            ->pluck('id', 'local_order_no')
            ->all();

        $rows = [
            'CMT20260901DEMO0002' => [
                'event_type' => 'withdraw',
                'status' => 'pending',
                'attempts' => 1,
                'available_at' => $now + 60,
            ],
            'CMT20260901DEMO0003' => [
                'event_type' => 'deposit',
                'status' => 'completed',
                'attempts' => 1,
                'processed_at' => $now - 3600,
                'provider_reference' => 'MT4-REF-90210002',
            ],
            'CMT20260901DEMO0005' => [
                'event_type' => 'compensate',
                'status' => 'completed',
                'attempts' => 2,
                'processed_at' => $now - 900,
                'provider_reference' => 'MT4-REF-90210005',
            ],
        ];

        foreach ($rows as $orderNo => $row) {
            // 主表缺行时跳过：不制造悬空的 commission_transfer_id。
            if (!isset($transferIds[$orderNo])) {
                continue;
            }

            $transferId = (int) $transferIds[$orderNo];
            // (commission_transfer_id, event_type) 是业务唯一键：同一笔的同类事件只应有一条。
            DB::table('commission_transfer_outbox')->updateOrInsert(
                ['commission_transfer_id' => $transferId, 'event_type' => $row['event_type']],
                array_merge($row, [
                    'commission_transfer_id' => $transferId,
                    'payload_hash' => hash('sha256', $orderNo . '|' . $row['event_type']),
                    'created_at' => $now - 86400,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
