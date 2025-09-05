<?php

namespace App\Http\Resources\Seller\WithdrawalResource;

use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalSettingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'withdrawal_min' => $this->withdrawal_min,
            'withdrawal_max' => $this->withdrawal_max,
            'withdrawal_date' => $this->withdrawal_date,
            'withdrawal_count' => $this->withdrawal_count,
            'withdrawal_wx_wallet' => $this->withdrawal_wx_wallet,
            'withdrawal_auto' => $this->withdrawal_auto,
            'withdrawal_rate' => $this->withdrawal_rate,
        ];
    }
}
