<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\DistributionResource\DistributionCollection;
use App\Services\DistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DistributionController extends Controller
{
    /**
     * 获取分销结算列表
     * @param Request $request
     * @param DistributionService $service
     * @return array
     */
    public function index(Request $request, DistributionService $service)
    {
        try {
            $data = $service->getList();
            return $this->success(new DistributionCollection($data));
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

}
