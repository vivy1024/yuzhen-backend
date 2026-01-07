<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;

/**
 * PersonalBest Model - 个人最佳记录模型
 * 
 * 用于追踪用户每个动作的最佳表现
 * 
 * @property int $id
 * @property int $user_id
 * @property string $exercise_id
 * @property string $exercise_name
 * @property float $best_weight
 * @property int $best_reps
 * @property float $estimated_1rm
 * @property string $achieved_date
 * @property float $last_used_weight
 * @property string $last_used_date
 * @property int $usage_count
 * 
 * @version 1.0.0
 * @date 2025-12-26
 */
class PersonalBest extends Model
{
    use HasFactory;

    protected $table = 'personal_bests';

    protected $fillable = [
        'user_id',
        'exercise_id',
        'exercise_name',
        'best_weight',
        'best_reps',
        'estimated_1rm',
        'achieved_date',
        'last_used_weight',
        'last_used_date',
        'usage_count',
    ];

    protected $casts = [
        'best_weight' => 'decimal:2',
        'estimated_1rm' => 'decimal:2',
        'last_used_weight' => 'decimal:2',
        'achieved_date' => 'date',
        'last_used_date' => 'date',
        'usage_count' => 'integer',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 计算估算1RM（Epley公式）
     * 
     * @param float $weight 重量
     * @param int $reps 次数
     * @return float 估算1RM
     */
    public static function calculate1RM(float $weight, int $reps): float
    {
        if ($reps <= 0 || $weight <= 0) {
            return 0.0;
        }

        if ($reps === 1) {
            return $weight;
        }

        // Epley公式: 1RM = weight × (1 + reps/30)
        return round($weight * (1 + $reps / 30), 2);
    }

    /**
     * 更新个人最佳记录
     * 
     * @param float $weight 新重量
     * @param int $reps 新次数
     * @return bool 是否更新了最佳记录
     */
    public function updateBest(float $weight, int $reps): bool
    {
        $new1RM = self::calculate1RM($weight, $reps);
        $current1RM = $this->estimated_1rm ?? 0;

        // 更新使用记录
        $this->last_used_weight = $weight;
        $this->last_used_date = now()->toDateString();
        $this->usage_count = ($this->usage_count ?? 0) + 1;

        // 检查是否打破记录
        if ($new1RM > $current1RM) {
            $this->best_weight = $weight;
            $this->best_reps = $reps;
            $this->estimated_1rm = $new1RM;
            $this->achieved_date = now()->toDateString();
            $this->save();
            return true;
        }

        $this->save();
        return false;
    }

    /**
     * 作用域：按用户筛选
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 作用域：按动作筛选
     */
    public function scopeForExercise($query, string $exerciseId)
    {
        return $query->where('exercise_id', $exerciseId);
    }

    /**
     * 获取或创建用户的动作记录
     */
    public static function getOrCreate(int $userId, string $exerciseId, ?string $exerciseName = null): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'exercise_id' => $exerciseId],
            ['exercise_name' => $exerciseName, 'usage_count' => 0]
        );
    }
}
