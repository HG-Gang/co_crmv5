<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为当前交易组配置增加旧项目主键身份字段。
 *
 * 文件功能：
 * - legacy_group_id 保存旧库 group_config.id，用于迁移 user_infos.group_id。
 * - pair_id 继续只保存当前 group_configs.id，作为当前表自关联配对键。
 * - 唯一索引保证同一条旧交易组只能映射到一条当前记录。
 *
 * 失败场景：
 * - 如果历史数据已经存在重复 legacy_group_id，唯一索引会让迁移明确失败，禁止静默覆盖映射。
 */
class AddLegacyGroupIdToGroupConfigs extends Migration
{
    /**
     * 增加可空旧主键字段及唯一索引。
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('group_configs', 'legacy_group_id')) {
            return;
        }

        Schema::table('group_configs', function (Blueprint $blueprint) {
            $blueprint->unsignedInteger('legacy_group_id')
                ->nullable()
                ->after('id')
                ->comment('旧 group_config.id；用于旧用户组身份映射 | Legacy group identity')
                ->unique('group_configs_legacy_group_id_unique');
        });
    }

    /**
     * 回滚本迁移新增的索引和字段，不删除任何交易组记录。
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumn('group_configs', 'legacy_group_id')) {
            return;
        }

        Schema::table('group_configs', function (Blueprint $blueprint) {
            $blueprint->dropUnique('group_configs_legacy_group_id_unique');
            $blueprint->dropColumn('legacy_group_id');
        });
    }
}
