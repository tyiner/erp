<?php

namespace App\Http\Resources\Seller\DistributionResource;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DistributionCollection extends ResourceCollection
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
            'list' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'order_no' => $item->order->order_no,
                    'order_status' => $item->order->order_status,
                    'order_status_cn' => $item->order->order_status_cn,
                    'order_refund_status' => $item->order->order_refund_status,
                    'order_refund_status_cn' => $item->order->order_refund_status_cn,
                    'order_price' => $item->order->order_price,
                    'gold_commission' => $item->gold_commission,
                    'gold_user_name' => $item->gold_user->name,
                    'gold_current_user_name' => $item->gold_current_user->name,
                    'promote_commission' => $item->promote_commission,
                    'promote_user_name' => $item->promote_user->name,
                    'promote_current_user_name' => $item->promote_current_user->name,
                    'commission_total' => $item->gold_commission + $item->promote_commission,
                    'commission_status' => $item->commission_status,
                    'commission_status_cn' => $item->commission_status_cn,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
                ];
            }),
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
