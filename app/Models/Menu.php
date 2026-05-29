<?php

namespace App\Models;

/**
 * 菜单模型 | Menu Model
 * 
 * 管理后台及前台的动态导航菜单。
 * Manages dynamic navigation menus for both backend and frontend.
 */
class Menu extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'menus';
    
    /**
     * 可批量赋值的字段 | Fillable attributes
     * @var array
     */
    protected $fillable = [
        'title', 'title_en', 'icon', 'path', 'component', 'parent_id',
        'permission_id', 'guard_type', 'type', 'is_visible', 'is_external',
        'sort', 'status'
    ];
    
    /**
     * 关联父菜单 | Relation: Parent Menu
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent() 
    { 
        return $this->belongsTo(Menu::class, 'parent_id'); 
    }
    
    /**
     * 关联子菜单 | Relation: Children Menus
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children() 
    { 
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort')->with('children'); 
    }
    
    /**
     * 关联权限 | Relation: Bound permission
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function permission() 
    { 
        return $this->belongsTo(Permission::class, 'permission_id'); 
    }
    
    /**
     * 作用域：后台菜单 | Scope: Admin menus
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdmin($query) 
    { 
        return $query->where('guard_type', 'admin'); 
    }
    
    /**
     * 作用域：前台菜单 | Scope: Front menus
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFront($query) 
    { 
        return $query->where('guard_type', 'front'); 
    }
    
    /**
     * 作用域：可见菜单 | Scope: Visible menus
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVisible($query) 
    { 
        return $query->where('is_visible', 1); 
    }
    
    /**
     * 作用域：启用菜单 | Scope: Active menus
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query) 
    { 
        return $query->where('status', 1); 
    }
    
    /**
     * 作用域：根菜单 | Scope: Root menus
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRoot($query) 
    { 
        return $query->where('parent_id', 0); 
    }
    
    /**
     * 获取本地化标题 | Accessor: Get localized title
     * 
     * @return string
     */
    public function getLocalizedTitleAttribute() 
    {
        return app()->getLocale() === 'en' ? ($this->title_en ?: $this->title) : $this->title;
    }
}
