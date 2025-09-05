<?php

namespace App\Http\Resources\Seller\MemberResource;

use App\Traits\HelperTrait;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Log;

class MemberCommissionCollection extends ResourceCollection
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
                    'id' => $item->id,
                    'type' => $item->type,
                    'openid' => $item->openid,
                    'phone' => $item->phone,
                    'avatar' => $item->avatar,
                    'name' => $item->name,
                    'nickname' => $item->nickname,
                    'last_login_time' => $item->last_login_time,
                    'ip' => $item->ip,
                    'status' => $item->status,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
                    'parent' => $item->parent,
                    'head' => $item->head,
                    'child_count' => 0,
                    'group_count' => 0,
                    'total_gold_commission' => 0,
                    'total_head_commission' => 0,
                ];
            }),
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
