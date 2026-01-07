<?php

namespace App\Modules\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;

/**
 * Payment Model
 * 
 * 支付记录模型
 */
class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'payment_no',
        'order_id',
        'user_id',
        'amount',
        'pay_method',
        'status',
        'trade_no',
        'pay_params',
        'callback_data',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'pay_params' => 'array',
        'callback_data' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * 生成支付流水号
     */
    public static function generatePaymentNo(): string
    {
        return 'PAY' . date('YmdHis') . Str::random(6);
    }

    /**
     * 关联：订单
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 是否成功
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * 标记为成功
     */
    public function markAsSuccess(string $tradeNo, array $callbackData = []): void
    {
        $this->status = 'success';
        $this->trade_no = $tradeNo;
        $this->callback_data = $callbackData;
        $this->paid_at = now();
        $this->save();
    }

    /**
     * 标记为失败
     */
    public function markAsFailed(): void
    {
        $this->status = 'failed';
        $this->save();
    }
}

