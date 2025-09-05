<?php

namespace App\Http\Resources\Home\OrderResource;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderGoodsResource extends JsonResource
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
        ];
    }

}
