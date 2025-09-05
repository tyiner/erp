<?php

namespace App\Http\Resources\Seller\GoodsResource;

use App\Models\GoodsClass;
use App\Services\GoodsService;
use App\Traits\HelperTrait;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Log;

class GoodsTabSellerCollection extends ResourceCollection
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
        $goods_service = new GoodsService();
        return [
            'data' => $this->collection->map(function ($item) {

                $class_id = [];
                $class = GoodsClass::where('id', $item->goods_class->pid)->first();
                if (isset($class['pid'])) {
                    $class_id[] = $class['pid'];
                }
                $class_id[] = $item->goods_class->pid;
                $class_id[] = $item->goods_class->id;
                return [
                    'id' => $item->id,
                    'goods_name' => $item->goods_name,
                    'goods_subname' => $item->goods_subname,
                    'goods_price' => $item->store_goods->goods_price,
                    'goods_stock' => $item->store_goods->goods_stock,
                    'goods_sale' => $item->goods_sale,
                    'goods_master_image' => $this->thumb($item->goods_master_image, 150),
                    'brand_name' => $item->goods_brand->name,
                    'class_name' => $item->goods_class->name,
                    'class_id' => $class_id,
                    'goods_no' => $item->goods_no,
                    'goods_status' => $item->goods_status,
                    'goods_verify' => $item->goods_verify,
                    'is_recommend' => $item->is_recommend, // 总后台可以不要
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
                ];
            }),
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
            // 统计
            'count' => $goods_service->getCount(),
        ];
    }
}
