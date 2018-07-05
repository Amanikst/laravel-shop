<?php
/**
 * Name: 订单模型.
 * User: 董坤鸿
 * Date: 2018/6/29
 * Time: 上午11:43
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Order extends Model
{
    const REFUND_STATUS_PENDING = 'pending';    // 未退款
    const REFUND_STATUS_APPLIED = 'applied';    // 已申请退款
    const REFUND_STATUS_PROCESSING = 'processing';  // 退款中
    const REFUND_STATUS_SUCCESS = 'success';    // 退款成功
    const REFUND_STATUS_FAILED = 'failed';  // 退款失败

    const SHIP_STATUS_PENDING = 'pending';  // 未发货
    const SHIP_STATUS_DELIVERED = 'delivered';  // 已发货
    const SHIP_STATUS_RECEIVED = 'received';    // 已收货

    /**
     * 退款状态
     *
     * @var array
     */
    public static $refundStatusMap = [
        self::REFUND_STATUS_PENDING => '未退款',
        self::REFUND_STATUS_APPLIED => '已申请退款',
        self::REFUND_STATUS_PROCESSING => '退款中',
        self::REFUND_STATUS_SUCCESS => '退款成功',
        self::REFUND_STATUS_FAILED => '退款失败',
    ];

    /**
     * 发货状态
     *
     * @var array
     */
    public static $shipStatusMap = [
        self::SHIP_STATUS_PENDING => '未发货',
        self::SHIP_STATUS_DELIVERED => '已发货',
        self::SHIP_STATUS_RECEIVED => '已收货',
    ];

    /**
     * 可以分配的属性。
     *
     * @var array
     */
    protected $fillable = [
        'no', 'address', 'total_amount', 'remark', 'paid_at', 'payment_method', 'payment_no',
        'refund_status', 'refund_no', 'closed', 'reviewed', 'ship_status', 'ship_data', 'extra',
    ];

    /**
     * 应该被转换成原生类型的属性。
     *
     * @var array
     */
    protected $casts = [
        'closed' => 'boolean',
        'reviewed' => 'boolean',
        'address' => 'json',
        'ship_data' => 'json',
        'extra' => 'json',
    ];

    /**
     * 需要转换成日期的属性。
     *
     * @var array
     */
    protected $dates = [
        'paid_at',
    ];

    /**
     * 注册一个模型创建事件监听函数，用于自动生成订单的流水号。
     */
    protected static function boot()
    {
        parent::boot(); // TODO: Change the autogenerated stub
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(function ($model) {
            // 如果模型的 no 字段为空
            if (!$model->no) {
                // 调用 findAvailableNo 生成订单流水号
                $model->no = static::findAvailableNo();
                // 如果生成失败，则终止创建订单
                if (!$model->no) {
                    return false;
                }
            }
        });
    }

    /**
     * 获取用户
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取订单项目
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * 获取优惠券
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function couponCode()
    {
        return $this->belongsTo(CouponCode::class);
    }

    /**
     * 生成订单流水号
     *
     * @return bool|string
     */
    public static function findAvailableNo()
    {
        // 订单流水号前缀
        $prefix = date('YmdHis');
        for ($i = 0; $i < 10; $i++) {
            // 随机生成 6 位的数字
            $no = $prefix.str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            //判断是否已经存在
            if (!static::query()->where('no', $no)->exists()) {
                return $no;
            }
        }
        \Log::warning('find order no failed');
        return false;
    }

    /**
     * 获取退款单号
     *
     * @return string
     */
    public static function getAvailableRefundNo()
    {
        do {
            // Uuid 类可以用生成大概率不重复的字符串
            $no = Uuid::uuid4()->getHex();
            // 为了避免重复我们在生成之后在数据库中查询看看是否已经在相同的退款订单号
        } while (self::query()->where('refund_no', $no)->exists());
        return $no;
    }

}