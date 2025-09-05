<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\WithdrawalResource\WithdrawalCollection;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller
{
    /**
     * 获取提现列表
     * @param Request $request
     * @param WithdrawalService $service
     * @return array
     */
    public function index(Request $request, WithdrawalService $service)
    {
        try {
            $data = $service->getList();
            return $this->success(new WithdrawalCollection($data));
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

}
