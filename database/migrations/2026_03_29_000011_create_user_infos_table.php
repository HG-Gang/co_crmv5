<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_infos', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->bigInteger('user_id')->comment('用户ID | User ID');
            $blueprint->integer('login_id')->comment('登录ID | Login ID');
            $blueprint->string('user_name', 200)->default('')->comment('用户名 | User name');
            $blueprint->string('phone', 50)->default('')->comment('电话 | Phone');
            $blueprint->tinyInteger('gender')->default(1)->comment('性别 | Gender');
            $blueprint->string('avatar', 500)->nullable()->comment('头像 | Avatar');
            $blueprint->integer('level_id')->default(0)->comment('级别ID | Level ID');
            $blueprint->integer('group_id')->default(0)->comment('分组ID | Group ID');
            $blueprint->integer('parent_id')->default(0)->comment('父ID | Parent ID');
            $blueprint->tinyInteger('account_type')->default(1)->comment('账户类型 | Account type');
            $blueprint->string('family_tree', 1000)->default('')->comment('家谱树: 逗号分隔祖先链 | Family tree: comma-separated ancestor chain');
            $blueprint->double('total_funds', 50, 2)->default(0)->comment('总资金 | Total funds');
            $blueprint->double('used_margin', 50, 2)->default(0)->comment('已用保证金 | Used margin');
            $blueprint->double('avail_margin', 50, 2)->default(0)->comment('可用保证金 | Available margin');
            $blueprint->double('equity', 50, 2)->default(0)->comment('净值 | Equity');
            $blueprint->double('effective_credit', 50, 2)->default(0)->comment('有效信用额 | Effective credit');
            $blueprint->double('risk_ratio', 50, 2)->default(0)->comment('风险率 | Risk ratio');
            $blueprint->double('margin_amount', 50, 2)->default(0)->comment('保证金金额 | Margin amount');
            $blueprint->integer('leverage')->default(0)->comment('杠杆 | Leverage');
            $blueprint->string('cust_vol', 255)->default('0')->comment('客户交易量 | Customer volume');
            $blueprint->integer('pay_provider_id')->default(0)->comment('支付提供商ID | Payment provider ID');
            $blueprint->integer('equity_ratio')->default(0)->comment('净值比例 | Equity ratio');
            $blueprint->integer('comm_rate')->default(0)->comment('佣金率 | Commission rate');
            $blueprint->tinyInteger('is_ecn')->default(0)->comment('是否ECN | Is ECN');
            $blueprint->tinyInteger('follow_parent_ecn')->default(0)->comment('跟随父级ECN | Follow parent ECN');
            $blueprint->tinyInteger('auth_status')->default(0)->comment('认证状态: 0=未验证 1=已验证 2=已退回 3=已禁用 | Auth status: 0=unverified 1=verified 2=returned 3=disabled');
            $blueprint->tinyInteger('is_mt4_synced')->default(0)->comment('是否同步MT4 | Is MT4 synced');
            $blueprint->tinyInteger('is_mt4_enabled')->default(1)->comment('MT4是否启用 | Is MT4 enabled');
            $blueprint->tinyInteger('is_mt4_readonly')->default(0)->comment('MT4是否只读 | Is MT4 readonly');
            $blueprint->tinyInteger('is_withdrawal_allowed')->default(0)->comment('允许提现: 0=是 1=否 | Withdrawal allowed: 0=yes 1=no');
            $blueprint->tinyInteger('is_deposit_allowed')->default(0)->comment('允许充值: 0=是 1=否 | Deposit allowed: 0=yes 1=no');
            $blueprint->tinyInteger('is_agent_confirmed')->default(0)->comment('代理确认 | Agent confirmed');
            $blueprint->string('original_group', 255)->default('')->comment('原分组 | Original group');
            $blueprint->string('mt4_group', 255)->default('')->comment('MT4分组 | MT4 group');
            $blueprint->integer('mt4_code')->default(0)->comment('MT4代码 | MT4 code');
            $blueprint->tinyInteger('trading_mode')->default(0)->comment('交易模式: 0=佣金 1=净值 | Trading mode: 0=commission 1=equity');
            $blueprint->tinyInteger('settle_method')->default(1)->comment('结算方式: 1=线上 2=线下 | Settle method: 1=online 2=offline');
            $blueprint->tinyInteger('settle_cycle')->default(0)->comment('结算周期: 1=每周 2=每两周 3=每月 | Settle cycle: 1=weekly 2=biweekly 3=monthly');
            $blueprint->string('country', 255)->default('')->comment('国家 | Country');
            $blueprint->string('city', 255)->default('')->comment('城市 | City');
            $blueprint->string('state', 255)->default('')->comment('州/省 | State');
            $blueprint->string('address', 500)->nullable()->comment('地址 | Address');
            $blueprint->tinyInteger('is_gift_allowed')->default(0)->comment('允许礼品 | Gift allowed');
            $blueprint->tinyInteger('data_source')->default(0)->comment('数据来源 | Data source');
            $blueprint->string('remark', 500)->default('')->comment('备注 | Remark');
            $blueprint->integer('created_by')->default(0)->comment('创建人 | Created by');
            $blueprint->integer('updated_by')->default(0)->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->unique('user_id');
            $blueprint->index('login_id');
            $blueprint->index('parent_id');
            $blueprint->index('account_type');
            // 注释掉family_tree索引，因为字段长度太长会导致索引错误
            // $blueprint->index('family_tree', 'idx_family_tree');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_infos');
    }
}
