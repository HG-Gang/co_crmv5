<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 权限表 | Permissions Table
 */
return new class extends Migration {
    public function up() {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('名称 | Name');
            $table->string('slug', 150)->unique()->comment('标识符 | Slug');
            $table->string('guard_type', 20)->default('admin')->comment('守卫类型: admin/front | Guard type');
            $table->integer('parent_id')->default(0)->comment('父ID | Parent ID');
            $table->tinyInteger('type')->default(1)->comment('类型: 1=菜单 2=页面 3=按钮 | Type: 1=menu 2=page 3=button');
            $table->string('icon', 100)->nullable()->comment('图标 | Icon');
            $table->integer('sort')->default(0)->comment('排序 | Sort');
            $table->string('route', 200)->nullable()->comment('前端路由路径 | Frontend route path');
            $table->string('api_route', 200)->nullable()->comment('后端API路由名称 | Backend API route name');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用 1=启用 | Status');
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('guard_type');
            $table->index('parent_id');
        });
    }
    public function down() { Schema::dropIfExists('permissions'); }
};
