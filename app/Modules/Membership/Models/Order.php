<?php

namespace App\Modules\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;

/**
 * Order Model
 * 
 * 订单模型
 */
class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'order_no',
        'user_id',
        'membership_id',
        'amount',
        'actual_amount',
        'discount_amount',
        'status',
        'pay_method',
        'pay_trade_no',
        'paid_at',
        'payment_proof_url',
        'proof_uploaded_at',
        'reviewer_id',
        'reviewed_at',
        'review_note',
        'remark',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'proof_uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // 订单状态常量
    const STATUS_PENDING = 'pending';      // 待支付
    const STATUS_REVIEWING = 'reviewing';  // 审核中（已上传截图）
    const STATUS_PAID = 'paid';            // 已支付
    const STATUS_CANCELLED = 'cancelled';  // 已取消
    const STATUS_REFUNDED = 'refunded';    // 已退款

    /**
     * 生成订单号
     */
    public static function generateOrderNo(): string
    {
        return date('YmdHis') . Str::random(8);
    }

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联：会员等级
     */
    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * 关联：支付记录
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * 是否待支付
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 是否审核中
     */
    public function isReviewing(): bool
    {
        return $this->status === self::STATUS_REVIEWING;
    }

    /**
     * 是否已支付
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * 标记为已支付
     */
    public function markAsPaid(string $tradeNo, string $payMethod): void
    {
        $this->status = self::STATUS_PAID;
        $this->pay_trade_no = $tradeNo;
        $this->pay_method = $payMethod;
        $this->paid_at = now();
        $this->save();
    }

    /**
     * 上传支付截图（进入审核状态）
     */
    public function uploadPaymentProof(string $proofUrl, string $payMethod): void
    {
        $this->payment_proof_url = $proofUrl;
        $this->proof_uploaded_at = now();
        $this->pay_method = $payMethod;
        $this->status = self::STATUS_REVIEWING;
        $this->save();
    }

    /**
     * 审核通过
     */
    public function approvePayment(int $reviewerId, ?string $note = null): void
    {
        $this->status = self::STATUS_PAID;
        $this->reviewer_id = $reviewerId;
        $this->reviewed_at = now();
        $this->review_note = $note;
        $this->paid_at = now();
        $this->pay_trade_no = 'MANUAL_' . $this->order_no;
        $this->save();
    }

    /**
     * 审核拒绝
     */
    public function rejectPayment(int $reviewerId, string $note): void
    {
        $this->status = self::STATUS_PENDING;
        $this->reviewer_id = $reviewerId;
        $this->reviewed_at = now();
        $this->review_note = $note;
        $this->payment_proof_url = null;
        $this->proof_uploaded_at = null;
        $this->save();
    }

    /**
     * 取消订单
     */
    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    /**
     * 退款
     */
    public function refund(): void
    {
        $this->status = self::STATUS_REFUNDED;
        $this->save();
    }

    /**
     * 作用域：待支付
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * 作用域：已支付
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}

