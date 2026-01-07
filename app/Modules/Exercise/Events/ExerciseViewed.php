<?php

namespace App\Modules\Exercise\Events;

use App\Modules\Exercise\Models\Exercise;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Exercise Viewed Event
 *
 * 动作被查看事件
 * 
 * @version 2.0.0
 * @date 2025-11-02
 * @changes 参数类型改为 Exercise 对象以适配新架构
 */
class ExerciseViewed
{
    use Dispatchable, SerializesModels;

    public Exercise $exercise;
    public ?int $userId;
    public string $viewedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Exercise $exercise, ?int $userId = null)
    {
        $this->exercise = $exercise;
        $this->userId = $userId;
        $this->viewedAt = now()->toISOString();
    }
}
