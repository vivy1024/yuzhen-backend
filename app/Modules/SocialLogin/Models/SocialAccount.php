<?php

namespace App\Modules\SocialLogin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;

/**
 * Social Account Model
 * 
 * 社交账号模型
 * 
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string $provider_username
 * @property string $provider_nickname
 * @property string $provider_avatar
 * @property string $provider_email
 * @property string $access_token
 * @property string $refresh_token
 * @property int $expires_in
 * @property \DateTime $token_expires_at
 * @property array $raw_data
 */
class SocialAccount extends Model
{
    use HasFactory;

    protected $table = 'social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_username',
        'provider_nickname',
        'provider_avatar',
        'provider_email',
        'access_token',
        'refresh_token',
        'expires_in',
        'token_expires_at',
        'raw_data',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'expires_in' => 'integer',
        'token_expires_at' => 'datetime',
        'raw_data' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Token是否过期
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        
        return $this->token_expires_at->isPast();
    }

    /**
     * 获取加密的access_token
     */
    public function getAccessTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * 设置加密的access_token
     */
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    /**
     * 获取加密的refresh_token
     */
    public function getRefreshTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * 设置加密的refresh_token
     */
    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = $value ? encrypt($value) : null;
    }
}

