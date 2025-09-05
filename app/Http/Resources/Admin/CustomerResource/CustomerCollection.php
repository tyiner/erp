<?php

namespace App\Http\Resources\Admin\CustomerResource;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerCollection extends ResourceCollection
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
                    'name' => $item->name,
                    'contact_name' => $item->contact_name,
                    'phone' => $item->phone,
                    'province_id' => $item->province_id,
                    'city_id' => $item->city_id,
                    'region_id' => $item->region_id,
                    'area_info' => $item->province->name . ',' . $item->city->name . ',' . $item->region->name,
                    'address' => $item->address,
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
