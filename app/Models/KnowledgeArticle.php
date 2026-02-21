<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeArticle extends Model
{
    protected $fillable = [
        'title', 'summary', 'content', 'category_id',
        'source_book', 'source_chapter', 'source_page',
        'tags', 'status', 'difficulty', 'view_count',
    ];

    protected $casts = [
        'tags' => 'array',
        'view_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(KnowledgeReference::class, 'article_id');
    }

    /**
     * 按分类和标签筛选已发布文章
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
