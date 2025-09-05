<?php

namespace App\Services;

use App\Models\WithdrawalSetting;

class WithdrawalSettingService extends BaseService
{

    /**
     * 获取店铺提现设置（当没有时，立即创建）
     * @param int $storeId
     * @return mixed
     */
    public function getByStoreId(int $storeId)
    {
        if (!$storeId) {
            $this->throwException('store_id不能为空', 6112);
        }
        $withdrawalSetting = new WithdrawalSetting();
        $withdrawalSetting = $withdrawalSetting->where('store_id', $storeId)->first();
        if (!$withdrawalSetting) {
            $withdrawalSetting = new WithdrawalSetting();
            $withdrawalSetting->store_id = $storeId;
            $withdrawalSetting->save();
            $withdrawalSetting = WithdrawalSetting::find($withdrawalSetting->id);
        }
        return $withdrawalSetting;
    }

    /**
     * 保存店铺提现设置
     * @param int $storeId
     * @param array $post
     * @return mixed
     */
    public function save(int $storeId, array $post)
    {
        $commissionSetting = $this->getByStoreId($storeId);
        $commissionSetting->withdrawal_min = $post['withdrawal_min'];
        $commissionSetting->withdrawal_max = $post['withdrawal_max'];
        $commissionSetting->withdrawal_date = $post['withdrawal_date'];
        $commissionSetting->withdrawal_count = $post['withdrawal_count'];
        $commissionSetting->withdrawal_wx_wallet = $post['withdrawal_wx_wallet'];
        $commissionSetting->withdrawal_auto = $post['withdrawal_auto'];
        $commissionSetting->withdrawal_rate = $post['withdrawal_rate'];
        return $commissionSetting->save();
    }

}
