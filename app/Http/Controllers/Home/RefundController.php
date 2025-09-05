<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\Home\RefundResource\RefundResource;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    /**
     * 申请售后
     * @param RefundService $service
     * @return array
     */
    public function store(Request $request, RefundService $service)
    {
        try {
            $orderId = $request->order_id;
            $refundType = $request->refund_type;
            $refundReason = $request->refund_reason;
            $images = $request->images;
            $goodsList = $request->goods_list ?? [];

            if (empty($orderId)) {
                $this->throwException('order_id不能为空', 4530);
            }

//            if (!isset($refundType)) {
//                $this->throwException('退款类型不能为空', 4530);
//            }

            if (empty($refundReason)) {
                $this->throwException('售后原因不能为空', 4530);
            }

            $data = $service->add($orderId, $refundType, $refundReason, $images, $goodsList);
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 获取售后信息
     * @param RefundService $service
     * @param $id 订单ID
     * @return array
     */
    public function show(RefundService $service, $id)
    {
        try {
            $refund = $service->getRefundInfoByOrderId($id);
            return $this->success(new RefundResource($refund));
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    // 这里的ID 都是OrderId
    public function update(RefundService $refund_service, $id)
    {
        $rs = $refund_service->edit($id);
        return $rs['status'] ? $this->success([], $rs['msg']) : $this->error($rs['msg']);
    }

    /**
     * 填写退货快递
     * @param Request $request
     * @param RefundService $service
     * @param $id
     * @return array
     */
    public function delivery(Request $request, RefundService $service, $id) {
        try {
            $deliveryNo = $request->delivery_no;
            $deliveryCode = $request->delivery_code;

            if (empty($deliveryNo)) {
                $this->throwException('快递单号不能为空', 5011);
            }

            if (empty($deliveryCode)) {
                $this->throwException('快递公司编码不能为空', 5012);
            }

            $rs = $service->userDelivery($id, $deliveryNo, $deliveryCode);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 取消申请售后
     * @param Request $request
     * @param RefundService $service
     * @param $id
     * @return array
     */
    public function cancel(Request $request, RefundService $service, $id) {
        try {
            $rs = $service->cancel($id);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }
}
