<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\DistributionResource\DistributionSettingResource;
use App\Services\DistributionSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DistributionSettingController extends Controller
{
    /**
     * 获取店铺分销设置
     * @param Request $request
     * @param DistributionSettingService $service
     * @return array
     */
    public function index(Request $request, DistributionSettingService $service) {
        try {
            $data = $service->getByStoreId($this->getStoreId());
            return $this->success(new DistributionSettingResource($data));
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 保存店铺分销设置
     * @param Request $request
     * @param DistributionSettingService $service
     * @return array
     */
    public function store(Request $request, DistributionSettingService $service) {
        try {
            $data = $service->save($this->getStoreId(), $request->post());
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

}
