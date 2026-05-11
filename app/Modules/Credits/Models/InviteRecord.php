<?php

namespace App\Modules\Credits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\User\Models\User;

/**
 * InviteRecord - 邀请记录模型
 *
 * @property int $id
 * @property int $inviter_id
 * @property int $invitee_id
 * @property string $credits_given_inviter
 * @property string $credits_given_invitee
 * @property \Carbon\Carbon $created_at
 */
class InviteRecord extends Model
{
    protected $table = 'invite_records';

    public $timestamps = false;

    protected $fillable = [
        'inviter_id',
        'invitee_id',
        'credits_given_inviter',
        'credits_given_invitee',
        'created_at',
    ];

    protected $casts = [
        'credits_given_inviter' => 'decimal:6',
        'credits_given_invitee' => 'decimal:6',
        'created_at' => 'datetime',
    ];

    /**
     * 邀请人
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /**
     * 被邀请人
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
