<?php

namespace App\Http\Resources\Seller\WithdrawalResource;

use Illuminate\Http\Resources\Json\ResourceCollection;

class WithdrawalCollection extends ResourceCollection
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
                    'withdrawal_no' => $item->withdrawal_no,
                    'member_name' => $item->member->realname,
                    'member_phone' => $item->member->phone,
                    'member_idcard' => $item->member->idcard,
                    'member_type' => $item->member->type,
                    'member_type_cn' => $item->member->type_cn,
                    'withdrawal_way' => $item->withdrawal_way,
                    'withdrawal_way_cn' => $item->withdrawal_way_cn,
                    'withdrawal_account' => $item->withdrawal_account,
                    'withdrawal_realname' => $item->withdrawal_realname,
                    'withdrawal_money' => $item->withdrawal_money,
                    'withdrawal_fee' => $item->withdrawal_fee,
                    'withdrawal_status' => $item->withdrawal_status,
                    'withdrawal_status_cn' => $item->withdrawal_status_cn,
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
