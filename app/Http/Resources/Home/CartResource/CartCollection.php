<?php

namespace App\Http\Resources\Home\CartResource;

use App\Traits\HelperTrait;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CartCollection extends ResourceCollection
{
    use HelperTrait;

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
                    'cart_id' => $item->id,
                    'goods_id' => $item->goods_id,
                    'goods_name' => $item->goods->goods_name,
                    'goods_image' => $this->thumb($item->goods->goods_master_image,150),
                    'goods_price' => $item->store_goods->goods_price ?? '0.00',
                    'buy_num' => $item->buy_num,
                ];
            }),
            // 'data'=>$this->collection,
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
