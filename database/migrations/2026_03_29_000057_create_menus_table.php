<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 菜单表 | Menus Table
 * 支持无限级别菜单，与权限绑定 | Supports infinite-level menus, bound to permissions
 */
return new class extends Migration {
    public function up() {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->comment('菜单标题 | Menu title');
            $table->string('title_en', 100)->default('')->comment('菜单英文标题 | Menu English title');
            $table->string('icon', 100)->default('')->comment('图标 | Icon class');
            $table->string('path', 200)->default('')->comment('前端路由路径 | Frontend route path');
            $table->string('component', 200)->default('')->comment('前端组件路径 | Frontend component path');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级菜单ID, 0=顶级 | Parent menu ID, 0=root');
            $table->unsignedBigInteger('permission_id')->nullable()->comment('绑定权限ID | Bound permission ID');
            $table->string('guard_type', 20)->default('admin')->comment('守卫类型: admin/front | Guard type');
            $table->tinyInteger('type')->default(1)->comment('类型: 1=目录 2=菜单 3=按钮 | Type: 1=dir 2=menu 3=button');
            $table->tinyInteger('is_visible')->default(1)->comment('是否可见: 0=隐藏 1=显示 | Visible: 0=hidden 1=shown');
            $table->tinyInteger('is_external')->default(0)->comment('是否外链: 0=否 1=是 | External link: 0=no 1=yes');
            $table->integer('sort')->default(0)->comment('排序值越小越前 | Sort order, smaller=first');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用 1=启用 | Status: 0=disabled 1=enabled');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('parent_id');
            $table->index('guard_type');
            $table->index(['guard_type', 'status']);
        });
    }
    public function down() { Schema::dropIfExists('menus'); }
};
