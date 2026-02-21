<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'parent_id', 'icon', 'sort_order', 'description',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(KnowledgeCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class, 'category_id');
    }

    /**
     * 获取完整分类树（嵌套结构）
     */
    public static function tree(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereNull('parent_id')
            ->with(['children.children'])
            ->orderBy('sort_order')
            ->get();
    }
}
