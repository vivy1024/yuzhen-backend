<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\UserMembership;
use App\Models\Membership;
use App\Models\TrainingLog;
use App\Models\PersonalBest;

/**
 * User Model
 * 
 * 用户模型
 * 
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $phone
 * @property string $avatar
 * @property string $role
 * @property int $status
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'status',
        'del_flag',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'onboarding_completed',
        'membership_tier',
        // 智能训练系统新增字段
        'preferred_training_time',
        'body_type',
        'user_type',
        'campus_name',
        'personal_volume_multiplier',
        'personal_recovery_factor',
        'last_volume_adjusted_at',
        'consecutive_training_weeks',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_volume_adjusted_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'integer',
        'personal_volume_multiplier' => 'decimal:2',
        'personal_recovery_factor' => 'decimal:2',
        'consecutive_training_weeks' => 'integer',
    ];

    /**
     * 关联：用户档案
     */
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * 关联：当前激活的会员记录
     */
    public function membership()
    {
        return $this->hasOne(UserMembership::class)
            ->where('is_active', true)
            ->with('membership');
    }
    
    /**
     * 关联：所有会员记录
     */
    public function memberships()
    {
        return $this->hasMany(UserMembership::class);
    }
    
    /**
     * 关联：会员等级（通过多对多）
     */
    public function membershipTiers()
    {
        return $this->belongsToMany(Membership::class, 'user_memberships')
            ->withPivot(['order_id', 'started_at', 'expires_at', 'is_active'])
            ->withTimestamps();
    }
    
    /**
     * 获取当前有效的会员等级
     */
    public function getCurrentMembership(): ?Membership
    {
        $userMembership = $this->membership;
        
        if (!$userMembership || !$userMembership->isValid()) {
            return null;
        }
        
        return $userMembership->membership;
    }

    /**
     * 关联：用户偏好
     */
    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    /**
     * 是否是管理员
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * 是否是专家（可以进行专家评审）
     */
    public function isExpert(): bool
    {
        return $this->role === 'expert' || $this->role === 'admin';
    }

    /**
     * 是否是VIP会员
     */
    public function isVip(): bool
    {
        return $this->membership?->is_active && 
               $this->membership?->expires_at?->isFuture();
    }

    /**
     * 更新最后登录时间
     */
    public function updateLastLogin(string $ip): void
    {
        // ✅ 修复: 移除不存在的last_login_ip字段
        $this->update([
            'last_login_at' => now(),
            // 'last_login_ip' => $ip,  // 字段不存在，暂时注释
        ]);
    }

    /**
     * 关联：训练日志
     */
    public function trainingLogs()
    {
        return $this->hasMany(TrainingLog::class);
    }

    /**
     * 关联：个人最佳记录
     */
    public function personalBests()
    {
        return $this->hasMany(PersonalBest::class);
    }

    /**
     * 获取用户的容量系数（带边界检查）
     * 
     * @return float 容量系数（0.7-1.5）
     */
    public function getVolumeMultiplier(): float
    {
        $multiplier = $this->personal_volume_multiplier ?? 1.0;
        return max(0.7, min(1.5, $multiplier));
    }

    /**
     * 更新容量系数
     * 
     * @param float $adjustment 调整值（正数上调，负数下调）
     * @return float 调整后的容量系数
     */
    public function adjustVolumeMultiplier(float $adjustment): float
    {
        $current = $this->getVolumeMultiplier();
        $new = max(0.7, min(1.5, $current + $adjustment));
        
        $this->update([
            'personal_volume_multiplier' => $new,
            'last_volume_adjusted_at' => now(),
        ]);
        
        return $new;
    }

    /**
     * 是否为大学生用户
     */
    public function isStudent(): bool
    {
        return $this->user_type === 'student';
    }

    /**
     * 是否为上班族用户
     */
    public function isWorker(): bool
    {
        return $this->user_type === 'worker';
    }
}

