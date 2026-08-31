<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:48
 */

namespace App\Models;

/**
 * 菜单模型。
 *
 * 文件功能：
 * - menus 表保存前后台 Blade 页面可见的动态菜单配置，用于渲染后台 Layui/Naive 风格菜单和前台用户菜单入口。
 * - title 表示中文菜单标题，title_en 表示英文菜单标题，后端多语言渲染时通过当前 locale 选择展示文案。
 * - icon 表示菜单图标名称，path 表示 Blade 页面访问路径，component 表示兼容前端组件标识。
 * - parent_id 表示父级菜单 ID，0 表示顶级菜单；children() 会按 sort 递归加载子菜单。
 * - permission_id 表示绑定的 permissions.id，用于把菜单展示和数据库权限配置关联起来。
 * - guard_type 表示菜单所属端：admin=后台管理员菜单，front=前台代理商或客户菜单。
 * - type 表示菜单节点类型，is_visible 表示是否在界面显示，is_external 表示是否外链。
 * - sort 表示菜单排序值，status 表示启用状态，禁用菜单不应进入可见菜单树。
 */
class Menu extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 menus。
     */
    protected $table = 'menus';
    
    /**
     * 允许批量写入的菜单字段。
     *
     * @var array<int, string> $fillable 表示创建或更新菜单时允许写入的字段白名单。
     */
    protected $fillable = [
        'title', 'title_en', 'icon', 'path', 'component', 'parent_id',
        'permission_id', 'guard_type', 'type', 'is_visible', 'is_external',
        'sort', 'status'
    ];
    
    /**
     * 关联当前菜单的父级菜单。
     *
     * 参数逻辑说明：
     * - 外键 parent_id 来自 menus.parent_id，表示当前菜单挂载在哪个父菜单下。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回父级 Menu 关系。
     */
    public function parent() 
    { 
        return $this->belongsTo(Menu::class, 'parent_id'); 
    }
    
    /**
     * 关联当前菜单的子菜单集合。
     *
     * 参数逻辑说明：
     * - 外键 parent_id 指向当前菜单 id，并按 sort 升序排列，保证后台侧边栏顺序稳定。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 返回递归加载后的子菜单集合。
     */
    public function children() 
    { 
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort')->with('children'); 
    }
    
    /**
     * 关联菜单绑定的权限配置。
     *
     * 参数逻辑说明：
     * - permission_id 对应 permissions.id，表示该菜单入口由哪一条权限记录控制。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回菜单绑定的 Permission 权限关系。
     */
    public function permission() 
    { 
        return $this->belongsTo(Permission::class, 'permission_id'); 
    }
    
    /**
     * 限定后台管理员菜单。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 菜单查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 guard_type=admin 条件的查询构造器。
     */
    public function scopeAdmin($query) 
    { 
        return $query->where('guard_type', 'admin'); 
    }
    
    /**
     * 限定前台代理商或客户菜单。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 菜单查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 guard_type=front 条件的查询构造器。
     */
    public function scopeFront($query) 
    { 
        return $query->where('guard_type', 'front'); 
    }
    
    /**
     * 限定界面可见菜单。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 菜单查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 is_visible=1 条件的查询构造器。
     */
    public function scopeVisible($query) 
    { 
        return $query->where('is_visible', 1); 
    }
    
    /**
     * 限定启用菜单。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 菜单查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 status=1 条件的查询构造器。
     */
    public function scopeActive($query) 
    { 
        return $query->where('status', 1); 
    }
    
    /**
     * 限定顶级根菜单。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 菜单查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 parent_id=0 条件的查询构造器。
     */
    public function scopeRoot($query) 
    { 
        return $query->where('parent_id', 0); 
    }
    
    /**
     * 获取本地化菜单标题。
     *
     * getLocalizedTitleAttribute() 按当前 locale 返回中文或英文菜单标题；英文标题为空时回退中文标题，避免菜单出现空白。
     *
     * @return string 当前语言下可展示的菜单标题。
     */
    public function getLocalizedTitleAttribute() 
    {
        return app()->getLocale() === 'en' ? ($this->title_en ?: $this->title) : $this->title;
    }
}
