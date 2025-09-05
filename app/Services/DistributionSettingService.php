<?php

namespace App\Services;

use App\Models\DistributionSetting;

class DistributionSettingService extends BaseService
{
    /**
     * 获取店铺分销设置（当没有时，立即创建）
     * @param int $storeId
     * @return mixed
     */
    public function getByStoreId(int $storeId)
    {
        if (!$storeId) {
            $this->throwException('store_id不能为空', 6112);
        }
        $distributionSetting = new DistributionSetting();
        $distributionSetting = $distributionSetting->where('store_id', $storeId)->first();
        if (!$distributionSetting) {
            $distributionSetting = new DistributionSetting();
            $distributionSetting->store_id = $storeId;
            $distributionSetting->save();
            $distributionSetting = DistributionSetting::find($distributionSetting->id);
        }
        return $distributionSetting;
    }

    /**
     * 保存店铺分销设置
     * @param int $storeId
     * @param array $post
     * @return mixed
     */
    public function save(int $storeId, array $post)
    {
        $distributionSetting = $this->getByStoreId($storeId);
        $distributionSetting->gold_name = $post['gold_name'];
        $distributionSetting->gold_commission_name = $post['gold_commission_name'];
        $distributionSetting->gold_commission_unit = $post['gold_commission_unit'];
        $distributionSetting->gold_commission_open = $post['gold_commission_open'];
        $distributionSetting->gold_commission_rate = $post['gold_commission_rate'];
        $distributionSetting->promote_name = $post['promote_name'];
        $distributionSetting->promote_commission_name = $post['promote_commission_name'];
        $distributionSetting->promote_commission_unit = $post['promote_commission_unit'];
        $distributionSetting->promote_commission_open = $post['promote_commission_open'];
        $distributionSetting->promote_commission_rate = $post['promote_commission_rate'];
        $distributionSetting->upgrade_gold_open = $post['upgrade_gold_open'];
        $distributionSetting->upgrade_gold_price = $post['upgrade_gold_price'];
        $distributionSetting->upgrade_gold_trigger = $post['upgrade_gold_trigger'];
        return $distributionSetting->save();
    }

}
