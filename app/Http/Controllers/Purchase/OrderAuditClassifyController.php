<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\OrderAuditClassifyService;
use Illuminate\Http\Request;

/**
 * Class OrderAuditClassifyController
 * @package App\Http\Controllers\Purchase
 */
class OrderAuditClassifyController extends Controller
{
    protected $service;

    public function __construct(OrderAuditClassifyService $service)
    {
        $this->service = $service;
    }

    /**
     * 按条件获取单据列表
     * @param Request $request
     */
    public function getList(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'order_name' => 'string|max:45',
            'order_type' => 'int',
            'classify' => 'int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        success($this->service->getList($data));
    }

    /**
     * 根据单据类型获取单据审核等级
     * @param Request $request
     */
    public function getByType(Request $request)
    {
        $rules = [
            'order_type' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $orderType = data_get($request, 'order_type');
        success($this->service->getByType($orderType));
    }

    /**
     * 新建新类型仓库单据
     * @param Request $request
     */
    public function store(Request $request)
    {
        $rules = [
            'order_name' => 'required|string|max:45',
            'order_type' => 'required|int',
            'classify' => 'required|int|between:2,5',
        ];
        $msg = [
            'order_name.required' => '单据名称不能为空',
            'order_type.required' => '单据类型序号不能为空',
            'order_type.int' => '单据类型序号必须为数字',
            'classify.required' => '审核等级不能为空',
            'classify.between' => '非法的审核等级',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $data = $request->all();
        $ret = $this->service->add($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 根据id获取详情
     * @param Request $request
     */
    public function getDetail(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $msg = [
            'id.required' => '单据id不能为空',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $id = $request->input('id');
        success($this->service->getById($id));
    }

    /**
     * 更新单据审核分类
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            'id' => 'required|int',
            'order_name' => 'required|string|max:45',
            'order_type' => 'required|int',
            'classify' => 'required|int|between:2,5',
        ];
        $msg = [
            'id.required' => 'id不能为空',
            'order_name.required' => '单据名称不能为空',
            'order_type.required' => '单据类型序号不能为空',
            'order_type.int' => '单据类型序号必须为数字',
            'classify.required' => '审核等级不能为空',
            'classify.between' => '非法的审核等级',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $data = $request->all();
        $ret = $this->service->update($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 删除单据审核分类
     * @param Request $request
     */
    public function delete(Request $request)
    {
        $rules = [
            'ids' => "required|array",
        ];
        $msg = [
            'ids.array' => 'ids必须为数组',
        ];
        $this->handleValidateRequest($request, $rules, $msg);
        $ids = $request->input("ids");
        $ret = $this->service->delete($ids);
        if (!$ret) {
            error("删除失败");
        }
        success("删除成功");
    }
}
