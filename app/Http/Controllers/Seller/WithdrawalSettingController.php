<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\WithdrawalResource\WithdrawalSettingResource;
use App\Services\WithdrawalSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WithdrawalSettingController extends Controller
{
    /**
     * 获取提现设置
     * @param Request $request
     * @param WithdrawalSettingService $service
     * @return array
     */
    public function index(Request $request, WithdrawalSettingService $service) {
        try {
            $data = $service->getByStoreId($this->getStoreId());
            return $this->success(new WithdrawalSettingResource($data));
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 保存提现设置
     * @param Request $request
     * @param WithdrawalSettingService $service
     * @return array
     */
    public function store(Request $request, WithdrawalSettingService $service) {
        try {
            $data = $service->save($this->getStoreId(), $request->post());
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

}
