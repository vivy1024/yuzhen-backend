<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户协议同意记录
 *
 * @property int $id
 * @property int $user_id
 * @property string $consent_type terms|privacy
 * @property string $consent_version 协议版本日期
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $consented_at
 */
class UserConsentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'consent_type',
        'consent_version',
        'ip_address',
        'user_agent',
        'consented_at',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
