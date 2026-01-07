<?php

namespace App\Modules\User\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\User\Services\UserService;
use App\Modules\User\Requests\UpdateProfileRequest;
use App\Modules\User\Resources\UserResource;
use App\Modules\User\Resources\UserDetailResource;
use App\Modules\User\Resources\UserProfileResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * User Controller
 * 
 * 用户API控制器
 */
class UserController extends BaseController
{
    protected UserService $userService;
    
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * 获取用户列表（管理员）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'role' => $request->input('role'),
                'status' => $request->input('status'),
                'search' => $request->input('search'),
            ];
            
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $result = $this->userService->getList($filters, $page, $perPage);
            
            return $this->page(
                UserResource::collection($result['rows'])->resolve(),
                $result['total'],
                $result['page'],
                $result['per_page'],
                '获取用户列表成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户列表');
        }
    }

    /**
     * 获取用户详情
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getDetail($id);
            
            if (!$user) {
                return $this->fail('用户不存在', 404);
            }
            
            return $this->success(
                new UserDetailResource($user),
                '获取用户详情成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户详情');
        }
    }

    /**
     * 获取当前登录用户信息
     */
    public function me(Request $request): JsonResponse
    {
        try {
            // ✅ 兼容数组和对象两种情况
            $currentUser = $request->user();
            if (is_array($currentUser)) {
                $userId = $currentUser['id'] ?? null;
            } else {
                $userId = $currentUser->id ?? null;
            }
            
            if (!$userId) {
                return $this->fail('无法获取用户ID', 401);
            }
            
            // ✅ 直接查询User模型对象
            $user = \App\Modules\User\Models\User::with(['profile', 'membership'])->find($userId);
            
            if (!$user) {
                return $this->fail('用户不存在', 404);
            }
            
            return $this->success(
                new UserDetailResource($user),
                '获取用户信息成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户信息');
        }
    }

    /**
     * 获取用户档案
     *
     * GET /api/users/profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        try {
            // ✅ 从request获取JWT中间件设置的用户
            $currentUser = $request->user();
            if (is_array($currentUser)) {
                $userId = $currentUser['id'] ?? null;
            } else {
                $userId = $currentUser->id ?? null;
            }

            if (!$userId) {
                return $this->fail('用户未认证', 401);
            }

            // ✅ 直接查询UserProfile模型
            $profile = \App\Modules\User\Models\UserProfile::where('user_id', $userId)->first();

            if (!$profile) {
                return $this->fail('用户档案不存在，请先创建档案', 404);
            }

            // 返回用户档案（直接UserProfile结构，对齐前端类型）
            return $this->success(
                new UserProfileResource($profile),
                '获取用户档案成功'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户档案');
        }
    }

    /**
     * 更新用户档案
     * 
     * PUT/POST /api/users/profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            // ✅ 使用request->user()，兼容数组和对象两种情况
            $user = $request->user();
            \Log::info('UpdateProfile - User type: ' . gettype($user), ['user' => $user]);
            
            // 兼容性处理：支持数组和对象
            if (is_array($user)) {
                $userId = $user['id'] ?? null;
            } else {
                $userId = $user->id ?? null;
            }
            
            if (!$userId) {
                return $this->fail('无法获取用户ID', 401);
            }
            
            $profileData = $request->validated();
            
            $result = $this->userService->updateProfile($userId, $profileData);

            if (!$result) {
                return $this->fail('更新档案失败');
            }

            // ✅ 直接查询UserProfile模型
            $profile = \App\Modules\User\Models\UserProfile::where('user_id', $userId)->first();

            if (!$profile) {
                return $this->fail('用户档案不存在', 404);
            }

            return $this->success(
                new UserProfileResource($profile),
                '更新档案成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '更新用户档案');
        }
    }

    /**
     * 获取用户统计信息
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $stats = $this->userService->getStatistics($userId);

            return $this->success($stats, '获取统计信息成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取统计信息');
        }
    }

    /**
     * 获取FFMI历史记录
     *
     * GET /api/users/profile/ffmi-history
     */
    public function getFFMIHistory(Request $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            if (is_array($currentUser)) {
                $userId = $currentUser['id'] ?? null;
            } else {
                $userId = $currentUser->id ?? null;
            }

            if (!$userId) {
                return $this->fail('用户未认证', 401);
            }

            $limit = min($request->input('limit', 10), 50); // 限制最大50条

            // 简化实现：从user_profiles表的ffmi_assessment字段获取历史
            $profiles = \App\Modules\User\Models\UserProfile::where('user_id', $userId)
                ->whereNotNull('ffmi_assessment')
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get(['id', 'user_id', 'basic_info', 'ffmi_assessment', 'updated_at']);

            $history = $profiles->map(function ($profile) {
                $basicInfo = $profile->basic_info;
                $ffmiData = $profile->ffmi_assessment;

                return [
                    'id' => $profile->id,
                    'user_id' => $profile->user_id,
                    'height' => $basicInfo['height'] ?? null,
                    'weight' => $basicInfo['weight'] ?? null,
                    'body_fat_percentage' => $basicInfo['body_fat_percentage'] ?? null,
                    'ffmi_data' => $ffmiData,
                    'recorded_at' => $profile->updated_at->toISOString(),
                ];
            });

            return $this->success($history->toArray(), '获取FFMI历史成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取FFMI历史');
        }
    }

    /**
     * 保存FFMI记录到历史
     *
     * POST /api/users/profile/ffmi-history
     */
    public function saveFFMIHistory(Request $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            if (is_array($currentUser)) {
                $userId = $currentUser['id'] ?? null;
            } else {
                $userId = $currentUser->id ?? null;
            }

            if (!$userId) {
                return $this->fail('用户未认证', 401);
            }

            $data = $request->validate([
                'height' => 'required|numeric|min:100|max:250',
                'weight' => 'required|numeric|min:30|max:300',
                'body_fat_percentage' => 'nullable|numeric|min:5|max:50',
                'ffmi_data' => 'required|array',
            ]);

            // 查找现有档案或创建新的
            $profile = \App\Modules\User\Models\UserProfile::where('user_id', $userId)->first();

            if (!$profile) {
                return $this->fail('用户档案不存在，请先完善基础信息', 404);
            }

            // 更新basic_info中的身高体重数据
            $basicInfo = $profile->basic_info ?? [];
            $basicInfo['height'] = $data['height'];
            $basicInfo['weight'] = $data['weight'];
            if (isset($data['body_fat_percentage'])) {
                $basicInfo['body_fat_percentage'] = $data['body_fat_percentage'];
            }

            $profile->basic_info = $basicInfo;
            $profile->ffmi_assessment = $data['ffmi_data'];
            $profile->incrementVersion();
            $profile->save();

            $result = [
                'id' => $profile->id,
                'user_id' => $profile->user_id,
                'height' => $data['height'],
                'weight' => $data['weight'],
                'body_fat_percentage' => $data['body_fat_percentage'] ?? null,
                'ffmi_data' => $data['ffmi_data'],
                'recorded_at' => $profile->updated_at->toISOString(),
            ];

            return $this->success($result, '保存FFMI记录成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '保存FFMI记录');
        }
    }
}

