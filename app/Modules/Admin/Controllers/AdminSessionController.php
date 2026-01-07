<?php

namespace App\Modules\Admin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理员会话控制器
 * 
 * 用于三轨评分系统的专家评审
 */
class AdminSessionController extends BaseController
{
    /**
     * 获取待评审会话列表
     * 
     * 条件：有用户评分但无专家评分
     * 
     * GET /api/admin/sessions/pending-review
     */
    public function pendingReview(Request $request): JsonResponse
    {
        try {
            $sessions = ChatSession::whereNotNull('ux_clarity')  // 有用户评分
                ->whereDoesntHave('expertReviews')  // 无专家评分
                ->with(['user:id,name,username,email'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            $items = $sessions->map(function ($session) {
                return $this->formatSession($session);
            });
            
            return $this->success([
                'sessions' => $items,
                'total' => $sessions->total(),
                'current_page' => $sessions->currentPage(),
            ], '获取待评审会话成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取待评审会话');
        }
    }

    /**
     * 获取已评审会话列表
     * 
     * GET /api/admin/sessions/reviewed
     */
    public function reviewed(Request $request): JsonResponse
    {
        try {
            $sessions = ChatSession::whereHas('expertReviews')
                ->with(['user:id,name,username,email', 'expertReviews'])
                ->orderBy('updated_at', 'desc')
                ->paginate(20);
            
            $items = $sessions->map(function ($session) {
                $data = $this->formatSession($session);
                
                // 添加专家评分
                $expertReview = $session->expertReviews->first();
                if ($expertReview) {
                    $data['expert_avg'] = ($expertReview->accuracy + $expertReview->scientific + 
                                          $expertReview->safety + $expertReview->completeness + 
                                          $expertReview->practicality + $expertReview->personalization) / 6;
                    $data['expert_review'] = [
                        'accuracy' => $expertReview->accuracy,
                        'scientific' => $expertReview->scientific,
                        'safety' => $expertReview->safety,
                        'completeness' => $expertReview->completeness,
                        'practicality' => $expertReview->practicality,
                        'personalization' => $expertReview->personalization,
                        'comments' => $expertReview->comments,
                    ];
                }
                
                return $data;
            });
            
            return $this->success([
                'sessions' => $items,
                'total' => $sessions->total(),
                'current_page' => $sessions->currentPage(),
            ], '获取已评审会话成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取已评审会话');
        }
    }

    /**
     * 格式化会话数据
     */
    private function formatSession(ChatSession $session): array
    {
        return [
            'id' => $session->id,
            'session_id' => $session->session_id,
            'user_query' => $session->user_query,
            'llm_response' => $session->llm_response,
            'model_used' => $session->model_used,
            'tools_used' => $session->tools_used,
            // 用户体验评分
            'ux_clarity' => $session->ux_clarity,
            'ux_practicality' => $session->ux_practicality,
            'ux_detail' => $session->ux_detail,
            'ux_friendliness' => $session->ux_friendliness,
            'ux_satisfaction' => $session->ux_satisfaction,
            // 个性化感知评分
            'profile_utilization_rate' => $session->profile_utilization_rate,
            'goal_alignment' => $session->goal_alignment,
            'uniqueness' => $session->uniqueness,
            'dynamic_adjustment' => $session->dynamic_adjustment,
            'personalization_grade' => $session->personalization_grade,
            // 综合
            'overall_score' => $session->overall_score,
            'fewshot_eligible' => $session->fewshot_eligible,
            // 用户信息
            'user' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->name ?? $session->user->username,
            ] : null,
            'created_at' => $session->created_at?->toIso8601String(),
        ];
    }
}
