<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\LocationService;
use Illuminate\Http\Request;

/**
 * Class LocationController
 * @package App\Http\Controllers\Stock
 */
class LocationController extends Controller
{
    private $service;

    public function __construct(LocationService $service)
    {
        $this->service = $service;
    }

    /**
     * 新建仓库
     * @param Request $request
     */
    public function create(Request $request)
    {
        $rules = [
            "name" => "required|string",
            "location_no" => "required|int",
            "company_id" => 'required|int',
            "link_user" => "required|string|max:45",
            "link_phone" => "required|numeric",
            "address" => 'required|string',
            'type' => 'required|int',
            'address_detail' => 'required|string',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only([
            'name',
            'location_no',
            'company_id',
            'link_user',
            'link_phone',
            'address',
            'address_detail',
            'remark',
            'status',
            'type',
        ]);
        $ret = $this->service->getByConds(['name' => data_get($data, 'name')]);
        if ($ret->count() > 0) {
            error("仓库名已存在");
        }
        $ret = $this->service->getByConds(['location_no' => data_get($data, 'location_no')]);
        if ($ret->count() > 0) {
            error("仓库序号已存在");
        }
        success($this->service->save($data));
    }

    /**
     * 更新仓库
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            "id" => "required|int",
            "name" => "required|string",
            "location_no" => "required|int",
            "status" => "in:0,1",
            'type' => 'in:0,1',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only([
            'id',
            'name',
            'location_no',
            'link_user',
            'link_phone',
            'address',
            'address_detail',
            'status',
            'company_id',
            'type',
            'remark'
        ]);
        success($this->service->update($data));
    }

    /**
     * 删除仓库
     * @param Request $request
     */
    public function delete(Request $request)
    {
        $rules = [
            'ids' => 'required',
        ];
        $this->handleValidateRequest($request, $rules);
        $ids = explode(',', $request->input('ids'));
        success($this->service->delete($ids));
    }

    /**
     * 获取仓库列表
     * @param Request $request
     */
    public function getList(Request $request)
    {
        $data = $request->only(['name', 'ids', 'location_no', 'limit', 'status', 'company_no']);
        if (data_get($data, 'ids')) {
            data_set($data, 'ids', explode(',', $data['ids']));
        }
        $ret = $this->service->getList($data);
        success($ret);
    }
}
