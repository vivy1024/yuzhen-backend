<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeReference extends Model
{
    protected $fillable = [
        'article_id', 'ref_type', 'title', 'authors',
        'year', 'doi', 'chapter', 'page_range', 'isbn',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'article_id');
    }

    /**
     * 格式化引用文本（APA风格简化版）
     */
    public function formatCitation(): string
    {
        $parts = [];
        if ($this->authors) {
            $parts[] = $this->authors;
        }
        if ($this->year) {
            $parts[] = "({$this->year})";
        }
        $parts[] = $this->title;
        if ($this->chapter) {
            $parts[] = "Ch.{$this->chapter}";
        }
        if ($this->page_range) {
            $parts[] = "pp.{$this->page_range}";
        }
        return implode('. ', $parts);
    }
}
