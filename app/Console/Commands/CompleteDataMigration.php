<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * 完整业务数据迁移命令
 *
 * 迁移核心业务表：
 * - deposit_record_log → deposit_records (入金记录)
 * - draw_record_log → withdraw_records (出金记录)
 * - voucher_info → voucher_infos (凭证信息)
 * - cancel_apply → cancel_applies (销户申请)
 * - operation_log → operation_logs (操作日志)
 * - system_login_log → admin_login_logs (管理员登录日志)
 * - user_img → user_images (用户图片)
 * - mt4_users → mt4_users (MT4用户)
 * - symbol_prices → symbol_prices (符号价格)
 * - user_addresses → user_addresses (用户地址)
 * - user_online → user_onlines (在线用户)
 * - trans_apply_log → trans_apply_logs (转账申请日志)
 * - hierarchy → agent_descendants (代理层级关系)
 *
 * 使用方法：
 * php artisan migrate:complete-data [--table=TABLE_NAME]
 */
class CompleteDataMigration extends Command
{
    protected $signature = 'migrate:complete-data {--table= : 迁移指定表}';
    protected $description = '完整业务数据迁移（核心表）';

    private $oldDb = 'old_crm';
    private $newDb = 'mysql';

    public function handle()
    {
        $this->info('====================================');
        $this->info('完整业务数据迁移开始');
        $this->info('====================================');
        $this->newLine();

        $table = $this->option('table');

        try {
            if ($table) {
                $this->migrateTable($table);
            } else {
                $this->migrateAll();
            }

            $this->newLine();
            $this->info('✅ 数据迁移完成！');
            $this->printSummary();

            return 0;

        } catch (Exception $e) {
            $this->error('❌ 数据迁移失败：' . $e->getMessage());
            $this->error('文件：' . $e->getFile() . ':' . $e->getLine());
            return 1;
        }
    }

    private function migrateAll()
    {
        $tables = [
            'deposit_records',
            'withdraw_records',
            'voucher_infos',
            'cancel_applies',
            'operation_logs',
            'admin_login_logs',
            'user_images',
            'mt4_users',
            'symbol_prices',
            'user_addresses',
            'user_onlines',
            'trans_apply_logs',
            'agent_descendants',
        ];

        foreach ($tables as $table) {
            $this->migrateTable($table);
            $this->newLine();
        }
    }

    private function migrateTable($table)
    {
        $method = 'migrate' . str_replace('_', '', ucwords($table, '_'));

        if (!method_exists($this, $method)) {
            $this->warn("⚠️  跳过 {$table}：未实现迁移方法");
            return;
        }

        $this->info("迁移 {$table}...");
        $this->$method();
    }

    private function migrateDepositRecords()
    {
        $count = DB::connection($this->oldDb)->table('deposit_record_log')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $batchSize = 1000;
        $processed = 0;

        DB::connection($this->oldDb)->table('deposit_record_log')
            ->orderBy('dep_id')
            ->chunk(500, function ($records) use (&$batch, &$processed, $batchSize) {
                foreach ($records as $old) {
                    $now = time();

                    // 处理 local_order_no：空值生成唯一号，非空值加 ID 后缀防止重复
                    if (!empty($old->dep_outTrande)) {
                        $localOrderNo = $old->dep_outTrande . '_' . $old->dep_id;
                    } else {
                        $localOrderNo = 'DP' . str_pad($old->dep_id, 10, '0', STR_PAD_LEFT);
                    }

                    $batch[] = [
                        'id' => $old->dep_id,
                        'user_id' => $old->dep_mt4_id,
                        'user_name' => '',
                        'mt4_ticket' => 0,
                        'amount' => $old->dep_amount,
                        'actual_amount' => $old->dep_act_amount,
                        'exchange_rate' => $old->dep_amt_rate,
                        'channel_name' => $old->dep_channel,
                        'channel_order_no' => $old->dep_outChannelNo ?? '',
                        'local_order_no' => $localOrderNo,
                        'merchant_id' => $old->dep_mchId ?? '',
                        'status' => $old->dep_status === '01' ? 'completed' : 'pending',
                        'payment_status' => null,
                        'settlement_status' => null,
                        'payment_time' => null,
                        'remarks' => $old->dep_body,
                        'created_by' => $old->rec_crt_user,
                        'updated_by' => $old->rec_upd_user,
                        'created_at' => strtotime($old->rec_crt_date),
                        'updated_at' => strtotime($old->rec_upd_date),
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::connection($this->newDb)->table('deposit_records')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('deposit_records')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function migrateWithdrawRecords()
    {
        $count = DB::connection($this->oldDb)->table('draw_record_log')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $batchSize = 1000;
        $processed = 0;

        DB::connection($this->oldDb)->table('draw_record_log')
            ->orderBy('record_id')
            ->chunk(500, function ($records) use (&$batch, &$processed, $batchSize) {
                foreach ($records as $old) {
                    // 状态映射：旧库 apply_status: 0=待审核, 1=审核通过, 2=已完成, -1=已拒绝
                    $statusMap = [
                        '0' => 0,  // pending
                        '1' => 1,  // approved
                        '2' => 2,  // completed
                        '-1' => -1, // rejected
                    ];

                    // local_order_no 为空时生成唯一订单号
                    $localOrderNo = !empty($old->orderId_LOC)
                        ? $old->orderId_LOC
                        : 'WD' . str_pad($old->record_id, 10, '0', STR_PAD_LEFT);

                    $batch[] = [
                        'id' => $old->record_id,
                        'user_id' => $old->user_id,
                        'user_name' => $old->user_name,
                        'mt4_ticket' => $old->mt4_trades_no,
                        'apply_amount' => $old->apply_amount,
                        'actual_amount' => $old->act_apply_amount,
                        'fee' => $old->draw_poundage,
                        'exchange_rate' => $old->draw_rate,
                        'rmb_fee' => $old->act_pdg_rmb,
                        'bank_no' => $old->draw_bank_no,
                        'bank_name' => $old->draw_bank_class,
                        'bank_addr' => $old->draw_bank_info,
                        'status' => $statusMap[$old->apply_status] ?? 0,
                        'local_order_no' => $localOrderNo,
                        'third_order_no' => $old->orderId_OTC ?? '',
                        'reject_reason' => $old->apply_remark ?? '',
                        'mt4_return_status' => $old->mt4_return_status,
                        'funding_status' => 'pending',
                        'created_by' => $old->rec_crt_user,
                        'updated_by' => $old->rec_upd_user,
                        'created_at' => strtotime($old->rec_crt_date),
                        'updated_at' => strtotime($old->rec_upd_date),
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::connection($this->newDb)->table('withdraw_records')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('withdraw_records')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function migrateVoucherInfos()
    {
        $count = DB::connection($this->oldDb)->table('voucher_info')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $batchSize = 1000;
        $processed = 0;

        DB::connection($this->oldDb)->table('voucher_info')
            ->where('user_id', '>', 0)
            ->orderBy('id')
            ->chunk(500, function ($records) use (&$batch, &$processed, $batchSize) {
                foreach ($records as $old) {
                    $batch[] = [
                        'id' => $old->id,
                        'user_id' => $old->user_id,
                        'images' => $old->imgs,
                        'remarks' => $old->remarks,
                        'review_status' => $old->review_status,
                        'review_message' => $old->review_msg,
                        'created_by' => $old->rec_crt_user ?? '',
                        'updated_by' => $old->rec_upd_user ?? '',
                        'created_at' => strtotime($old->rec_crt_date),
                        'updated_at' => strtotime($old->rec_upd_date),
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::connection($this->newDb)->table('voucher_infos')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('voucher_infos')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function migrateCancelApplies()
    {
        $count = DB::connection($this->oldDb)->table('cancel_apply')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];

        DB::connection($this->oldDb)->table('cancel_apply')
            ->orderBy('cancel_id')
            ->chunk(500, function ($records) use (&$batch) {
                foreach ($records as $old) {
                    // 状态映射：旧库 cancel_status: 0=待审核, 1=已批准, -1=已拒绝
                    $statusMap = [
                        '0' => 0,
                        '1' => 1,
                        '-1' => -1,
                    ];

                    $batch[] = [
                        'id' => $old->cancel_id,
                        'user_id' => $old->cancel_userid,
                        'user_name' => $old->cancel_username,
                        'status' => $statusMap[$old->cancel_status] ?? 0,
                        'cancel_remark' => $old->cancel_remark,
                        'reject_reason' => '',
                        'created_by' => $old->rec_crt_user,
                        'updated_by' => $old->rec_upd_user,
                        'created_at' => strtotime($old->rec_crt_date),
                        'updated_at' => strtotime($old->rec_upd_date),
                    ];
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('cancel_applies')->insert($batch);
        }

        $this->line("  ✓ 完成: " . count($batch) . " 条");
    }

    private function migrateOperationLogs()
    {
        $count = DB::connection($this->oldDb)->table('operation_log')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];

        DB::connection($this->oldDb)->table('operation_log')
            ->orderBy('id')
            ->chunk(500, function ($records) use (&$batch) {
                foreach ($records as $old) {
                    $batch[] = [
                        'id' => $old->id,
                        'admin_id' => $old->user_id ?? 0,
                        'admin_name' => $old->name,
                        'target_user_id' => $old->order_number > 0 ? $old->order_number : null,
                        'order_no' => null,
                        'content' => $old->content,
                        'ip' => $old->handle_ip,
                        'action_type' => $old->type,
                        'created_at' => $old->created_on,
                        'updated_at' => $old->created_on,
                    ];
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('operation_logs')->insert($batch);
        }

        $this->line("  ✓ 完成: " . count($batch) . " 条");
    }

    private function migrateAdminLoginLogs()
    {
        $count = DB::connection($this->oldDb)->table('system_login_log')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $batchSize = 1000;
        $processed = 0;

        DB::connection($this->oldDb)->table('system_login_log')
            ->orderBy('sys_id')
            ->chunk(1000, function ($records) use (&$batch, &$processed, $batchSize) {
                foreach ($records as $old) {
                    // login_ip 最长 50 字符，截断超长的 IPv6
                    $loginIp = $old->login_ip;
                    if (strlen($loginIp) > 50) {
                        // 如果是重复的 IPv6（逗号分隔），只取第一个
                        $parts = explode(',', $loginIp);
                        $loginIp = trim($parts[0]);
                        // 如果单个 IPv6 还是超长，强制截断
                        if (strlen($loginIp) > 50) {
                            $loginIp = substr($loginIp, 0, 50);
                        }
                    }

                    $batch[] = [
                        'id' => $old->sys_id,
                        'admin_id' => $old->login_id,
                        'login_ip' => $loginIp,
                        'ip_address' => $old->login_id_desc,
                        'user_agent' => null,
                        'created_at' => strtotime($old->login_date),
                        'updated_at' => strtotime($old->login_date),
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::connection($this->newDb)->table('admin_login_logs')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('admin_login_logs')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function migrateUserImages()
    {
        $count = DB::connection($this->oldDb)->table('user_img')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $processed = 0;

        DB::connection($this->oldDb)->table('user_img')
            ->orderBy('img_id')
            ->chunk(500, function ($records) use (&$batch, &$processed) {
                foreach ($records as $old) {
                    $now = time();

                    // 旧库一条记录存多个图片路径，需要拆分成多条
                    $images = [
                        ['type' => 'id_card_front', 'path' => $old->img_idcard01_path],
                        ['type' => 'id_card_back', 'path' => $old->img_idcard02_path],
                        ['type' => 'avatar', 'path' => $old->img_header_path],
                        ['type' => 'bank_card', 'path' => $old->img_bank_path],
                    ];

                    foreach ($images as $img) {
                        if (!empty($img['path'])) {
                            $batch[] = [
                                'user_id' => $old->user_id,
                                'type' => $img['type'],
                                'path' => $img['path'],
                                'mime_type' => null,
                                'created_at' => strtotime($old->rec_crt_date),
                                'updated_at' => strtotime($old->rec_upd_date),
                            ];
                        }
                    }

                    if (count($batch) >= 1000) {
                        DB::connection($this->newDb)->table('user_images')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('user_images')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function migrateMt4Users()
    {
        $count = DB::connection($this->oldDb)->table('mt4_users')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $batchSize = 1000;
        $processed = 0;

        DB::connection($this->oldDb)->table('mt4_users')
            ->orderBy('LOGIN')
            ->chunk(1000, function ($records) use (&$batch, &$processed, $batchSize) {
                foreach ($records as $old) {
                    $now = time();

                    $batch[] = [
                        'login' => $old->LOGIN,
                        'name' => $old->NAME,
                        'group' => $old->GROUP,
                        'balance' => $old->BALANCE,
                        'equity' => $old->EQUITY,
                        'margin' => $old->MARGIN,
                        'margin_free' => $old->MARGIN_FREE,
                        'leverage' => $old->LEVERAGE,
                        'created_at' => strtotime($old->REGDATE),
                        'updated_at' => $old->TIMESTAMP > 0 ? $old->TIMESTAMP : $now,
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::connection($this->newDb)->table('mt4_users')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('mt4_users')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function migrateSymbolPrices()
    {
        $count = DB::connection($this->oldDb)->table('symbol_prices')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];

        DB::connection($this->oldDb)->table('symbol_prices')
            ->orderBy('sym_id')
            ->chunk(500, function ($records) use (&$batch) {
                foreach ($records as $old) {
                    $now = time();

                    $batch[] = [
                        'id' => $old->sym_id,
                        'symbol' => $old->sym_symbol,
                        'time' => $old->sym_time,
                        'bid' => $old->sym_bid,
                        'ask' => $old->sym_ask,
                        'low' => $old->sym_low,
                        'high' => $old->sym_high,
                        'direction' => $old->sym_direction,
                        'digits' => $old->sym_digits,
                        'spread' => $old->sym_spread,
                        'group_id' => $old->sym_grp_id,
                        'status' => $old->voided === '0' ? 1 : 0,
                        'modify_time' => $old->sym_modify_time,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('symbol_prices')->insert($batch);
        }

        $this->line("  ✓ 完成: " . count($batch) . " 条");
    }

    private function migrateUserAddresses()
    {
        $count = DB::connection($this->oldDb)->table('user_addresses')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];

        DB::connection($this->oldDb)->table('user_addresses')
            ->orderBy('id')
            ->chunk(500, function ($records) use (&$batch) {
                foreach ($records as $old) {
                    $batch[] = [
                        'id' => $old->id,
                        'user_id' => $old->user_id,
                        'recipient_name' => $old->recipient_name,
                        'recipient_phone' => $old->recipient_phone,
                        'recipient_address' => $old->recipient_address,
                        'is_default' => $old->is_default,
                        'created_at' => strtotime($old->created_at),
                        'updated_at' => strtotime($old->updated_at),
                    ];
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('user_addresses')->insert($batch);
        }

        $this->line("  ✓ 完成: " . count($batch) . " 条");
    }

    private function migrateUserOnlines()
    {
        $count = DB::connection($this->oldDb)->table('user_online')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $batchSize = 1000;
        $processed = 0;

        DB::connection($this->oldDb)->table('user_online')
            ->orderBy('id')
            ->chunk(1000, function ($records) use (&$batch, &$processed, $batchSize) {
                foreach ($records as $old) {
                    // ip_address 最长 45 字符，截断超长的 IP（逗号分隔多个 IP 时取第一个）
                    $ipAddress = $old->ip;
                    if (strlen($ipAddress) > 45) {
                        $parts = explode(',', $ipAddress);
                        $ipAddress = trim($parts[0]);
                        // 如果单个 IP 还是超长，强制截断
                        if (strlen($ipAddress) > 45) {
                            $ipAddress = substr($ipAddress, 0, 45);
                        }
                    }

                    $batch[] = [
                        'id' => $old->id,
                        'user_id' => $old->user_id,
                        'last_activity' => $old->last_active,
                        'ip_address' => $ipAddress,
                        'user_agent' => $old->req_url ?? null,
                        'created_at' => strtotime($old->created_at),
                        'updated_at' => strtotime($old->updated_at),
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::connection($this->newDb)->table('user_onlines')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('user_onlines')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function migrateTransApplyLogs()
    {
        $count = DB::connection($this->oldDb)->table('trans_apply_log')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];

        DB::connection($this->oldDb)->table('trans_apply_log')
            ->orderBy('trans_id')
            ->chunk(500, function ($records) use (&$batch) {
                foreach ($records as $old) {
                    $batch[] = [
                        'id' => $old->trans_id,
                        'user_id' => $old->trans_uid,
                        'origin_group_id' => 0,
                        'group_id' => $old->trans_type_gid,
                        'group_name' => $old->trans_type_name,
                        'applicant_id' => $old->trans_apply_uid,
                        'applicant_name' => $old->trans_apply_uname,
                        'status' => $old->trans_apply_status,
                        'apply_reason' => $old->trans_apply_reason,
                        'reject_reason' => null,
                        'created_by' => $old->rec_crt_user,
                        'updated_by' => $old->rec_upd_user,
                        'created_at' => strtotime($old->rec_crt_date),
                        'updated_at' => strtotime($old->rec_upd_date),
                    ];
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('trans_apply_logs')->insert($batch);
        }

        $this->line("  ✓ 完成: " . count($batch) . " 条");
    }

    private function migrateAgentDescendants()
    {
        // 使用 hierarchy 表（最完整的层级关系数据）
        $count = DB::connection($this->oldDb)->table('hierarchy')->count();
        $this->line("  旧库记录数: {$count}");

        if ($count === 0) {
            $this->line('  无数据需要迁移');
            return;
        }

        $batch = [];
        $batchSize = 1000;
        $processed = 0;

        DB::connection($this->oldDb)->table('hierarchy')
            ->orderBy('parent_id')
            ->orderBy('child_id')
            ->chunk(1000, function ($records) use (&$batch, &$processed, $batchSize) {
                $now = time();

                foreach ($records as $old) {
                    $batch[] = [
                        'agent_id' => $old->parent_id,
                        'descendant_id' => $old->child_id,
                        'descendant_type' => 0, // 默认值
                        'depth' => $old->depth,
                        'is_direct' => $old->is_direct,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::connection($this->newDb)->table('agent_descendants')->insert($batch);
                        $processed += count($batch);
                        $this->line("    已处理: {$processed} 条");
                        $batch = [];
                    }
                }
            });

        if (count($batch) > 0) {
            DB::connection($this->newDb)->table('agent_descendants')->insert($batch);
            $processed += count($batch);
        }

        $this->line("  ✓ 完成: {$processed} 条");
    }

    private function printSummary()
    {
        $this->info('====================================');
        $this->info('迁移后数据统计');
        $this->info('====================================');

        $tables = [
            'deposit_records' => '入金记录',
            'withdraw_records' => '出金记录',
            'voucher_infos' => '凭证信息',
            'cancel_applies' => '销户申请',
            'operation_logs' => '操作日志',
            'admin_login_logs' => '管理员登录日志',
            'user_images' => '用户图片',
            'mt4_users' => 'MT4用户',
            'symbol_prices' => '符号价格',
            'user_addresses' => '用户地址',
            'user_onlines' => '在线用户',
            'trans_apply_logs' => '转账申请日志',
            'agent_descendants' => '代理层级关系',
        ];

        foreach ($tables as $table => $label) {
            $count = DB::connection($this->newDb)->table($table)->count();
            $this->line("{$label} ({$table}): " . number_format($count) . " 条");
        }
    }
}
