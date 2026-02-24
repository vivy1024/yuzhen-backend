<?php

namespace App\Modules\User\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Internal User API Controller
 * 
 * 用于MCP/CrewAI等内部服务访问用户档案
 * 
 * 需要 X-Internal-Token 认证
 */
class InternalUserController extends BaseController
{
    protected UserService $userService;
    
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * 获取用户档案（为MCP格式化）
     * 
     * GET /api/internal/user-profile/{userId}
     * 
     * ✅ v1.1.0: 完整映射所有user_profiles字段到MCP格式
     */
    public function getUserProfile(int $userId): JsonResponse
    {
        try {
            $user = $this->userService->getDetail($userId);
            
            if (!$user) {
                return $this->fail("用户档案不存在: {$userId}", 404);
            }
            
            // 获取用户档案（如果存在）- 修复：使用数组访问方式
            $profile = $user['profile'] ?? null;
            
            if (!$profile) {
                return $this->fail("用户档案未创建: {$userId}", 404);
            }
            
            // ✅ 计算BMI - 修复：使用数组访问方式
            $bmi = null;
            if (($profile['height'] ?? null) && ($profile['weight'] ?? null)) {
                $heightM = $profile['height'] / 100;
                $bmi = round($profile['weight'] / ($heightM ** 2), 1);
            }
            
            // ✅ 优先从JSON字段读取（统一字段名，无_v2后缀）- 修复：使用数组访问方式
            $basicInfo = $profile['basic_info'] ?? [];
            $fitnessGoals = $profile['fitness_goals'] ?? [];
            $trainingPreferences = $profile['training_preferences'] ?? [];
            $healthStatus = $profile['health_status'] ?? [];
            $nutritionProfile = $profile['nutrition_profile'] ?? [];
            $strengthData = $profile['strength_data'] ?? [];
            $ffmiData = $profile['ffmi_assessment'] ?? [];
            
            // ✅ 计算BMI（优先从basic_info读取）- 修复：使用数组访问方式
            $height = $basicInfo['height'] ?? ($profile['height'] ?? null);
            $weight = $basicInfo['weight'] ?? ($profile['weight'] ?? null);
            $bmi = null;
            if ($height && $weight) {
                $heightM = $height / 100;
                $bmi = round($weight / ($heightM ** 2), 1);
            }
            
            // ✅ 转换为MCP期望的格式（优先使用JSON字段，降级到扁平字段）
            // ✅ v1.2.0: 添加users表中的训练系统字段
            $mcpFormat = [
                'user_id' => (string)$userId,
                
                // ============ 训练系统字段（来自users表）============
                'training_system' => [
                    'preferred_training_time' => $user['preferred_training_time'] ?? null,
                    'body_type' => $user['body_type'] ?? null,
                    'user_type' => $user['user_type'] ?? 'other',
                    'campus_name' => $user['campus_name'] ?? null,
                    'personal_volume_multiplier' => (float)($user['personal_volume_multiplier'] ?? 1.0),
                    'personal_recovery_factor' => (float)($user['personal_recovery_factor'] ?? 1.0),
                    'last_volume_adjusted_at' => $user['last_volume_adjusted_at'] ?? null,
                    'consecutive_training_weeks' => (int)($user['consecutive_training_weeks'] ?? 0),
                ],
                
                // ============ 基础信息 ============
                'basic_info' => array_merge([
                    'age' => $basicInfo['age'] ?? ($profile['age'] ?? null),
                    'gender' => $basicInfo['gender'] ?? ($profile['gender'] ?? null),
                    'height' => $height,
                    'weight' => $weight,
                    'body_fat_percentage' => $basicInfo['body_fat_percentage'] ?? ($profile['body_fat_percentage'] ?? null),
                    'bmi' => $bmi,
                    'ffmi' => $ffmiData['ffmi'] ?? null,
                    'region' => $basicInfo['region'] ?? ($profile['region'] ?? null),
                    'fitness_level' => $basicInfo['fitness_level'] ?? ($profile['fitness_level'] ?? null),
                    'sleep_hours' => $basicInfo['sleep_hours'] ?? ($profile['sleep_hours'] ?? null),
                    'nickname' => $basicInfo['nickname'] ?? ($profile['nickname'] ?? null),
                    // ✅ 添加用户类型相关字段到basic_info（兼容旧版MCP）
                    'body_type' => $user['body_type'] ?? null,
                    'user_type' => $user['user_type'] ?? 'other',
                ], $basicInfo),
                
                // ============ 营养档案 ============
                'nutrition_profile' => !empty($nutritionProfile) ? $nutritionProfile : [
                    'user_settings' => [
                        'population_type' => ($profile['nutrition_user_settings']['population_type'] ?? null) ?? '普通人群',
                        'dietary_preferences' => !empty($profile['dietary_restrictions']) ? json_decode($profile['dietary_restrictions'], true) : [],
                        'allergies' => !empty($profile['allergies']) ? json_decode($profile['allergies'], true) : [],
                        'supplements' => $profile['nutrition_user_settings']['supplements'] ?? [],
                        'budget' => $profile['nutrition_user_settings']['budget'] ?? 'moderate',
                    ],
                    'auto_calculated' => !empty($profile['nutrition_auto_calculated']) ? json_decode($profile['nutrition_auto_calculated'], true) : [
                        'tdee' => $profile['tdee'] ?? null,
                        'bmr' => $profile['bmr'] ?? null,
                    ],
                ],
                
                // ============ 健身配置 ============
                'fitness_config' => !empty($trainingPreferences) ? array_merge([
                    'training_experience_months' => null, // 需要从其他字段计算
                    'fitness_level' => $profile['fitness_level'] ?? null,
                    'preferred_training_split' => $profile['training_split'] ?? null,
                    'preferred_rest_pattern' => $profile['preferred_rest_pattern'] ?? ($trainingPreferences['preferred_rest_pattern'] ?? null),
                    'preferred_training_time' => $user['preferred_training_time'] ?? null,  // ✅ 来自users表
                    'training_days_per_week' => ($profile['training_days'] ?? null) ?? ($profile['workout_frequency'] ?? null),
                    'training_duration_per_session' => $profile['workout_duration'] ?? null,
                    'training_location' => $profile['training_location'] ?? null,
                    'exercise_preferences' => !empty($profile['exercise_preferences']) ? json_decode($profile['exercise_preferences'], true) : [],
                    'disliked_exercises' => !empty($profile['disliked_exercises']) ? json_decode($profile['disliked_exercises'], true) : [],
                    'sleep_hours' => $profile['sleep_hours'] ?? null,
                ], $trainingPreferences) : [
                    'fitness_level' => $profile['fitness_level'] ?? null,
                    'preferred_training_split' => $profile['training_split'] ?? null,
                    'preferred_rest_pattern' => $profile['preferred_rest_pattern'] ?? null,
                    'preferred_training_time' => $user['preferred_training_time'] ?? null,  // ✅ 来自users表
                    'training_days_per_week' => ($profile['training_days'] ?? null) ?? ($profile['workout_frequency'] ?? null),
                    'training_duration_per_session' => $profile['workout_duration'] ?? null,
                    'training_location' => $profile['training_location'] ?? null,
                    'exercise_preferences' => !empty($profile['exercise_preferences']) ? json_decode($profile['exercise_preferences'], true) : [],
                    'disliked_exercises' => !empty($profile['disliked_exercises']) ? json_decode($profile['disliked_exercises'], true) : [],
                    'preferred_equipment' => !empty($profile['preferred_equipment']) ? json_decode($profile['preferred_equipment'], true) : [],
                    'training_intensity_preference' => $profile['training_intensity_preference'] ?? null,
                    'activity_level' => $profile['activity_level'] ?? null,
                    'sleep_hours' => $profile['sleep_hours'] ?? null,
                ],
                
                // ============ 健身目标 ============
                // 前端使用 primary_goal（单数，字符串）和 secondary_goals（数组）
                // 数据库中 fitness_goals 是 JSON 字段，存储格式为：
                // { "primary_goal": "hypertrophy", "secondary_goals": ["fat_loss"], "target_weight": 70 }
                'fitness_goals' => [
                    'primary_goal' => $fitnessGoals['primary_goal'] ?? null,
                    'secondary_goals' => $fitnessGoals['secondary_goals'] ?? [],
                    'target_weight' => $fitnessGoals['target_weight'] ?? null,
                    'goal_priority' => $fitnessGoals['goal_priority'] ?? [],
                    'training_split' => $fitnessGoals['training_split'] ?? null,
                ],
                
                // ============ 力量水平 ============
                'strength_data' => $strengthData,
                
                // ============ 健康档案 ============
                'health_status' => !empty($healthStatus) ? $healthStatus : [
                    'injury_history' => !empty($profile['injuries']) ? json_decode($profile['injuries'], true) : [],
                    'chronic_diseases' => !empty($profile['chronic_diseases']) ? json_decode($profile['chronic_diseases'], true) : [],
                    'health_conditions' => !empty($profile['health_conditions']) ? json_decode($profile['health_conditions'], true) : [],
                    'medications' => !empty($profile['medications']) ? json_decode($profile['medications'], true) : [],
                    'other_notes' => $profile['health_other_notes'] ?? null,
                ],
                
                // ============ FFMI评估 ============
                'ffmi_assessment' => $ffmiData,
                
                // ============ 训练偏好 ============
                'training_preferences' => !empty($trainingPreferences) ? $trainingPreferences : [
                    'training_split' => $profile['training_split'] ?? null,
                    'training_days' => ($profile['training_days'] ?? null) ?? ($profile['workout_frequency'] ?? null),
                    'training_duration' => $profile['workout_duration'] ?? null,
                    'training_location' => $profile['training_location'] ?? null,
                    'exercise_preferences' => !empty($profile['exercise_preferences']) ? json_decode($profile['exercise_preferences'], true) : [],
                    'disliked_exercises' => !empty($profile['disliked_exercises']) ? json_decode($profile['disliked_exercises'], true) : [],
                    'available_equipment' => !empty($profile['preferred_equipment']) ? json_decode($profile['preferred_equipment'], true) : [],
                ],
                
                // ============ 元数据 ============
                'created_at' => !empty($profile['created_at']) ? (is_object($profile['created_at']) ? $profile['created_at']->toISOString() : $profile['created_at']) : null,
                'updated_at' => !empty($profile['updated_at']) ? (is_object($profile['updated_at']) ? $profile['updated_at']->toISOString() : $profile['updated_at']) : null,
                'version' => $profile['version'] ?? null,
                'sync_status' => $profile['sync_status'] ?? null,
            ];
            
            Log::info("MCP访问用户档案成功", [
                'user_id' => $userId,
                'profile_version' => $profile['version'] ?? null,
            ]);
            
            return $this->success($mcpFormat, 'MCP获取用户档案成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'MCP获取用户档案');
        }
    }

    /**
     * 更新用户容量系数
     * 
     * PUT /api/internal/user-profile/{userId}/volume-multiplier
     * 
     * 用于DAML-RAG的容量动态调整功能
     * 
     * @param int $userId 用户ID
     * @return JsonResponse
     */
    public function updateVolumeMultiplier(int $userId): JsonResponse
    {
        try {
            $request = request();
            
            // 验证请求参数
            $newMultiplier = $request->input('new_multiplier');
            $adjustment = $request->input('adjustment');
            $reason = $request->input('reason', '');
            
            if ($newMultiplier === null) {
                return $this->fail('缺少必要参数: new_multiplier', 400);
            }
            
            // 验证容量系数范围
            $newMultiplier = (float)$newMultiplier;
            if ($newMultiplier < 0.7 || $newMultiplier > 1.5) {
                return $this->fail('容量系数必须在0.7-1.5范围内', 400);
            }
            
            // 获取用户
            $user = \App\Modules\User\Models\User::find($userId);
            
            if (!$user) {
                return $this->fail("用户不存在: {$userId}", 404);
            }
            
            // 记录旧值
            $oldMultiplier = $user->personal_volume_multiplier ?? 1.0;
            
            // 更新容量系数
            $user->update([
                'personal_volume_multiplier' => $newMultiplier,
                'last_volume_adjusted_at' => now(),
            ]);
            
            Log::info("容量系数更新成功", [
                'user_id' => $userId,
                'old_multiplier' => $oldMultiplier,
                'new_multiplier' => $newMultiplier,
                'adjustment' => $adjustment,
                'reason' => $reason,
            ]);
            
            return $this->success([
                'user_id' => $userId,
                'old_multiplier' => $oldMultiplier,
                'new_multiplier' => $newMultiplier,
                'adjustment' => $adjustment,
                'reason' => $reason,
                'adjusted_at' => now()->toISOString(),
            ], '容量系数更新成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '更新容量系数');
        }
    }

    /**
     * 获取活跃用户ID列表
     * 
     * GET /api/internal/users/active
     * 
     * 活跃用户定义：有用户档案的用户
     * 用于DAML-RAG服务启动时预热用户缓存
     * 
     * @return JsonResponse
     */
    public function getActiveUsers(): JsonResponse
    {
        try {
            $request = request();
            $limit = (int)($request->input('limit', 20));
            $limit = min($limit, 100); // 最多返回100个
            
            // 查询有用户档案的用户ID
            $userIds = \App\Modules\User\Models\User::query()
                ->whereHas('profile') // 有用户档案
                ->where('is_active', true) // 账号激活状态
                ->orderBy('updated_at', 'desc') // 最近更新的优先
                ->limit($limit)
                ->pluck('id')
                ->toArray();
            
            Log::info("获取活跃用户列表", [
                'count' => count($userIds),
                'limit' => $limit,
            ]);
            
            return $this->success([
                'user_ids' => $userIds,
                'count' => count($userIds),
            ], '获取活跃用户列表成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取活跃用户列表');
        }
    }

    /**
     * 获取最近登录的用户列表
     * 
     * GET /api/internal/users/recent
     * 
     * 返回最近登录的用户，用于预热缓存
     * 
     * @return JsonResponse
     */
    public function getRecentUsers(): JsonResponse
    {
        try {
            $request = request();
            $limit = (int)($request->input('limit', 20));
            $limit = min($limit, 100);
            
            // 查询最近登录的用户
            $users = \App\Modules\User\Models\User::query()
                ->whereHas('profile') // 有用户档案
                ->whereNotNull('last_login_at') // 有登录记录
                ->orderBy('last_login_at', 'desc') // 最近登录的优先
                ->limit($limit)
                ->get(['id', 'last_login_at']);
            
            $result = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'last_login_at' => $user->last_login_at?->toISOString(),
                ];
            })->toArray();
            
            Log::info("获取最近登录用户列表", [
                'count' => count($result),
                'limit' => $limit,
            ]);
            
            return $this->success([
                'users' => $result,
                'count' => count($result),
            ], '获取最近登录用户列表成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取最近登录用户列表');
        }
    }
}

