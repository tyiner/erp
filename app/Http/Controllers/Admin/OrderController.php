<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OrderResource\OrderCollection;
use App\Http\Resources\Admin\OrderResource\OrderResource;
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
        $rs = $order_service->getOrders('admin');
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
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order_model, $id)
    {
        $idArray = array_filter(explode(',', $id), function ($item) {
            return is_numeric($item);
        });
        $order_model->destroy($idArray);
        return $this->success(__('base.success'));
    }
}
