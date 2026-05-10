<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlanTemplate;

/**
 * 预置 12 个官方训练计划模板
 * 增肌/减脂/力量/塑形 × 初/中/高
 */
class PlanTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // 增肌
            ['name' => '初学者增肌入门', 'goal' => 'hypertrophy', 'level' => 'beginner', 'weeks' => 4, 'freq' => 3, 'desc' => '全身训练，每次3个复合动作，适合零基础', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 1],
                ['name' => '杠铃划船', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 1],
                ['name' => '哑铃肩推', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 3],
                ['name' => '腿举', 'sets' => 3, 'reps' => '12-15', 'day_of_week' => 3],
                ['name' => '高位下拉', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 3],
                ['name' => '罗马尼亚硬拉', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 5],
                ['name' => '上斜哑铃卧推', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 5],
                ['name' => '坐姿划船', 'sets' => 3, 'reps' => '10-12', 'day_of_week' => 5],
            ]],
            ['name' => '中级增肌分化', 'goal' => 'hypertrophy', 'level' => 'intermediate', 'weeks' => 8, 'freq' => 4, 'desc' => '推拉腿分化，每个肌群每星期训练2次', 'exercises' => [
                ['name' => '杠铃卧推', 'sets' => 4, 'reps' => '8-10', 'day_of_week' => 1],
                ['name' => '哑铃飞鸟', 'sets' => 3, 'reps' => '12', 'day_of_week' => 1],
                ['name' => '杠铃划船', 'sets' => 4, 'reps' => '8-10', 'day_of_week' => 2],
                ['name' => '引体向上', 'sets' => 3, 'reps' => '8-12', 'day_of_week' => 2],
                ['name' => '杠铃深蹲', 'sets' => 4, 'reps' => '8-10', 'day_of_week' => 4],
                ['name' => '腿弯举', 'sets' => 3, 'reps' => '12', 'day_of_week' => 4],
                ['name' => '哑铃肩推', 'sets' => 4, 'reps' => '8-10', 'day_of_week' => 5],
                ['name' => '侧平举', 'sets' => 3, 'reps' => '15', 'day_of_week' => 5],
            ]],
            ['name' => '高级增肌强化', 'goal' => 'hypertrophy', 'level' => 'advanced', 'weeks' => 12, 'freq' => 5, 'desc' => '5天分化训练，高容量高强度', 'exercises' => [
                ['name' => '杠铃卧推', 'sets' => 5, 'reps' => '6-8', 'day_of_week' => 1],
                ['name' => '上斜哑铃卧推', 'sets' => 4, 'reps' => '8-10', 'day_of_week' => 1],
                ['name' => '硬拉', 'sets' => 5, 'reps' => '5', 'day_of_week' => 2],
                ['name' => '引体向上', 'sets' => 4, 'reps' => '8-12', 'day_of_week' => 2],
                ['name' => '杠铃深蹲', 'sets' => 5, 'reps' => '6-8', 'day_of_week' => 3],
                ['name' => '哑铃肩推', 'sets' => 4, 'reps' => '8-10', 'day_of_week' => 4],
                ['name' => '杠铃弯举', 'sets' => 4, 'reps' => '10-12', 'day_of_week' => 5],
                ['name' => '三头绳索下压', 'sets' => 4, 'reps' => '10-12', 'day_of_week' => 5],
            ]],
            // 减脂
            ['name' => '初学者减脂燃烧', 'goal' => 'fat_loss', 'level' => 'beginner', 'weeks' => 4, 'freq' => 3, 'desc' => '全身循环训练，高次数低休息', 'exercises' => [
                ['name' => '高脚杯深蹲', 'sets' => 3, 'reps' => '15', 'day_of_week' => 1],
                ['name' => '俯卧撑', 'sets' => 3, 'reps' => '12-15', 'day_of_week' => 1],
                ['name' => '哑铃划船', 'sets' => 3, 'reps' => '15', 'day_of_week' => 1],
                ['name' => '弓步蹲', 'sets' => 3, 'reps' => '12/侧', 'day_of_week' => 3],
                ['name' => '哑铃肩推', 'sets' => 3, 'reps' => '15', 'day_of_week' => 3],
                ['name' => '平板支撑', 'sets' => 3, 'reps' => '30s', 'day_of_week' => 3],
            ]],
            ['name' => '中级减脂HIIT', 'goal' => 'fat_loss', 'level' => 'intermediate', 'weeks' => 6, 'freq' => 4, 'desc' => '力量+HIIT结合，最大化脂肪燃烧', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 4, 'reps' => '12', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 4, 'reps' => '12', 'day_of_week' => 1],
                ['name' => '波比跳', 'sets' => 4, 'reps' => '10', 'day_of_week' => 2],
                ['name' => '壶铃摆荡', 'sets' => 4, 'reps' => '15', 'day_of_week' => 2],
                ['name' => '硬拉', 'sets' => 4, 'reps' => '10', 'day_of_week' => 4],
                ['name' => '引体向上', 'sets' => 3, 'reps' => '8-12', 'day_of_week' => 4],
            ]],
            ['name' => '高级减脂冲刺', 'goal' => 'fat_loss', 'level' => 'advanced', 'weeks' => 8, 'freq' => 5, 'desc' => '高频高强度，配合严格饮食', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 5, 'reps' => '10', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 5, 'reps' => '10', 'day_of_week' => 2],
                ['name' => '硬拉', 'sets' => 5, 'reps' => '8', 'day_of_week' => 3],
                ['name' => '哑铃肩推', 'sets' => 4, 'reps' => '12', 'day_of_week' => 4],
                ['name' => '波比跳', 'sets' => 5, 'reps' => '12', 'day_of_week' => 5],
            ]],
            // 力量
            ['name' => '初学者力量基础', 'goal' => 'strength', 'level' => 'beginner', 'weeks' => 6, 'freq' => 3, 'desc' => 'Starting Strength风格，专注三大项', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 3, 'reps' => '5', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 3, 'reps' => '5', 'day_of_week' => 1],
                ['name' => '硬拉', 'sets' => 1, 'reps' => '5', 'day_of_week' => 1],
                ['name' => '杠铃深蹲', 'sets' => 3, 'reps' => '5', 'day_of_week' => 3],
                ['name' => '杠铃推举', 'sets' => 3, 'reps' => '5', 'day_of_week' => 3],
                ['name' => '杠铃划船', 'sets' => 3, 'reps' => '5', 'day_of_week' => 3],
            ]],
            ['name' => '中级力量进阶', 'goal' => 'strength', 'level' => 'intermediate', 'weeks' => 8, 'freq' => 4, 'desc' => '5/3/1周期化力量训练', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 4, 'reps' => '5/3/1', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 4, 'reps' => '5/3/1', 'day_of_week' => 2],
                ['name' => '硬拉', 'sets' => 4, 'reps' => '5/3/1', 'day_of_week' => 4],
                ['name' => '杠铃推举', 'sets' => 4, 'reps' => '5/3/1', 'day_of_week' => 5],
            ]],
            ['name' => '高级力量极限', 'goal' => 'strength', 'level' => 'advanced', 'weeks' => 12, 'freq' => 4, 'desc' => '竞技力量举训练，冲击PR', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 5, 'reps' => '3', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 5, 'reps' => '3', 'day_of_week' => 2],
                ['name' => '硬拉', 'sets' => 5, 'reps' => '3', 'day_of_week' => 4],
                ['name' => '杠铃推举', 'sets' => 5, 'reps' => '3', 'day_of_week' => 5],
            ]],
            // 塑形/维持
            ['name' => '初学者塑形入门', 'goal' => 'body_shaping', 'level' => 'beginner', 'weeks' => 4, 'freq' => 3, 'desc' => '均衡全身训练，改善体态', 'exercises' => [
                ['name' => '高脚杯深蹲', 'sets' => 3, 'reps' => '12', 'day_of_week' => 1],
                ['name' => '哑铃卧推', 'sets' => 3, 'reps' => '12', 'day_of_week' => 1],
                ['name' => '哑铃划船', 'sets' => 3, 'reps' => '12', 'day_of_week' => 3],
                ['name' => '哑铃肩推', 'sets' => 3, 'reps' => '12', 'day_of_week' => 3],
                ['name' => '臀桥', 'sets' => 3, 'reps' => '15', 'day_of_week' => 5],
                ['name' => '平板支撑', 'sets' => 3, 'reps' => '30s', 'day_of_week' => 5],
            ]],
            ['name' => '中级塑形雕刻', 'goal' => 'body_shaping', 'level' => 'intermediate', 'weeks' => 6, 'freq' => 4, 'desc' => '针对性训练，雕刻肌肉线条', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 4, 'reps' => '10-12', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 4, 'reps' => '10-12', 'day_of_week' => 2],
                ['name' => '引体向上', 'sets' => 3, 'reps' => '8-12', 'day_of_week' => 4],
                ['name' => '哑铃侧平举', 'sets' => 3, 'reps' => '15', 'day_of_week' => 5],
            ]],
            ['name' => '高级塑形精雕', 'goal' => 'body_shaping', 'level' => 'advanced', 'weeks' => 8, 'freq' => 5, 'desc' => '高级分化训练，精细化肌肉塑造', 'exercises' => [
                ['name' => '杠铃深蹲', 'sets' => 4, 'reps' => '10', 'day_of_week' => 1],
                ['name' => '杠铃卧推', 'sets' => 4, 'reps' => '10', 'day_of_week' => 2],
                ['name' => '硬拉', 'sets' => 4, 'reps' => '8', 'day_of_week' => 3],
                ['name' => '哑铃肩推', 'sets' => 4, 'reps' => '10', 'day_of_week' => 4],
                ['name' => '杠铃弯举', 'sets' => 3, 'reps' => '12', 'day_of_week' => 5],
            ]],
        ];

        foreach ($templates as $t) {
            PlanTemplate::updateOrCreate(
                ['name' => $t['name']],
                [
                    'description' => $t['desc'],
                    'goal' => $t['goal'],
                    'level' => $t['level'],
                    'duration_weeks' => $t['weeks'],
                    'workouts_per_week' => $t['freq'],
                    'exercises' => $t['exercises'],
                    'tags' => [$t['goal'], $t['level']],
                ]
            );
        }
    }
}
