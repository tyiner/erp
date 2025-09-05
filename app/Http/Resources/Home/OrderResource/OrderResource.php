<?php

namespace App\Http\Resources\Home\OrderResource;

use App\Services\KuaibaoService;
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
        $kb_service = new KuaibaoService();
        return [
            'id' => $this->id,
            'order_type' => $this->order_type,
            'order_no' => $this->order_no,
            'order_status' => $this->order_status,
            'order_status_cn' => $this->order_status_cn,
            'receive_name' => $this->receive_name,
            'receive_tel' => $this->receive_tel,
            'receive_area' => $this->receive_area,
            'receive_address' => $this->receive_address,
            'total_price' => $this->total_price,
            'freight_money' => $this->freight_money,
            'remark' => $this->remark,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'delivery_no' => $this->delivery_no,
            'delivery_code' => $this->delivery_code,
            'delivery_name' => $this->delivery_name,
            'delivery_list' => []/*empty($this->delivery_no) ? [] : $kb_service->getExpressInfo($this->delivery_no, $this->delivery_code, $this->receive_tel)*/,
            'order_goods' => $this->order_goods->map(function ($q) {
                return [
                    'goods_id' => $q->goods_id,
                    'goods_image' => $q->goods_image,
                    'goods_name' => $q->goods_name,
                    'goods_price' => $q->goods_price,
                    'sku_name' => $q->sku_name,
                    'buy_num' => $q->buy_num,
                ];
            }),
            'order_pay' => $this->order_pay->map(function ($q) {
                return [
                    'id' => $q->id,
                    'pay_no' => $q->pay_no,
                    'out_trade_no' => $q->out_trade_no,
                    'payment_name' => $q->payment_name,
                    'payment_name_cn' => $q->payment_name_cn,
                    'pay_status' => $q->pay_status,
                    'pay_time' => $q->pay_time,
                ];
            }),
            'refund' => $this->refund->map(function ($q) {
                return [
                    'id' => $q->id,
                    'refund_type' => $q->refund_type,
                    'refund_reason' => $q->refund_reason,
                    'refund_verify' => $q->refund_verify,
                    'refund_step' => $q->refund_step,
                    'refund_status' => $q->refund_status,
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
