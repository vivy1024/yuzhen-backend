<?php

namespace App\Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Profile Request
 * 
 * 更新用户档案请求验证
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 基础信息
            'nickname' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female,other',
            'age' => 'nullable|integer|min:10|max:120',
            'height' => 'nullable|numeric|min:50|max:300',
            'weight' => 'nullable|numeric|min:20|max:500',
            'body_fat_percentage' => 'nullable|numeric|min:3|max:60',
            'fitness_level' => 'nullable|string|in:novice,beginner,intermediate,advanced',
            'region' => 'nullable|string|max:100',
            'sleep_hours' => 'nullable|numeric|min:0|max:24',
            
            // 健身目标（支持英文枚举和中文，validated()中统一转为英文）
            // 支持两种格式：primary_goal（字符串）或 primary_goals（数组）
            'primary_goal' => 'nullable|string',
            'primary_goals' => 'nullable|array',
            'primary_goals.*' => 'nullable|string',
            'secondary_goals' => 'nullable|array',
            'secondary_goals.*' => 'nullable|string',
            'target_weight' => 'nullable|numeric|min:20|max:500',
            'training_split' => 'nullable|string',
            
            // 训练偏好
            'training_location' => 'nullable|string',
            'available_equipment' => 'nullable|array',
            'available_equipment.*' => 'nullable|string|in:杠铃,杠铃片,哑铃,固定器械,自由重量架,史密斯架,龙门架,弹力带,壶铃,TRX,药球,波速球,健身球,跳箱,战绳,徒手',
            'training_intensity' => 'nullable|string',
            'exercise_preferences' => 'nullable|array',
            'disliked_exercises' => 'nullable|array',
            'preferred_rest_pattern' => 'nullable|string|in:练一休一,练二休一,练三休一,练四休一,练五休一,练五休二,练六休一,练七休一',
            
            // 健康状况
            'chronic_diseases' => 'nullable|array',
            'medications' => 'nullable|array',
            // 伤病史（统一化后的21个具体伤病选项）
            'injury_history' => 'nullable|array',
            'injury_history.*' => 'nullable|string|in:无,下背部疼痛,腰椎间盘突出,前交叉韧带损伤,膝盖受伤,髌骨软化症,髂胫束综合征,肩峰撞击,肩袖损伤,肩部受伤,腕管综合征,腕部受伤,跟腱炎,足底筋膜炎,踝关节扭伤,颈椎病,颈部受伤,网球肘,高尔夫球肘,髋关节撞击,髋滑囊炎,髋部受伤,其他',
            'health_notes' => 'nullable|string',
            
            // 营养档案
            'nutrition_settings' => 'nullable|array',
            
            // 力量数据和FFMI
            'strength_data' => 'nullable|array',
            'ffmi_assessment' => 'nullable|array',
            
            // 版本控制
            'version' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'gender.in' => '性别必须是：male, female, other 之一',
            'birthday.before' => '生日必须早于今天',
            'height.min' => '身高不能小于50cm',
            'height.max' => '身高不能大于300cm',
            'weight.min' => '体重不能小于20kg',
            'weight.max' => '体重不能大于500kg',
            'fitness_goal.in' => '健身目标无效',
            'fitness_level.in' => '健身水平无效',
            'preferred_rest_pattern.in' => '休息模式必须是：练一休一、练二休一、练三休一、练四休一、练五休一、练五休二、练六休一、练七休一 之一',
            'primary_goal.in' => '主要训练目标必须是：hypertrophy(增肌)、fat_loss(减脂)、strength(力量)、endurance(耐力)、body_shaping(塑形)、general_fitness(综合)、functional(功能性)、rehabilitation(康复) 之一',
            'secondary_goals.*.in' => '次要训练目标必须是：hypertrophy、fat_loss、strength、endurance、body_shaping、general_fitness、functional、rehabilitation 之一',
            'available_equipment.*.in' => '器械选项无效，请选择有效的器械',
            'injury_history.*.in' => '伤病选项无效，请选择具体的伤病类型',
        ];
    }

    /**
     * 验证后转换数据格式
     * 
     * 将扁平的英文字段转换为嵌套的JSON结构
     * 同时归一化训练目标（中文→英文枚举）
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // 中文→英文目标映射
        $goalMap = [
            '增肌' => 'hypertrophy',
            '减脂' => 'fat_loss',
            '增强力量' => 'strength',
            '提高耐力' => 'endurance',
            '塑形' => 'body_shaping',
            '综合健身' => 'general_fitness',
            '功能性训练' => 'functional',
            '康复训练' => 'rehabilitation',
            '运动表现' => 'athletic_performance',
        ];

        // 归一化单个目标值
        $normalizeGoal = function ($goal) use ($goalMap) {
            if (!$goal) return $goal;
            return $goalMap[$goal] ?? $goal; // 中文转英文，已是英文则原样返回
        };

        // 归一化目标数组
        $normalizeGoals = function ($goals) use ($normalizeGoal) {
            if (!is_array($goals)) return $goals;
            return array_map($normalizeGoal, $goals);
        };

        // 确定 primary_goal
        $primaryGoal = $validated['primary_goal'] 
            ?? (isset($validated['primary_goals']) && is_array($validated['primary_goals']) && count($validated['primary_goals']) > 0 
                ? $validated['primary_goals'][0] 
                : '');

        // 转换为嵌套的JSON结构
        return [
            'basic_info' => array_filter([
                'nickname' => $validated['nickname'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'age' => $validated['age'] ?? null,
                'height' => $validated['height'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'body_fat_percentage' => $validated['body_fat_percentage'] ?? null,
                'fitness_level' => $validated['fitness_level'] ?? null,
                'region' => $validated['region'] ?? null,
                'sleep_hours' => $validated['sleep_hours'] ?? null,
            ], fn($value) => !is_null($value)),
            
            'fitness_goals' => [
                'primary_goal' => $normalizeGoal($primaryGoal),
                'secondary_goals' => $normalizeGoals($validated['secondary_goals'] ?? []),
                'target_weight' => $validated['target_weight'] ?? null,
                'training_split' => $validated['training_split'] ?? null,
            ],
            
            'training_preferences' => [
                'training_location' => $validated['training_location'] ?? null,
                'available_equipment' => $validated['available_equipment'] ?? [],
                'training_intensity' => $validated['training_intensity'] ?? null,
                'exercise_preferences' => $validated['exercise_preferences'] ?? null,
                'disliked_exercises' => $validated['disliked_exercises'] ?? null,
            ],
            
            'preferred_rest_pattern' => $validated['preferred_rest_pattern'] ?? null,
            
            'health_status' => [
                'chronic_diseases' => $validated['chronic_diseases'] ?? [],
                'medications' => $validated['medications'] ?? [],
                'injury_history' => $validated['injury_history'] ?? [],
                'other_notes' => $validated['health_notes'] ?? null,
            ],
            
            'nutrition_profile' => [
                'user_settings' => $validated['nutrition_settings'] ?? [],
            ],
            
            'strength_data' => $validated['strength_data'] ?? [],
            'ffmi_assessment' => $validated['ffmi_assessment'] ?? null,
            'version' => $validated['version'] ?? 1,
        ];
    }
}

