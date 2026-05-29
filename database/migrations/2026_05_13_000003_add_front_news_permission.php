<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFrontNewsPermission extends Migration
{
    /**
     * Add front news menu permission.
     * 旧项目前台有 news_list 模块；这里补齐前台菜单权限，使登录后的动态菜单可以展示新闻公告入口。
     *
     * @return void
     */
    public function up()
    {
        if (!DB::table('permissions')->where('slug', 'front_news')->exists()) {
            DB::table('permissions')->insert([
                'name' => '新闻公告',
                'slug' => 'front_news',
                'guard_type' => 'front',
                'parent_id' => 0,
                'type' => 1,
                'icon' => 'layui-icon-notice',
                'sort' => 90,
                'route' => '/front/news',
                'api_route' => 'front_api_newsList',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Remove seeded front news menu permission.
     * 回滚时只删除本迁移创建的 front_news 菜单，不影响其他权限配置。
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->where('slug', 'front_news')->delete();
    }
}
