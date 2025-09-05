<?php

namespace App\Http\Resources\Admin\OrderResource;

use App\Services\OrderService;
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
                    'order_name' => $item->order_name,
                    'order_image' => $item->order_image,
                    'total_price' => $item->total_price,
                    'store_name' => $item->store->store_name,
                    'buyer_name' => $item->member->openid,
                    'order_status' => $item->order_status,
                    'order_status_cn' => $item->order_status_cn,
                    'order_source' => $item->order_source,
                    'order_source_cn' => $item->order_source_cn,
                    'delivery_type' => $item->delivery_type,
                    'delivery_type_cn' => $item->delivery_type_cn,
                    'delivery_time' => $item->delivery_time,
                    'audit_time' => $item->audit_time,
                    'audit_time_cn' => $item->audit_time ?? '未审核',
                    'order_refund_status' => $item->order_refund_status,
                    'receipt_time' => $item->receipt_time,
                    'comment_time' => $item->comment_time,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
                    'order_pay' => $item->order_pay->map(function ($q) {
                        return [
                            'id' => $q->id,
                            'pay_no' => $q->pay_no,
                            'out_trade_no' => $q->out_trade_no,
                            'payment_name' => $q->payment_name,
                            'payment_name_cn' => $q->payment_name_cn,
                            'pay_type_cn' => $q->pay_type_cn,
                            'pay_status' => $q->pay_status,
                            'pay_time' => $q->pay_time,
                        ];
                    }),
                    'member' => $item->member,
                    'refund' => $item->refund->map(function ($q) {
                        return [
                            'id' => $q->id,
                            'refund_no' => $q->refund_no,
                            'refund_type' => $q->refund_type,
                            'refund_type_cn' => $q->refund_type_cn,
                            'refund_reason' => $q->refund_reason,
                            'refund_verify' => $q->refund_verify,
                            'refund_verify_cn' => $q->refund_verify_cn,
                            'refund_step' => $q->refund_step,
                            'delivery_no' => $q->delivery_no,
                            'delivery_code' => $q->delivery_code,
                            're_delivery_no' => $q->re_delivery_no,
                            're_delivery_code' => $q->re_delivery_code,
                            'refuse_reason' => $q->refuse_reason,
                            'created_at' => $q->created_at->format('Y-m-d H:i:s'),
                        ];
                    }),
                    'store' => $item->store,
                ];
            }),
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
