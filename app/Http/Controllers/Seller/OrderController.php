<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\OrderResource\OrderCollection;
use App\Http\Resources\Seller\OrderResource\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(OrderService $order_service)
    {
        $rs = $order_service->getOrders('seller');
        return $rs['status'] ? $this->success(new OrderCollection($rs['data'])) : $this->error($rs['msg']);
    }

    /**
     * 获取订单详情
     * @param OrderService $service
     * @param $id
     * @return array
     */
    public function show(OrderService $service, $id)
    {
        try {
            $order = $service->getOrderInfoById($id);
            return $this->success(new OrderResource($order));
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order_model, $id)
    {
        $store_id = $this->get_store(true);
        $order_model = $order_model->where(['id' => $id, 'store_id' => $store_id])->first();
        $order_model->delivery_code = $request->delivery_code ?? 'yd';
        $order_model->delivery_no = $request->delivery_no ?? '123456';

        // 判断是否需要修改订单状态
        if ($order_model->order_status == 2) {
            $order_model->order_status = 3;
            $order_model->delivery_time = now();
        }
        $order_model->save();
        return $this->success([], __('base.success'));
    }

    /**
     * 审核
     * @param Request $request
     * @return array
     */
    public function audit(Request $request)
    {
        try {
            $id = explode(',', $request->id);
            $rs = Order::whereIn('id', $id)->update([
                'audit_time' => date('Y-m-d H:i:s'),
            ]);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    /**
     * 取消订单
     * @param Request $request
     * @return array
     */
    public function cancel(Request $request)
    {
        try {
            $id = explode(',', $request->id);
            $rs = Order::whereIn('id', $id)->update([
                'order_status' => Order::STATUS_CLOSE,
            ]);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    /**
     * 发货&填写订单快递信息
     * @param Request $request
     * @param OrderService $service
     * @param $id
     * @return array
     */
    public function delivery(Request $request, OrderService $service, $id) {
        try {
            $deliveryNo = $request->delivery_no;
            $deliveryCode = $request->delivery_code;

            if (empty($deliveryNo)) {
                $this->throwException('快递单号不能为空', 5011);
            }

            if (empty($deliveryCode)) {
                $this->throwException('快递公司编码不能为空', 5012);
            }

            $rs = $service->fillDelivery($id, $deliveryNo, $deliveryCode);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 商家确定收货并换货
     * @param Request $request
     * @param OrderService $service
     * @param $id
     * @return array
     */
    public function exchange(Request $request, OrderService $service, $id) {
        try {
            $rs = $service->addExchangeOrder($id);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }
}
