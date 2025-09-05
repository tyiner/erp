<?php

namespace App\Http\Resources\Seller\MemberResource;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'openid' => $this->openid,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'last_login_time' => $this->last_login_time,
            'ip' => $this->ip,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'parent' => $this->parent,
            'head' => $this->head,
            'child_count' => 0,
            'group_count' => 0,
            'total_gold_commission' => 0,
            'total_head_commission' => 0,
            'store' => [
                'id' => $this->store->id,
                'store_name' => $this->store->store_name,
            ],
        ];
    }
}
