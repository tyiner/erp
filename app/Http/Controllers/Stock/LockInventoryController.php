<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\LockInventoryService;
use Illuminate\Http\Request;

/**
 * Class LockInventoryController
 * @package App\Http\Controllers\Stock
 */
class LockInventoryController extends Controller
{
    private $service;

    public function __construct(LockInventoryService $service)
    {
        $this->service = $service;
    }

    /**
     * 新建锁定仓库
     * @param Request $request
     */
    public function create(Request $request)
    {
        $rule = [
            'location_no' => 'string|required|max:45',
            'goods_no' => 'int|required',
            'lock_num' => 'int|required',
        ];
    }

    /**
     * 更新锁定仓
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rule = [
            'location_no' => 'int|required',
            'goods_no' => 'int|required',
            'lock_num' => 'int|required',
        ];
        $this->handleValidateRequest($request, $rule);
        $data = $request->only('location_no', 'goods_no', 'lock_num');
        $ret = $this->service->update($data);
        success($ret);
    }

    /**
     * 删除锁定仓
     * @param Request $request
     */
    public function destroy(Request $request)
    {
        $rules = [
            'location_id' => 'int',
            'goods_no' => 'required|int',
            'location_no' => 'required|int'
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only('location_id', 'goods_no', 'location_no');
        $ret = $this->service->destroy($data);
        if ($ret) {
            success("锁定仓删除成功");
        }
        error("锁定仓删除失败");
    }

    /**
     * 获取锁定仓数据
     * @param Request $request
     */
    public function get(Request $request)
    {
    }
}
