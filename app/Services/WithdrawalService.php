<?php
namespace App\Services;

use App\Models\Withdrawal;

class WithdrawalService extends BaseService
{
    /**
     * 获取提现列表
     * @return mixed
     */
    public function getList() {
        $withdrawal = new Withdrawal();
        if ($this->isSeller()) {
            $withdrawal = $withdrawal->where('store_id', $this->getStoreId());
        }
        $withdrawal = $withdrawal->orderByDesc('id')
            ->paginate(request()->per_page ?? 30);
        return $withdrawal;
    }
}
