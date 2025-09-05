<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    /**
     * 修改订单状态（临时存在，方便测试）
     * @param Request $request
     * @return array
     */
    public function editOrderStatus(Request $request)
    {
        $orderId = $request->order_id;
        $orderStatus = $request->order_status;

        $order = Order::find($orderId);
        $order->order_status = $orderStatus;
        $rs = $order->save();
        return $this->success($rs);
    }

    /**
     * 获取推广者
     * @param Request $request
     * @return array|null
     */
    public function getPromoterInfo(Request $request) {
        try {
            $promoterId = $this->getPromoterId($request->user_id);
            $member = Member::find($promoterId);
            return $this->success($member);
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }
}
