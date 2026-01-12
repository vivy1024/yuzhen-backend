<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * 会员订单模型 - 会员自动化控制系统
 * 
 * 记录用户购买会员套餐的订单
 * 
 * @property int $id
 * @property string $order_no 订单号
 * @property int $user_id 用户ID
 * @property int $membership_id 会员套餐ID
 * @property float $amount 实付金额
 * @property float $discount_amount 优惠金额
 * @property string|null $pay_method 支付方式：wechat/alipay/manual
 * @property string $status 订单状态：pending/paid/failed/refunded/cancelled
 * @property Carbon|null $paid_at 支付时间
 * @property Carbon $created_at
 * 
 * @property-read User $user
 * @property-read Membership $membership
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 10.1
 */
class MembershipOrder extends Model
{
    use HasFactory;

    protected $table = 'membership_orders';

    /**
     * 禁用updated_at，只使用created_at
     */
    const UPDATED_AT = null;

    /**
     * 订单状态常量
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * 支付方式常量
     */
    const PAY_METHOD_WECHAT = 'wechat';
    const PAY_METHOD_ALIPAY = 'alipay';
    const PAY_METHOD_MANUAL = 'manual';

    protected $fillable = [
        'order_no',
        'user_id',
        'membership_id',
        'amount',
        'discount_amount',
        'pay_method',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联会员套餐
     */
    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * 生成唯一订单号
     * 
     * @return string 格式：M + 年月日时分秒 + 6位随机数
     */
    public static function generateOrderNo(): string
    {
        return 'M' . date('YmdHis') . Str::upper(Str::random(6));
    }

    /**
     * 创建订单
     * 
     * @param int $userId
     * @param int $membershipId
     * @param float $amount
     * @param float $discountAmount
     * @return static
     */
    public static function createOrder(
        int $userId,
        int $membershipId,
        float $amount,
        float $discountAmount = 0
    ): self {
        return static::create([
            'order_no' => static::generateOrderNo(),
            'user_id' => $userId,
            'membership_id' => $membershipId,
            'amount' => $amount,
            'discount_amount' => $discountAmount,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * 获取用户的订单列表
     * 
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserOrders(int $userId, int $limit = 20)
    {
        return static::where('user_id', $userId)
            ->with('membership:id,name,tier,duration_days')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 根据订单号查找订单
     * 
     * @param string $orderNo
     * @return static|null
     */
    public static function findByOrderNo(string $orderNo): ?self
    {
        return static::where('order_no', $orderNo)->first();
    }

    /**
     * 标记为已支付
     * 
     * @param string $payMethod
     * @return bool
     */
    public function markAsPaid(string $payMethod): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }
        
        return $this->update([
            'status' => self::STATUS_PAID,
            'pay_method' => $payMethod,
            'paid_at' => Carbon::now(),
        ]);
    }

    /**
     * 标记为支付失败
     * 
     * @return bool
     */
    public function markAsFailed(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }
        
        return $this->update(['status' => self::STATUS_FAILED]);
    }

    /**
     * 标记为已取消
     * 
     * @return bool
     */
    public function markAsCancelled(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }
        
        return $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * 标记为已退款
     * 
     * @return bool
     */
    public function markAsRefunded(): bool
    {
        if ($this->status !== self::STATUS_PAID) {
            return false;
        }
        
        return $this->update(['status' => self::STATUS_REFUNDED]);
    }

    /**
     * 是否为待支付状态
     * 
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 是否已支付
     * 
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * 是否可以取消（24小时内且未使用会员权益）
     * 
     * @return bool
     */
    public function canCancel(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }
        
        // 24小时内可取消
        return $this->created_at->diffInHours(Carbon::now()) < 24;
    }

    /**
     * 获取状态描述
     * 
     * @return string
     */
    public function getStatusDescription(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => '待支付',
            self::STATUS_PAID => '已支付',
            self::STATUS_FAILED => '支付失败',
            self::STATUS_REFUNDED => '已退款',
            self::STATUS_CANCELLED => '已取消',
            default => '未知状态',
        };
    }

    /**
     * 获取支付方式描述
     * 
     * @return string
     */
    public function getPayMethodDescription(): string
    {
        return match($this->pay_method) {
            self::PAY_METHOD_WECHAT => '微信支付',
            self::PAY_METHOD_ALIPAY => '支付宝',
            self::PAY_METHOD_MANUAL => '人工充值',
            default => '未支付',
        };
    }
}
