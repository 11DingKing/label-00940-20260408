<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class Order extends Model
{
    protected ?string $table = 'orders';

    protected array $fillable = [
        'order_no',
        'user_id',
        'total_amount',
        'status',
        'address',
        'receiver',
        'phone',
        'remark',
        'paid_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'total_amount' => 'decimal:2',
        'status' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 订单状态
    public const STATUS_PENDING = 0;    // 待支付
    public const STATUS_PAID = 1;       // 已支付
    public const STATUS_SHIPPED = 2;    // 已发货
    public const STATUS_COMPLETED = 3;  // 已完成
    public const STATUS_CANCELLED = 4;  // 已取消

    public static array $statusMap = [
        self::STATUS_PENDING => '待支付',
        self::STATUS_PAID => '已支付',
        self::STATUS_SHIPPED => '已发货',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_CANCELLED => '已取消',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public function getStatusTextAttribute(): string
    {
        return self::$statusMap[$this->status] ?? '未知';
    }
}
