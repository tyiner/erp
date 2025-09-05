<?php

namespace App\Http\Resources\Home\FavoriteResource;

use App\Traits\HelperTrait;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FavoriteCollection extends ResourceCollection
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
            'data' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'goods_id' => $item->goods_id,
                    'goods_name' => $item->goods->goods_name ?? '',
                    'mobile_image' => $item->goods->mobile_image ?? '',
                    'goods_master_image' => $item->goods->goods_master_image ?? '',
                    'goods_price' => $item->goods->goods_price ?? '0.00',
                ];
            }),
            // 'data'=>$this->collection,
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
