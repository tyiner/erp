<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
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
            return $this->success($refund);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    // 这里的ID 都是OrderId
    public function update(RefundService $refund_service, $id)
    {
        $rs = $refund_service->edit($id, 'seller');
        return $rs['status'] ? $this->success([], $rs['msg']) : $this->error($rs['msg']);
    }

    /**
     * 售后同意
     * @param RefundService $service
     * @param $id
     * @return array
     */
    public function agree(RefundService $service, $id)
    {
        try {
            $data = $service->agree($id);
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 售后拒绝
     * @param Request $request
     * @param RefundService $service
     * @param $id
     * @return array
     */
    public function refuse(Request $request, RefundService $service, $id)
    {
        try {
            $refuseReason = $request->refuse_reason;

            if (empty($refuseReason)) {
                $this->throwException('拒绝原因不能为空', 5602);
            }

            $data = $service->refuse($id, $refuseReason);
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 商户确定收货并退款
     * @param RefundService $service
     * @param $id
     * @return array
     */
    public function refund(RefundService $service, $id)
    {
        try {
            $data = $service->refund($id);
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     *
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

            $rs = $service->merchantDelivery($id, $deliveryNo, $deliveryCode);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }
}
