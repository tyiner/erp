<?php

namespace App\Http\Resources\Home\GoodsResource;

use App\Traits\HelperTrait;
use Illuminate\Http\Resources\Json\ResourceCollection;

class GoodsSearchCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    use HelperTrait;

    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'goods_no' => $item->goods_no,
                    'goods_name' => $item->goods_name,
                    'goods_subname' => $item->goods_subname,
                    'goods_price' => $item->store_goods->goods_price,
                    'goods_stock' => $item->store_goods->goods_stock,
                    'goods_sale' => $item->store_goods->goods_sale,
                    'goods_master_image' => $this->thumb($item->goods_master_image, 300),
                    'order_comment_count' => $item->order_comment_count,
                ];
            }),
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
