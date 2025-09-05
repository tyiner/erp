<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HelperTrait;

    protected $appends = [
        'refund_type_cn',
        'refund_verify_cn',
        'refund_step_cn',
        'exchange_goods_list',
    ];

    /** @var int 退款 */
    const TYPE_REFUND = 0;
    /** @var int 退货退款 */
    const TYPE_RETURN_REFUND = 1;
    /** @var int 换货 */
    const TYPE_EXCHANGE = 2;

    /** @var int 已取消 */
    const VERIFY_CANCEL = -1;
    /** @var int 处理中 */
    const VERIFY_WAIT_PROCESS = 0;
    /** @var int 已同意 */
    const VERIFY_AGREE = 1;
    /** @var int 已拒绝 */
    const VERIFY_REFUSE = 2;

    /** @var int 等待用户填写单号 */
    const STEP_WAIT_USER = 0;
    /** @var int 等待商家处理 */
    const STEP_WAIT_MERCHANT = 1;
    /** @var int 商家确定收货并退款或换货 */
    const STEP_MERCHANT_CONFIRM = 2;
    /** @var int 用户确定收货订单成功 */
    const STEP_USER_CONFIRM = 3;

    /** @var int 退款中 */
    const STATUS_DEFAULT = 0;
    /** @var int 已退款 */
    const STATUS_SUCCESS = 1;

    /**
     * 关联用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Models\Member', 'id', 'user_id');
    }

    /**
     * 关联店铺
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function store()
    {
        return $this->hasOne('App\Models\Store', 'id', 'store_id');
    }

    /**
     * 关联订单
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order()
    {
        return $this->hasOne('App\Models\Order', 'id', 'order_id');
    }

    /**
     * 关联换货单
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function exchange_order()
    {
        return $this->hasOne('App\Models\Order', 'id', 'exchange_order_id');
    }

    /**
     * 关联订单商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function order_goods()
    {
        return $this->hasMany('App\Models\OrderGoods', 'order_id', 'order_id');
    }

    /**
     * 获取售后类型选项
     * @return string[]
     */
    public static function getRefundTypeOptions(): array
    {
        return [
            self::TYPE_REFUND => '退款',
            self::TYPE_RETURN_REFUND => '退货退款',
            self::TYPE_EXCHANGE => '换货',
        ];
    }

    /**
     * 获取售后类型文本
     * @return string
     */
    public function getRefundTypeCnAttribute(): string
    {
        $options = self::getRefundTypeOptions();
        return $options[$this->refund_type] ?? "未知($this->refund_type)";
    }

    /**
     * 获取售后状态选项
     * @return string[]
     */
    public static function getRefundVerifyOptions(): array
    {
        return [
            self::VERIFY_CANCEL => '已取消',
            self::VERIFY_WAIT_PROCESS => '处理中',
            self::VERIFY_AGREE => '已同意',
            self::VERIFY_REFUSE => '已拒绝',
        ];
    }

    /**
     * 获取售后状态文本
     * @return string
     */
    public function getRefundVerifyCnAttribute(): string
    {
        $options = self::getRefundVerifyOptions();
        return $options[$this->refund_verify] ?? "未知($this->refund_verify)";
    }

    /**
     * 获取售后步骤选项
     * @return string[]
     */
    public static function getRefundStepOptions(): array
    {
        return [
            self::STEP_WAIT_USER => '等待用户填写单号',
            self::STEP_WAIT_MERCHANT => '等待商家处理',
            self::STEP_MERCHANT_CONFIRM => '商家确定收货并退款或换货',
            self::STEP_USER_CONFIRM => '用户确定收货订单成功',
        ];
    }

    /**
     * 获取售后步骤文本
     * @return string
     */
    public function getRefundStepCnAttribute(): string
    {
        $options = self::getRefundStepOptions();
        return $options[$this->refund_step] ?? "未知($this->refund_step)";
    }

    /**
     * 获取换购商品信息
     * @return array
     */
    public function getExchangeGoodsListAttribute(): array
    {
        $goodsList = json_decode($this->goods_list);
        if (empty($goodsList) || !is_array($goodsList)) {
            return [];
        }

        $data = [];
        foreach ($goodsList as $v) {
            $fromGoods = Goods::find($v->from_goods_id);
            $toGoods = Goods::find($v->to_goods_id);
            $item = [
                'from_goods' => [
                    'goods_id' => $fromGoods->id,
                    'goods_name' => $fromGoods->goods_name,
                    'goods_master_image' => $this->thumb($fromGoods->goods_master_image, 150),
                    'goods_price' => $fromGoods->goods_price,
                    'buy_num' => $v->buy_num ?? 1,
                ],
                'to_goods' => [
                    'goods_id' => $toGoods->id,
                    'goods_name' => $toGoods->goods_name,
                    'goods_master_image' => $this->thumb($toGoods->goods_master_image, 150),
                    'goods_price' => $toGoods->goods_price,
                    'buy_num' => $v->buy_num ?? 1,
                ],
            ];
            $data[] = $item;
        }

        return $data;
    }
}
