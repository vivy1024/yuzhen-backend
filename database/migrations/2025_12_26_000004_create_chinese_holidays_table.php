<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 chinese_holidays 表 - 中国节假日配置表
 * 
 * 用于节假日训练建议：
 * - 春节期间推荐轻量训练
 * - 国庆期间可安排集中训练
 * - 调休日提醒用户注意休息
 * 
 * @version 1.0.0
 * @date 2025-12-26
 * @requirements 中国本地化
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chinese_holidays', function (Blueprint $table) {
            $table->id();
            
            // 节假日名称
            $table->string('holiday_name', 50)
                ->comment('节假日名称');
            
            // 开始日期
            $table->date('start_date')
                ->comment('开始日期');
            
            // 结束日期
            $table->date('end_date')
                ->comment('结束日期');
            
            // 节假日类型
            $table->enum('holiday_type', [
                'spring_festival',  // 春节
                'national_day',     // 国庆节
                'labor_day',        // 劳动节
                'mid_autumn',       // 中秋节
                'dragon_boat',      // 端午节
                'qingming',         // 清明节
                'new_year',         // 元旦
                'other'             // 其他
            ])->comment('节假日类型');
            
            // 年份
            $table->integer('year')
                ->comment('年份');
            
            // 是否为调休工作日
            $table->boolean('is_workday')
                ->default(false)
                ->comment('是否为调休工作日');
            
            // 训练建议
            $table->string('training_suggestion', 255)
                ->nullable()
                ->comment('训练建议');
            
            $table->timestamps();
            
            // 索引
            $table->index('year', 'idx_year');
            $table->index(['start_date', 'end_date'], 'idx_dates');
            $table->index('holiday_type', 'idx_type');
        });
        
        // 插入2025年和2026年的主要节假日数据
        $this->seedHolidays();
    }

    public function down(): void
    {
        Schema::dropIfExists('chinese_holidays');
    }
    
    /**
     * 插入节假日初始数据
     */
    private function seedHolidays(): void
    {
        $holidays = [
            // 2025年节假日
            [
                'holiday_name' => '元旦',
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-01',
                'holiday_type' => 'new_year',
                'year' => 2025,
                'is_workday' => false,
                'training_suggestion' => '新年第一天，可以安排轻量恢复训练'
            ],
            [
                'holiday_name' => '春节',
                'start_date' => '2025-01-28',
                'end_date' => '2025-02-04',
                'holiday_type' => 'spring_festival',
                'year' => 2025,
                'is_workday' => false,
                'training_suggestion' => '春节期间建议轻量训练，注意饮食控制'
            ],
            [
                'holiday_name' => '清明节',
                'start_date' => '2025-04-04',
                'end_date' => '2025-04-06',
                'holiday_type' => 'qingming',
                'year' => 2025,
                'is_workday' => false,
                'training_suggestion' => '清明假期，可以安排户外有氧训练'
            ],
            [
                'holiday_name' => '劳动节',
                'start_date' => '2025-05-01',
                'end_date' => '2025-05-05',
                'holiday_type' => 'labor_day',
                'year' => 2025,
                'is_workday' => false,
                'training_suggestion' => '五一假期，适合集中训练'
            ],
            [
                'holiday_name' => '端午节',
                'start_date' => '2025-05-31',
                'end_date' => '2025-06-02',
                'holiday_type' => 'dragon_boat',
                'year' => 2025,
                'is_workday' => false,
                'training_suggestion' => '端午假期，注意粽子热量摄入'
            ],
            [
                'holiday_name' => '中秋节',
                'start_date' => '2025-10-06',
                'end_date' => '2025-10-06',
                'holiday_type' => 'mid_autumn',
                'year' => 2025,
                'is_workday' => false,
                'training_suggestion' => '中秋节，注意月饼热量'
            ],
            [
                'holiday_name' => '国庆节',
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-07',
                'holiday_type' => 'national_day',
                'year' => 2025,
                'is_workday' => false,
                'training_suggestion' => '国庆长假，适合系统性训练'
            ],
            // 2026年节假日
            [
                'holiday_name' => '元旦',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-03',
                'holiday_type' => 'new_year',
                'year' => 2026,
                'is_workday' => false,
                'training_suggestion' => '新年假期，制定年度训练计划'
            ],
            [
                'holiday_name' => '春节',
                'start_date' => '2026-02-17',
                'end_date' => '2026-02-23',
                'holiday_type' => 'spring_festival',
                'year' => 2026,
                'is_workday' => false,
                'training_suggestion' => '春节期间建议轻量训练，注意饮食控制'
            ],
        ];
        
        foreach ($holidays as $holiday) {
            \DB::table('chinese_holidays')->insert(array_merge($holiday, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
};
