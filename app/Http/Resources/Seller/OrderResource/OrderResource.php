<?php

namespace App\Http\Resources\Seller\OrderResource;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_type' => $this->order_type,
            'order_no' => $this->order_no,
            'receive_name' => $this->receive_name,
            'receive_tel' => $this->receive_tel,
            'receive_area' => $this->receive_area,
            'receive_address' => $this->receive_address,
            'delivery_type' => $this->delivery_type,
            'delivery_type_cn' => $this->delivery_type_cn,
            'delivery_no' => $this->delivery_no,
            'delivery_code' => $this->delivery_code,
            'delivery_code_cn' => $this->delivery_code_cn,
            'delivery_time' => $this->delivery_time,
            'order_status' => $this->order_status,
            'order_status_cn' => $this->order_status_cn,
            'order_status_step' => $this->getOrderStatusStep(),
            'order_source' => $this->order_source,
            'order_source_cn' => $this->order_source_cn,
            'total_price' => $this->total_price,
            'freight_money' => $this->freight_money,
            'coupon_money' => $this->coupon_money,
            'audit_time' => $this->audit_time,
            'close_time' => $this->close_time,
            'order_refund_status' => $this->order_refund_status,
            'remark' => $this->remark,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'order_goods' => $this->order_goods->map(function ($q) {
                return [
                    'goods_id' => $q->goods_id,
                    'goods_no' => $q->goods->goods_no,
                    'goods_image' => $q->goods_image,
                    'goods_name' => $q->goods_name,
                    'goods_price' => $q->goods_price,
                    'sku_name' => $q->sku_name ?? '-',
                    'buy_num' => $q->buy_num,
                    'total_price' => $q->total_price,
                    'unit' => $q->goods->unit ?? '-',
                ];
            }),
            'order_pay' => $this->order_pay->map(function ($q) {
                return [
                    'id' => $q->id,
                    'pay_no' => $q->pay_no,
                    'out_trade_no' => $q->out_trade_no,
                    'payment_name' => $q->payment_name,
                    'payment_name_cn' => $q->payment_name_cn,
                    'pay_type_cn' => $q->pay_type_cn,
                    'pay_status' => $q->pay_status,
                    'pay_status_cn' => $q->pay_status ? '已支付' : '未支付',
                    'pay_time' => $q->pay_time,
                ];
            }),
            'member' => $this->member,
            'refund' => $this->refund->map(function ($q) {
                return [
                    'id' => $q->id,
                    'refund_no' => $q->refund_no,
                    'refund_type' => $q->refund_type,
                    'refund_type_cn' => $q->refund_type_cn,
                    'refund_reason' => $q->refund_reason,
                    'refund_verify' => $q->refund_verify,
                    'refund_verify_cn' => $q->refund_verify_cn,
                    'refund_step' => $q->refund_step,
                    'refund_step_cn' => $q->refund_step_cn,
                    'delivery_no' => $q->delivery_no,
                    'delivery_code' => $q->delivery_code,
                    're_delivery_no' => $q->re_delivery_no,
                    're_delivery_code' => $q->re_delivery_code,
                    'refuse_reason' => $q->refuse_reason,
                    'created_at' => $q->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ];
    }
}
