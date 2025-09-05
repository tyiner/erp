<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\CompanyService;
use Illuminate\Http\Request;

/**
 * Class CompanyController
 * @package App\Http\Controllers\Purchase
 */
class CompanyController extends Controller
{
    protected $service;

    public function __construct(CompanyService $service)
    {
        $this->service = $service;
    }

    /**
     * 创建公司
     * @param Request $request
     */
    public function create(Request $request)
    {
        $rules = [
            "name" => "required|string|max:45",
            "company_no" => "required|string|max:45",
            "status" => "int|in:0,1",
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $sameNameCompany = $this->service->getByCondition(['name' => data_get($data, 'name')]);
        if (!is_null($sameNameCompany)) {
            error('公司名已存在');
        }
        $sameNoCompany = $this->service->getByCondition(['company_no' => data_get($data, 'company_no')]);
        if (!is_null($sameNoCompany)) {
            error('公司编号已存在');
        }
        $ret = $this->service->create($data);
        success(['id' => data_get($ret, 'id')]);
    }

    /**
     * 删除公司
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
     * 更新公司名称或编号
     * @param Request $request
     */
    public function update(Request $request)
    {
        $data = $request->only(['id', 'name', 'company_no', 'status', 'user', 'address', 'address_detail', 'phone']);
        $sameNameCompany = $this->service->getByCondition(['name' => data_get($data, 'name')]);
        if (!is_null($sameNameCompany) && $sameNameCompany->id != data_get($data, 'id')) {
            error('公司名已存在');
        }
        $sameNoCompany = $this->service->getByCondition(['company_no' => data_get($data, 'company_no')]);
        if (!is_null($sameNoCompany) && $sameNoCompany->id != data_get($data, 'id')) {
            error('公司编号已存在');
        }
        $ret = $this->service->update($data);
        if ($ret) {
            success($data['id']);
        }
        error("更新失败");
    }

    /**
     * 获取分页
     * @param Request $request
     */
    public function getList(Request $request)
    {
        $conditions = $request->only(['status', 'ids', 'name', 'company_no', 'limit']);
        if (!empty(data_get($conditions, 'ids'))) {
            data_set($conditions, 'ids', explode(',', $conditions['ids']));
        }
        $list = $this->service->getList($conditions);
        success($list);
    }

    /**
     * 按公司分组获取仓库地址
     * @param Request $request
     */
    public function getLocations(Request $request)
    {
        $rules = [
            "limit" => "int",
            "page" => "int",
            "company_ids" => "string",
            "company_name" => "string|max:45",
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only(["limit", "page", "company_ids", "company_name"]);
        if (data_get($data, 'company_ids')) {
            $data['company_ids'] = explode(',', data_get($data, 'company_ids'));
        }
        $list = $this->service->getLocationList($data);
        success($list);
    }
}
