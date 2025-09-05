<?php

namespace App\Http\Resources\Home\OrderResource;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'order_type' => $item->order_type,
                    'order_no' => $item->order_no,
                    'order_price' => $item->order_price,
                    'total_price' => $item->total_price,
                    'order_status' => $item->order_status,
                    'created_at' => $item->created_at->format('Y-m-d H:i'),
                    'order_goods' => $item->order_goods->map(function ($q) {
                        return [
                            'goods_id' => $q->goods_id,
                            'goods_image' => $q->goods_image,
                            'goods_name' => $q->goods_name,
                            'goods_price' => $q->goods_price,
                            'sku_name' => $q->sku_name,
                            'buy_num' => $q->buy_num,
                            'total_price' => $q->total_price,
                        ];
                    }),
                    'order_pay' => $item->order_pay->map(function ($q) {
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
//                    'store' => $item->store,
//                    'member' => $item->member,
                    'refund' => $item->refund->map(function ($q) {
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
            }),
            // 'data'=>$this->collection,
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
