<?php
namespace App\Services;

use App\Models\Distribution;

class DistributionService extends BaseService
{

    /**
     * 获取分销
     * @param int $storeId
     * @param int $orderId
     * @return mixed
     * @throws \Exception
     */
    public function get(int $storeId, int $orderId) {
        if (empty($storeId) || empty($orderId)) {
            $this->throwException('store_id和order_id不能为空', 6211);
        }
        $distribution = Distribution::where(['store_id' => $storeId, 'order_id' => $orderId])->first();
        return $distribution;
    }

    /**
     * 添加分销
     * @param int $storeId
     * @param int $orderId
     * @param array $post
     * @throws \Exception
     */
    public function add(int $storeId, int $orderId, float $orderPrice) {
        $distribution = $this->get($storeId, $orderId);
        if (!$distribution) {
            $distribution = new Distribution();
        }
        $distribution->store_id = $storeId;
        $distribution->order_id = $orderId;

        $distributionSettingService = new DistributionSettingService();
        $distributionSetting = $distributionSettingService->getByStoreId($storeId);

        $goldCommission = round($orderPrice * $distributionSetting->gold_commission_rate, 2);
        $promoteCommission = round($orderPrice * $distributionSetting->promote_commission_rate, 2);

        $user = $this->getUser();
        $goldUserId = $user->inviter_id;
        $goldCurrentUserId = $user->inviter_id;
        $promoteUserId = $this->getPromoterId($user->inviter_id);
        $promoteCurrentUserId = $this->getPromoterId($user->inviter_id);

        $distribution->gold_commission = $goldCommission;
        $distribution->gold_user_id = $goldUserId;
        $distribution->gold_current_user_id = $goldCurrentUserId;
        $distribution->promote_commission = $promoteCommission;
        $distribution->promote_user_id = $promoteUserId;
        $distribution->promote_current_user_id = $promoteCurrentUserId;
        $distribution->save();
    }

    /**
     * 获取分销结算列表
     * @return mixed
     */
    public function getList() {
        $distribution = new Distribution();
        if ($this->isSeller()) {
            $distribution = $distribution->where('store_id', $this->getStoreId());
        }
        $distribution = $distribution->orderByDesc('id')
            ->paginate(request()->per_page ?? 30);
        return $distribution;
    }
}
