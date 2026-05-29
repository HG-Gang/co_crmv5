<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyFrontBusinessDataSeeder extends Seeder
{
    private $legacy;
    private $counts = [];
    private $emailCounts = [];
    private $usedEmails = [];
    private $groupMap = [];
    private $levelMap = [];
    private $accountTypes = [];
    private $parentMap = [];
    private $familyTrees = [];
    private $userNames = [];

    public function run()
    {
        $this->connectLegacy();
        DB::connection()->disableQueryLog();
        $this->legacy->disableQueryLog();
        $this->loadReferenceMaps();

        $this->migrateUsers('agents', 1);
        $this->migrateUsers('user', 2);
        $this->restoreFrontTestAgent();
        $this->refreshUserMaps();
        $this->migrateAgentDescendants();
        $this->migrateDeposits();
        $this->migrateWithdrawals();
        $this->migrateTrades();
        $this->migrateVouchers();
        $this->migrateAddresses();
        $this->migrateGiftShipments();
        $this->migrateGroupChangeApplies();
        $this->migrateNewsLangs();
        $this->updateIdSequences();

        foreach ($this->counts as $name => $count) {
            $this->command->info($name . ': ' . $count);
        }
    }

    private function connectLegacy(): void
    {
        $connection = config('database.connections.mysql');
        $connection['host'] = env('LEGACY_DB_HOST', '127.0.0.1');
        $connection['port'] = env('LEGACY_DB_PORT', '3307');
        $connection['database'] = env('LEGACY_DB_DATABASE', 'hank_zl_data');
        $connection['username'] = env('LEGACY_DB_USERNAME', 'root');
        $connection['password'] = env('LEGACY_DB_PASSWORD', '123456');
        $connection['charset'] = 'utf8mb4';
        $connection['collation'] = 'utf8mb4_unicode_ci';

        Config::set('database.connections.legacy_front_business', $connection);
        DB::purge('legacy_front_business');
        $this->legacy = DB::connection('legacy_front_business');
        DB::connection()->disableQueryLog();
        $this->legacy->disableQueryLog();
        $this->legacy->getPdo();
    }

    private function loadReferenceMaps(): void
    {
        $this->emailCounts = $this->legacy
            ->table(DB::raw('(select email from agents union all select email from user) as legacy_emails'))
            ->selectRaw('lower(trim(email)) as email_key, count(*) as total')
            ->whereNotNull('email')
            ->whereRaw("trim(email) <> ''")
            ->groupByRaw('lower(trim(email))')
            ->pluck('total', 'email_key')
            ->map(function ($count) {
                return (int) $count;
            })
            ->all();

        $this->usedEmails = DB::table('user_logins')
            ->whereNotNull('email')
            ->pluck('user_id', 'email')
            ->mapWithKeys(function ($userId, $email) {
                return [strtolower((string) $email) => (int) $userId];
            })
            ->all();

        $this->groupMap = DB::table('group_configs')
            ->whereNotNull('pair_id')
            ->pluck('id', 'pair_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $this->levelMap = DB::table('agent_levels')
            ->pluck('id', 'level_code')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();
    }

    private function migrateUsers(string $table, int $accountType): void
    {
        $count = 0;
        $this->legacy->table($table)
            ->orderBy('user_id')
            ->chunk(500, function ($rows) use ($table, $accountType, &$count) {
                foreach ($rows as $row) {
                    $this->upsertUser($row, $accountType);
                    $count++;
                }
            });

        $this->counts[$table . '_users'] = $count;
    }

    private function upsertUser($row, int $accountType): void
    {
        $userId = (int) $row->user_id;
        if ($userId <= 0) {
            return;
        }

        $now = time();
        $createdAt = $this->timestamp($row->rec_crt_date ?? null, $now);
        $updatedAt = $this->timestamp($row->rec_upd_date ?? null, $createdAt);
        $deletedAt = $this->deletedAtFromVoided($row->voided ?? '1', $updatedAt);
        $email = $this->uniqueEmail($row->email ?? '', $userId);

        DB::table('user_logins')->updateOrInsert(
            ['user_id' => $userId],
            [
                'email' => $email,
                'password' => $this->passwordHash($row->password ?? ''),
                'account_type' => $accountType,
                'is_enabled' => (int) ($row->enable ?? 1) === 1 && $deletedAt === null ? 1 : 0,
                'is_cancelled' => $deletedAt === null ? 0 : 1,
                'source_type' => 1,
                'jwt_token_id' => '',
                'last_login_ip' => '',
                'last_login_at' => $this->nullableDateTime($row->last_logindate ?? ($row->Last_landing_time ?? null)),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $deletedAt,
            ]
        );
        $loginId = (int) DB::table('user_logins')->where('user_id', $userId)->value('id');

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => $this->string($row->user_name ?? '', 'Legacy User ' . $userId),
                'phone' => $this->string($row->phone ?? ''),
                'gender' => $this->gender($row->sex ?? 1),
                'avatar' => null,
                'level_id' => $this->levelId($accountType),
                'group_id' => $this->groupId((int) ($row->group_id ?? 0), $accountType),
                'parent_id' => (int) ($row->parent_id ?? 0),
                'account_type' => $accountType,
                'family_tree' => $this->string($row->family_tree ?? '', (string) $userId),
                'total_funds' => $this->float($row->user_money ?? 0),
                'used_margin' => $this->float($row->used_bond_money ?? 0),
                'avail_margin' => $this->float($row->available_bond_money ?? 0),
                'equity' => $this->float($row->cust_eqy ?? 0),
                'effective_credit' => $this->float($row->effective_cdt ?? 0),
                'risk_ratio' => $this->float($row->risk_rate ?? 0),
                'margin_amount' => $this->float($row->bond_money ?? 0),
                'leverage' => (int) $this->float($row->cust_lvg ?? 0),
                'cust_vol' => $this->string($row->cust_vol ?? '0'),
                'pay_provider_id' => (int) ($row->player_Id ?? 0),
                'equity_ratio' => (int) ($row->rights ?? 0),
                'comm_rate' => (int) ($row->comm_prop ?? 0),
                'is_ecn' => (int) ($row->is_enc ?? 0),
                'follow_parent_ecn' => (int) ($row->enc_look ?? 0),
                'auth_status' => (string) ($row->user_status ?? '0') === '1' ? 1 : 0,
                'is_mt4_synced' => 1,
                'is_mt4_enabled' => (int) ($row->enable ?? 1) === 1 ? 1 : 0,
                'is_mt4_readonly' => (int) ($row->enable_readonly ?? 0),
                'is_withdrawal_allowed' => (int) ($row->is_out_money ?? 0),
                'is_deposit_allowed' => (int) ($row->is_allow_money ?? 0),
                'is_agent_confirmed' => (int) ($row->is_confirm_agents_lvg ?? 0),
                'original_group' => $this->string($row->original_grp ?? ''),
                'mt4_group' => $this->string($row->mt4_grp ?? ''),
                'mt4_code' => (int) ($row->mt4_code ?? 0),
                'trading_mode' => (int) ($row->trans_mode ?? 0),
                'settle_method' => (int) ($row->settlement_model ?? 0),
                'settle_cycle' => (int) ($row->cycle ?? 0),
                'country' => $this->string($row->country ?? ''),
                'city' => $this->string($row->city ?? ''),
                'state' => $this->string($row->state ?? ''),
                'address' => $this->string($row->address ?? ''),
                'is_gift_allowed' => (int) ($row->gift_allowed ?? 0),
                'data_source' => (int) ($row->data_source ?? 1),
                'remark' => $this->string($row->remark ?? ''),
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $deletedAt,
            ]
        );

        DB::table('user_auths')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bank_no' => $this->string($row->bank_no ?? ''),
                'bank_no_tmp' => $this->string($row->bank_no_tmp ?? ''),
                'bank_name' => $this->string($row->bank_class ?? ''),
                'bank_name_tmp' => $this->string($row->bank_class_tmp ?? ''),
                'bank_card_img' => $this->string($row->bank_img ?? ''),
                'bank_card_img_tmp' => $this->string($row->bank_img_tmp ?? ''),
                'bank_addr' => $this->string($row->bank_info ?? ''),
                'bank_addr_tmp' => $this->string($row->bank_info_tmp ?? ''),
                'bank_status' => (int) ($row->bank_status ?? 0),
                'bank_remarks' => $this->string($row->bank_remarks ?? ''),
                'id_card_no' => $this->string($row->IDcard_no ?? ''),
                'id_card_status' => (int) ($row->IDcard_status ?? 0),
                'id_card_front' => $this->string($row->IDcard_img ?? ''),
                'id_card_back' => $this->string($row->IDcard_negative ?? ''),
                'id_card_remarks' => $this->string($row->IDcard_remarks ?? ''),
                'is_bank_synced' => (int) ($row->bank_synchro ?? 0),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $deletedAt,
            ]
        );
    }

    private function refreshUserMaps(): void
    {
        $this->accountTypes = DB::table('user_infos')->pluck('account_type', 'user_id')->map(function ($value) {
            return (int) $value;
        })->all();
        $this->parentMap = DB::table('user_infos')->pluck('parent_id', 'user_id')->map(function ($value) {
            return (int) $value;
        })->all();
        $this->familyTrees = DB::table('user_infos')->pluck('family_tree', 'user_id')->map(function ($value) {
            return (string) $value;
        })->all();
        $this->userNames = DB::table('user_infos')->pluck('user_name', 'user_id')->map(function ($value) {
            return (string) $value;
        })->all();
    }

    private function migrateAgentDescendants(): void
    {
        $count = 0;
        $this->legacy->table('agent_relations')
            ->orderBy('parent_id')
            ->chunk(1000, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $agentId = (int) $row->parent_id;
                    $descendantId = (int) $row->child_id;
                    if ($agentId <= 0 || $descendantId <= 0 || $agentId === $descendantId) {
                        continue;
                    }
                    if (($this->accountTypes[$agentId] ?? 0) !== 1 || !isset($this->accountTypes[$descendantId])) {
                        continue;
                    }

                    $batch[] = [
                        'agent_id' => $agentId,
                        'descendant_id' => $descendantId,
                        'descendant_type' => (int) $this->accountTypes[$descendantId],
                        'is_direct' => (($this->parentMap[$descendantId] ?? 0) === $agentId) ? 1 : 0,
                        'depth' => $this->relationDepth($agentId, $descendantId),
                        'created_at' => time(),
                        'updated_at' => time(),
                        'deleted_at' => null,
                    ];
                }
                $this->upsertRows('agent_descendants', $batch, ['agent_id', 'descendant_id']);
                $count += count($batch);
            });

        $this->migrateFamilyTreeDescendants($count);
        $this->counts['agent_descendants'] = $count;
    }

    private function migrateFamilyTreeDescendants(int &$count): void
    {
        $batch = [];

        foreach ($this->accountTypes as $descendantId => $descendantType) {
            $descendantId = (int) $descendantId;
            $tree = array_values(array_filter(array_map('intval', explode(',', $this->familyTrees[$descendantId] ?? ''))));
            if (!$tree) {
                $parentId = (int) ($this->parentMap[$descendantId] ?? 0);
                $tree = $parentId > 0 ? [$parentId, $descendantId] : [$descendantId];
            }
            if (!in_array($descendantId, $tree, true)) {
                $tree[] = $descendantId;
            }

            $descendantIndex = array_search($descendantId, $tree, true);
            if ($descendantIndex === false) {
                continue;
            }

            for ($i = 0; $i < $descendantIndex; $i++) {
                $agentId = (int) $tree[$i];
                if ($agentId <= 0 || $agentId === $descendantId || ($this->accountTypes[$agentId] ?? 0) !== 1) {
                    continue;
                }

                $batch[] = [
                    'agent_id' => $agentId,
                    'descendant_id' => $descendantId,
                    'descendant_type' => (int) $descendantType,
                    'is_direct' => (($this->parentMap[$descendantId] ?? 0) === $agentId) ? 1 : 0,
                    'depth' => max(1, $descendantIndex - $i),
                    'created_at' => time(),
                    'updated_at' => time(),
                    'deleted_at' => null,
                ];

                if (count($batch) >= 2000) {
                    $this->upsertRows('agent_descendants', $batch, ['agent_id', 'descendant_id']);
                    $count += count($batch);
                    $batch = [];
                }
            }
        }

        if ($batch) {
            $this->upsertRows('agent_descendants', $batch, ['agent_id', 'descendant_id']);
            $count += count($batch);
        }
    }

    private function migrateDeposits(): void
    {
        $count = 0;
        $this->legacy->table('deposit_record_log')
            ->orderBy('dep_id')
            ->chunk(1000, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $createdAt = $this->timestamp($row->rec_crt_date ?? null, time());
                    $updatedAt = $this->timestamp($row->rec_upd_date ?? null, $createdAt);
                    $userId = $this->depositUserId($row->dep_body ?? '');
                    $batch[] = [
                        'id' => (int) $row->dep_id,
                        'user_id' => $userId,
                        'user_name' => $this->userNames[$userId] ?? '',
                        'mt4_ticket' => (int) ($row->dep_mt4_id ?? 0),
                        'amount' => $this->float($row->dep_act_amount ?? 0),
                        'actual_amount' => $this->float($row->dep_amount ?? 0),
                        'exchange_rate' => $this->float($row->dep_amt_rate ?? 0),
                        'channel_name' => $this->string($row->dep_channel ?? ''),
                        'channel_order_no' => $this->string($row->dep_channel_no ?? ''),
                        'local_order_no' => $this->string($row->dep_outTrande ?? '', 'LEGACY-DEP-' . (int) $row->dep_id),
                        'status' => $this->string($row->dep_status ?? '01', '01'),
                        'payment_time' => $this->nullableDateTime($row->rec_upd_date ?? null),
                        'remarks' => $this->string($row->dep_body ?? ''),
                        'created_by' => $this->string($row->rec_crt_user ?? ''),
                        'updated_by' => $this->string($row->rec_upd_user ?? ''),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => null,
                    ];
                }
                $this->upsertRows('deposit_records', $batch, ['id']);
                $count += count($batch);
            });

        $this->counts['deposit_records'] = $count;
    }

    private function migrateWithdrawals(): void
    {
        $count = 0;
        $this->legacy->table('draw_record_log')
            ->orderBy('record_id')
            ->chunk(1000, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $createdAt = $this->timestamp($row->rec_crt_date ?? null, time());
                    $updatedAt = $this->timestamp($row->rec_upd_date ?? null, $createdAt);
                    $batch[] = [
                        'id' => (int) $row->record_id,
                        'user_id' => (int) ($row->user_id ?? 0),
                        'user_name' => $this->string($row->user_name ?? ''),
                        'mt4_ticket' => $this->string($row->mt4_trades_no ?? ''),
                        'apply_amount' => $this->float($row->apply_amount ?? 0),
                        'actual_amount' => $this->float($row->act_apply_amount ?? $row->apply_amount ?? 0),
                        'fee' => $this->float($row->draw_poundage ?? 0),
                        'exchange_rate' => $this->float($row->draw_rate ?? 0),
                        'rmb_fee' => $this->float($row->act_pdg_rmb ?? 0),
                        'bank_no' => $this->string($row->draw_bank_no ?? ''),
                        'bank_name' => $this->string($row->draw_bank_class ?? ''),
                        'bank_addr' => $this->string($row->draw_bank_info ?? ''),
                        'status' => (int) ($row->apply_status ?? 0),
                        'local_order_no' => $this->string($row->orderId_LOC ?? '', 'LEGACY-WDR-' . (int) $row->record_id),
                        'third_order_no' => $this->string($row->orderId_OTC ?? ''),
                        'reject_reason' => $this->string($row->apply_remark ?? ''),
                        'mt4_return_status' => $this->string($row->mt4_return_status ?? ''),
                        'created_by' => $this->string($row->rec_crt_user ?? ''),
                        'updated_by' => $this->string($row->rec_upd_user ?? ''),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => null,
                    ];
                }
                $this->upsertRows('withdraw_records', $batch, ['id']);
                $count += count($batch);
            });

        $this->counts['withdraw_records'] = $count;
    }

    private function migrateTrades(): void
    {
        $count = 0;
        $this->legacy->table('user_trades')
            ->orderBy('trades_id')
            ->chunk(2000, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $openAt = $this->timestamp($row->open_time ?? null, time());
                    $closeTime = $this->tradeCloseTime($row->close_time ?? null);
                    $modifyAt = $this->timestamp($row->modify_time ?? null, $openAt);
                    $batch[] = [
                        'id' => (int) $row->trades_id,
                        'user_id' => (int) ($row->user_id ?? 0),
                        'ticket' => (int) ($row->ticket ?? 0),
                        'symbol' => $this->string($row->symbol ?? ''),
                        'digits' => (int) ($row->digits ?? 0),
                        'cmd' => (int) ($row->cmd ?? 0),
                        'volume' => (int) ($row->volume ?? 0),
                        'open_time' => date('Y-m-d H:i:s', $openAt),
                        'open_price' => $this->float($row->open_price ?? 0),
                        'stop_loss' => $this->float($row->stop_loss ?? 0),
                        'take_profit' => $this->float($row->take_profit ?? 0),
                        'close_time' => $closeTime,
                        'expiration' => $this->nullableDateTime($row->expiration ?? null),
                        'reason' => (int) ($row->reason ?? 0),
                        'conv_rate1' => $this->float($row->conv_rate1 ?? 0),
                        'conv_rate2' => $this->float($row->conv_rate2 ?? 0),
                        'commission' => $this->float($row->commission ?? 0),
                        'commission_agent' => $this->float($row->commission_agent ?? 0),
                        'swaps' => $this->float($row->swaps ?? 0),
                        'close_price' => $this->float($row->close_price ?? 0),
                        'profit' => $this->float($row->profit ?? 0),
                        'taxes' => $this->float($row->taxes ?? 0),
                        'comment' => $this->string($row->comment ?? ''),
                        'internal_id' => (int) ($row->internal_id ?? 0),
                        'margin_rate' => $this->float($row->margin_rate ?? 0),
                        'timestamp_val' => (int) ($row->timestamp ?? 0),
                        'magic' => (int) ($row->magic ?? 0),
                        'gw_volume' => (int) ($row->gw_volume ?? 0),
                        'gw_open_price' => (int) ($row->gw_open_price ?? 0),
                        'gw_close_price' => (int) ($row->gw_close_price ?? 0),
                        'modify_time' => date('Y-m-d H:i:s', $modifyAt),
                        'settlement_status' => $closeTime === '1970-01-01 00:00:00' ? 0 : 1,
                        'settled_at' => $this->nullableDateTime($row->rec_comp_date ?? null),
                        'created_at' => $openAt,
                        'updated_at' => $modifyAt,
                        'deleted_at' => $this->deletedAtFromVoided($row->voided ?? '1', $modifyAt),
                    ];
                }
                $this->upsertRows('user_trades', $batch, ['id']);
                $count += count($batch);
            });

        $this->counts['user_trades'] = $count;
    }

    private function migrateVouchers(): void
    {
        $count = 0;
        $this->legacy->table('voucher_info')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $createdAt = $this->timestamp($row->rec_crt_date ?? null, time());
                    $updatedAt = $this->timestamp($row->rec_upd_date ?? null, $createdAt);
                    $batch[] = [
                        'id' => (int) $row->id,
                        'user_id' => (int) ($row->user_id ?? 0),
                        'images' => $this->string($row->imgs ?? ''),
                        'remarks' => $this->string($row->remarks ?? ''),
                        'review_status' => (int) ($row->review_status ?? 0),
                        'review_message' => $this->string($row->review_msg ?? ''),
                        'created_by' => $this->string($row->rec_crt_user ?? ''),
                        'updated_by' => $this->string($row->rec_upd_user ?? ''),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => null,
                    ];
                }
                $this->upsertRows('voucher_infos', $batch, ['id']);
                $count += count($batch);
            });

        $this->counts['voucher_infos'] = $count;
    }

    private function migrateAddresses(): void
    {
        $count = 0;
        $this->legacy->table('user_addresses')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $createdAt = $this->timestamp($row->created_at ?? null, time());
                    $updatedAt = $this->timestamp($row->updated_at ?? null, $createdAt);
                    $batch[] = [
                        'id' => (int) $row->id,
                        'user_id' => (int) ($row->user_id ?? 0),
                        'recipient_name' => $this->string($row->recipient_name ?? ''),
                        'recipient_phone' => $this->string($row->recipient_phone ?? ''),
                        'recipient_address' => $this->string($row->recipient_address ?? ''),
                        'is_default' => (int) ($row->is_default ?? 0),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => $this->nullableTimestamp($row->deleted_at ?? null),
                    ];
                }
                $this->upsertRows('user_addresses', $batch, ['id']);
                $count += count($batch);
            });

        $this->counts['user_addresses'] = $count;
    }

    private function migrateGiftShipments(): void
    {
        $count = 0;
        $this->legacy->table('gift_shipments')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $createdAt = $this->timestamp($row->created_at ?? null, time());
                    $updatedAt = $this->timestamp($row->updated_at ?? null, $createdAt);
                    $batch[] = [
                        'id' => (int) $row->id,
                        'user_id' => (int) ($row->user_id ?? 0),
                        'address_id' => (int) ($row->address_id ?? 0),
                        'recipient_name' => $this->string($row->recipient_name ?? ''),
                        'recipient_phone' => $this->string($row->recipient_phone ?? ''),
                        'recipient_address' => $this->string($row->recipient_address ?? ''),
                        'sender_name' => $this->string($row->sender_name ?? ''),
                        'tracking_number' => $this->string($row->tracking_number ?? ''),
                        'gift_name' => $this->string($row->gift_name ?? ''),
                        'gift_quantity' => (int) ($row->gift_quantity ?? 0),
                        'status' => (int) ($row->status ?? 0),
                        'remark' => $this->string($row->remark ?? ''),
                        'admin_id' => (int) ($row->admin_id ?? 0),
                        'shipped_at' => $this->nullableDateTime($row->shipped_at ?? null),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => $this->nullableTimestamp($row->deleted_at ?? null),
                    ];
                }
                $this->upsertRows('gift_shipments', $batch, ['id']);
                $count += count($batch);
            });

        $this->counts['gift_shipments'] = $count;
    }

    private function migrateGroupChangeApplies(): void
    {
        $count = 0;
        $this->legacy->table('trans_apply_log')
            ->orderBy('trans_id')
            ->chunk(500, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $createdAt = $this->timestamp($row->rec_crt_date ?? null, time());
                    $updatedAt = $this->timestamp($row->rec_upd_date ?? null, $createdAt);
                    $batch[] = [
                        'id' => (int) $row->trans_id,
                        'user_id' => (int) ($row->trans_uid ?? 0),
                        'origin_group_id' => 0,
                        'group_id' => (int) ($row->trans_type_gid ?? 0),
                        'group_name' => $this->string($row->trans_type_name ?? ''),
                        'applicant_id' => (int) ($row->trans_apply_uid ?? 0),
                        'applicant_name' => $this->string($row->trans_apply_uname ?? ''),
                        'status' => (int) ($row->trans_apply_status ?? 0),
                        'apply_reason' => '',
                        'reject_reason' => $this->string($row->trans_apply_reason ?? ''),
                        'created_by' => $this->string($row->rec_crt_user ?? ''),
                        'updated_by' => $this->string($row->rec_upd_user ?? ''),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => $this->deletedAtFromVoided($row->voided ?? '1', $updatedAt),
                    ];
                }
                $this->upsertRows('trans_apply_logs', $batch, ['id']);
                $count += count($batch);
            });

        $this->counts['trans_apply_logs'] = $count;
    }

    private function migrateNewsLangs(): void
    {
        $count = 0;
        $this->legacy->table('newslist')
            ->where('voided', '1')
            ->orderBy('news_id')
            ->chunk(100, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $newsId = (int) $row->news_id;
                    $createdAt = $this->timestamp($row->rec_crt_date ?? null, time());
                    $updatedAt = $this->timestamp($row->rec_upd_date ?? null, $createdAt);
                    $title = $this->string($row->news_title ?? '');
                    $content = $this->string($row->news_content ?? '');

                    DB::table('news')->updateOrInsert(
                        ['id' => $newsId],
                        [
                            'title' => $title,
                            'content' => $content,
                            'image' => null,
                            'author_id' => 0,
                            'author_name' => $this->string($row->news_user ?? $row->rec_crt_user ?? ''),
                            'is_published' => 1,
                            'created_at' => $createdAt,
                            'updated_at' => $updatedAt,
                            'deleted_at' => null,
                        ]
                    );

                    foreach (['zh-CN', 'zh_CN', 'en'] as $locale) {
                        DB::table('news_langs')->updateOrInsert(
                            ['news_id' => $newsId, 'lang_code' => $locale],
                            [
                                'title' => $title,
                                'content' => $content,
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                                'deleted_at' => null,
                            ]
                        );
                    }
                    $count++;
                }
            });

        $this->counts['news_langs_from_news'] = $count;
    }

    private function updateIdSequences(): void
    {
        $now = time();
        $maxAgentId = (int) DB::table('user_infos')->where('account_type', 1)->max('user_id');
        $maxCustomerId = (int) DB::table('user_infos')->where('account_type', 2)->max('user_id');

        DB::table('id_sequences')->updateOrInsert(
            ['type' => 'agent'],
            ['current_value' => $maxAgentId, 'prefix' => '', 'step' => 1, 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('id_sequences')->updateOrInsert(
            ['type' => 'customer'],
            ['current_value' => $maxCustomerId, 'prefix' => '', 'step' => 1, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    private function restoreFrontTestAgent(): void
    {
        $now = time();
        $userId = 1001;

        $login = DB::table('user_logins')->where('email', 'agent@test.com')->first();
        if (!$login) {
            $login = DB::table('user_logins')->where('user_id', $userId)->orderBy('id')->first();
        }

        $loginPayload = [
            'user_id' => $userId,
            'email' => 'agent@test.com',
            'password' => Hash::make('agent123'),
            'account_type' => 1,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($login) {
            DB::table('user_logins')->where('id', $login->id)->update($loginPayload);
            $loginId = (int) $login->id;
        } else {
            $loginId = (int) DB::table('user_logins')->insertGetId(array_merge($loginPayload, [
                'last_login_ip' => '',
                'last_login_at' => null,
                'created_at' => $now,
            ]));
        }

        $levelId = (int) ($this->levelMap[1] ?? DB::table('agent_levels')->value('id') ?? 0);
        $groupId = (int) (
            DB::table('group_configs')->where('category', 1)->where('is_default', 1)->value('id')
            ?: DB::table('group_configs')->where('category', 1)->value('id')
            ?: 0
        );

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $loginId,
                'user_name' => 'Demo Root Agent',
                'phone' => '',
                'gender' => 1,
                'avatar' => null,
                'level_id' => $levelId,
                'group_id' => $groupId,
                'parent_id' => 0,
                'account_type' => 1,
                'family_tree' => (string) $userId,
                'total_funds' => 0,
                'used_margin' => 0,
                'avail_margin' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'risk_ratio' => 0,
                'margin_amount' => 0,
                'leverage' => 0,
                'cust_vol' => '0',
                'pay_provider_id' => 0,
                'equity_ratio' => 0,
                'comm_rate' => 0,
                'is_ecn' => 0,
                'follow_parent_ecn' => 0,
                'auth_status' => 1,
                'is_mt4_synced' => 1,
                'is_mt4_enabled' => 1,
                'is_mt4_readonly' => 0,
                'is_withdrawal_allowed' => 0,
                'is_deposit_allowed' => 0,
                'is_agent_confirmed' => 1,
                'original_group' => '',
                'mt4_group' => 'demo-agent',
                'mt4_code' => 0,
                'trading_mode' => 0,
                'settle_method' => 1,
                'settle_cycle' => 1,
                'country' => '',
                'city' => '',
                'state' => '',
                'address' => '',
                'is_gift_allowed' => 1,
                'data_source' => 0,
                'remark' => 'Permanent front test agent login',
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
            ]
        );

        DB::table('user_auths')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bank_no' => '',
                'bank_no_tmp' => '',
                'bank_name' => '',
                'bank_name_tmp' => '',
                'bank_card_img' => '',
                'bank_card_img_tmp' => '',
                'bank_addr' => '',
                'bank_addr_tmp' => '',
                'bank_status' => 0,
                'bank_remarks' => '',
                'id_card_no' => '',
                'id_card_status' => 0,
                'id_card_front' => '',
                'id_card_back' => '',
                'id_card_remarks' => '',
                'is_bank_synced' => 0,
                'created_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
            ]
        );

        $this->counts['front_test_agent_restored'] = 1;
    }

    private function upsertRows(string $table, array $rows, array $uniqueBy): void
    {
        if (!$rows) {
            return;
        }

        $updateColumns = array_values(array_diff(array_keys($rows[0]), $uniqueBy));
        DB::table($table)->upsert($rows, $uniqueBy, $updateColumns);
    }

    private function uniqueEmail($email, int $userId): string
    {
        $email = strtolower(trim((string) $email));
        $emailKey = $email;
        $existingUserId = $this->usedEmails[$emailKey] ?? null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($this->emailCounts[$emailKey] ?? 0) > 1
            || ($existingUserId !== null && (int) $existingUserId !== $userId)
        ) {
            $email = 'legacy' . $userId . '@legacy.local';
            $emailKey = $email;
        }

        $this->usedEmails[$emailKey] = $userId;

        return $email;
    }

    private function passwordHash($value): string
    {
        $value = trim((string) $value);
        if ($value !== '' && (strpos($value, '$2y$') === 0 || strpos($value, '$2a$') === 0 || strpos($value, '$argon') === 0)) {
            return $value;
        }

        return Hash::make($value !== '' ? $value : '123456');
    }

    private function groupId(int $legacyGroupId, int $accountType): int
    {
        if (isset($this->groupMap[$legacyGroupId])) {
            return $this->groupMap[$legacyGroupId];
        }

        $category = $accountType === 1 ? 1 : 2;
        $fallback = DB::table('group_configs')->where('category', $category)->where('is_default', 1)->value('id');

        return (int) ($fallback ?: DB::table('group_configs')->where('category', $category)->value('id'));
    }

    private function levelId(int $accountType): int
    {
        if ($accountType !== 1) {
            return 0;
        }

        return (int) ($this->levelMap[5] ?? $this->levelMap[1] ?? DB::table('agent_levels')->value('id'));
    }

    private function relationDepth(int $agentId, int $descendantId): int
    {
        $tree = array_values(array_filter(array_map('intval', explode(',', $this->familyTrees[$descendantId] ?? ''))));
        $agentIndex = array_search($agentId, $tree, true);
        $descendantIndex = array_search($descendantId, $tree, true);

        if ($agentIndex !== false && $descendantIndex !== false && $descendantIndex > $agentIndex) {
            return $descendantIndex - $agentIndex;
        }

        return (($this->parentMap[$descendantId] ?? 0) === $agentId) ? 1 : 2;
    }

    private function depositUserId($body): int
    {
        if (preg_match('/(\d+)/', (string) $body, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function gender($value): int
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['2', 'f', 'female', '女'], true)) {
            return 2;
        }

        return 1;
    }

    private function float($value): float
    {
        return round((float) $value, 2);
    }

    private function string($value, string $fallback = ''): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? $fallback : $value;
    }

    private function timestamp($value, int $fallback): int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return $fallback;
        }

        $timestamp = strtotime($value);

        return $timestamp ?: $fallback;
    }

    private function nullableTimestamp($value): ?int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp ?: null;
    }

    private function nullableDateTime($value): ?string
    {
        $timestamp = $this->nullableTimestamp($value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function tradeCloseTime($value): string
    {
        $timestamp = $this->nullableTimestamp($value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '1970-01-01 00:00:00';
    }

    private function deletedAtFromVoided($voided, int $fallback): ?int
    {
        return (string) $voided === '1' ? null : $fallback;
    }
}
