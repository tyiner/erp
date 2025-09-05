<?php

namespace App\Http\Resources\Seller\DistributionResource;

use Illuminate\Http\Resources\Json\JsonResource;

class DistributionSettingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'gold_name' => $this->gold_name,
            'gold_commission_name' => $this->gold_commission_name,
            'gold_commission_unit' => $this->gold_commission_unit,
            'gold_commission_open' => $this->gold_commission_open,
            'gold_commission_rate' => $this->gold_commission_rate,
            'promote_name' => $this->promote_name,
            'promote_commission_name' => $this->promote_commission_name,
            'promote_commission_unit' => $this->promote_commission_unit,
            'promote_commission_open' => $this->promote_commission_open,
            'promote_commission_rate' => $this->promote_commission_rate,
            'upgrade_gold_open' => $this->upgrade_gold_open,
            'upgrade_gold_price' => $this->upgrade_gold_price,
            'upgrade_gold_trigger' => $this->upgrade_gold_trigger,
        ];
    }
}
