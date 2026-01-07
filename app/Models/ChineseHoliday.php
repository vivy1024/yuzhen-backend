<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ChineseHoliday Model - 中国节假日模型
 * 
 * 用于节假日训练建议
 * 
 * @property int $id
 * @property string $holiday_name
 * @property string $start_date
 * @property string $end_date
 * @property string $holiday_type
 * @property int $year
 * @property bool $is_workday
 * @property string $training_suggestion
 * 
 * @version 1.0.0
 * @date 2025-12-26
 */
class ChineseHoliday extends Model
{
    use HasFactory;

    protected $table = 'chinese_holidays';

    protected $fillable = [
        'holiday_name',
        'start_date',
        'end_date',
        'holiday_type',
        'year',
        'is_workday',
        'training_suggestion',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'year' => 'integer',
        'is_workday' => 'boolean',
    ];

    /**
     * 节假日类型常量
     */
    const TYPE_SPRING_FESTIVAL = 'spring_festival';
    const TYPE_NATIONAL_DAY = 'national_day';
    const TYPE_LABOR_DAY = 'labor_day';
    const TYPE_MID_AUTUMN = 'mid_autumn';
    const TYPE_DRAGON_BOAT = 'dragon_boat';
    const TYPE_QINGMING = 'qingming';
    const TYPE_NEW_YEAR = 'new_year';
    const TYPE_OTHER = 'other';

    /**
     * 检查指定日期是否为节假日
     * 
     * @param string $date 日期（Y-m-d格式）
     * @return ChineseHoliday|null
     */
    public static function isHoliday(string $date): ?self
    {
        return self::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('is_workday', false)
            ->first();
    }

    /**
     * 检查指定日期是否为调休工作日
     * 
     * @param string $date 日期（Y-m-d格式）
     * @return bool
     */
    public static function isWorkday(string $date): bool
    {
        return self::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('is_workday', true)
            ->exists();
    }

    /**
     * 获取指定年份的所有节假日
     * 
     * @param int $year 年份
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByYear(int $year)
    {
        return self::where('year', $year)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * 获取即将到来的节假日
     * 
     * @param int $days 未来天数
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUpcoming(int $days = 30)
    {
        $today = now()->toDateString();
        $futureDate = now()->addDays($days)->toDateString();

        return self::where('start_date', '>=', $today)
            ->where('start_date', '<=', $futureDate)
            ->where('is_workday', false)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * 作用域：按年份筛选
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * 作用域：按类型筛选
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('holiday_type', $type);
    }
}
