<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DepartmentResource\DepartmentCollection;
use App\Services\Purchase\DepartmentService;
use Illuminate\Http\Request;

/**
 * 部门管理
 * Class DepartmentController
 * @package App\Http\Controllers\Purchase
 */
class DepartmentController extends Controller
{
    private $service;

    public function __construct(DepartmentService $service)
    {
        $this->service = $service;
    }

    public function create(Request $request)
    {
        $rules = [
            'company_id' => 'required|int',
            'name' => 'required|string',
            'status' => 'int',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(['company_id', 'name', 'status']);
        $ret = $this->service->create($data);
        if ($ret) {
            success($ret);
        }
        error("添加失败");
    }

    /**
     * 获取列表
     * @param Request $request
     */
    public function getList(Request $request)
    {
        $conditions = $request->only(['status', 'ids', 'name', 'page', 'limit', 'company_id']);
        if (!empty(data_get($conditions, 'ids'))) {
            data_set($conditions, 'ids', explode(',', $conditions['ids']));
        }
        $list = $this->service->getList($conditions);
        success(new DepartmentCollection($list));
    }

    /**
     * 根据id值删除部门
     * @param Request $request
     */
    public function destroy(Request $request)
    {
        $idArray = array_filter(explode(',', $request->ids), function ($item) {
            return is_numeric($item);
        });
        $this->service->delete($idArray);
        success("删除成功");
    }

    /**
     * 更新部门
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rule = [
            'id' => 'required|int',
            'company_id' => 'required|int',
            'status' => 'int',
            'name' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rule);
        $data = $request->only(['id', 'company_id', 'name', 'status']);
        $ret = $this->service->update($data);
        if ($ret) {
            success(data_get($data, 'id'));
        }
        error("更新失败");
    }
}
