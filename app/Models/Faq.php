<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'faqs';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'category',
        'question',
        'answer',
        'order',
        'helpful_count',
        'not_helpful_count',
        'is_active',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'order' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 分类常量
     */
    const CATEGORY_ACCOUNT = 'account';
    const CATEGORY_TRAINING = 'training';
    const CATEGORY_MEMBERSHIP = 'membership';
    const CATEGORY_TECHNICAL = 'technical';

    /**
     * 获取分类标签
     */
    public static function getCategoryLabels(): array
    {
        return [
            self::CATEGORY_ACCOUNT => '账号相关',
            self::CATEGORY_TRAINING => '训练相关',
            self::CATEGORY_MEMBERSHIP => '会员相关',
            self::CATEGORY_TECHNICAL => '技术问题',
        ];
    }

    /**
     * 获取分类标签属性
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::getCategoryLabels()[$this->category] ?? $this->category;
    }

    /**
     * 作用域：启用的
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 作用域：按分类筛选
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * 增加有帮助数
     */
    public function incrementHelpful(): void
    {
        $this->increment('helpful_count');
    }

    /**
     * 增加无帮助数
     */
    public function incrementNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }
}
