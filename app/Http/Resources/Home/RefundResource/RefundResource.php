<?php

namespace App\Http\Resources\Home\RefundResource;

use App\Http\Resources\Home\OrderResource\OrderGoodsResource;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
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
            'refund_no' => $this->refund_no,
            'refund_type' => $this->refund_type,
            'refund_reason' => $this->refund_reason,
            'refund_verify' => $this->refund_verify,
            'refund_step' => $this->refund_step,
            'refund_status' => $this->refund_status,
            'delivery_no' => $this->delivery_no,
            'delivery_code' => $this->delivery_code,
            'refuse_reason' => $this->refuse_reason,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
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
            'order_price' => $this->order->order_price,
            'order_remark' => $this->order->remark,
            'exchange_order' => new OrderGoodsResource($this->exchange_order),
            'exchange_goods' => $this->exchange_goods_list,
        ];
    }

}
