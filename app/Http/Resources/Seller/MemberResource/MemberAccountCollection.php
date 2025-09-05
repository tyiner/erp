<?php

namespace App\Http\Resources\Seller\MemberResource;

use Illuminate\Http\Resources\Json\ResourceCollection;

class MemberAccountCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'list' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'openid' => $item->openid,
                    'realname' => $item->realname,
                    'phone' => $item->phone,
                    'idcard' => $item->idcard,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
                    'total_commission' => 0,
                    'withdrawal_money' => 0,
                    'usable_money' => 0,
                    'frozen_money' => 0,
                ];
            }),
            'total' => $this->total(), // 数据总数
            'per_page' => $this->perPage(), // 每页数量
            'current_page' => $this->currentPage(), // 当前页码
        ];
    }
}
