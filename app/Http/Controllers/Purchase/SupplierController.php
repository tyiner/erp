<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\SupplierService;
use Illuminate\Http\Request;

/**
 * Class CompanyController
 * @package App\Http\Controllers\Purchase
 */
class SupplierController extends Controller
{
    protected $service;

    public function __construct(SupplierService $service)
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
            "no" => "required|string|max:45",
            "link_name" => "required|string|max:45",
            "address" => "string|max:225",
            "phone" => "int",
            "status" => "int",
            "remark" => "max:225",
            "duty" => 'max:45',
            "bank" => 'max:20',
            "bank_account" => 'max:20',
            "account_holder" => 'max:20',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $sameNameSupplier = $this->service->getByCondition(['name' => data_get($data, 'name')]);
        if (!is_null($sameNameSupplier)) {
            error('供应商名已存在');
        }
        $sameNoSupplier = $this->service->getByCondition(['no' => data_get($data, 'no')]);
        if (!is_null($sameNoSupplier)) {
            error('供应商编号已存在');
        }
        $ret = $this->service->create($data);
        success([
            'id' => data_get($ret, 'id')
        ]);
    }

    /**
     * @param Request $request
     */
    public function destroy(Request $request)
    {
        $data = $request->only('ids');
        $this->service->delete($data);
        success("删除成功");
    }

    /**
     * 更新公司名称或编号
     * @param Request $request
     */
    public function update(Request $request)
    {
        $rules = [
            "id" => "required|int",
            "name" => "required|string|max:45",
            "no" => "required|string|max:45",
            "link_name" => "required|string|max:45",
            "address" => "string|max:225",
            "phone" => "int",
            "status" => "int",
            "duty" => "max:20",
            "account_holder" => "max:20",
            "bank" => "max:20",
            "bank_account" => "max:20",
            "remark" => "max:225",
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->only([
            'id',
            'name',
            'no',
            'phone',
            'link_name',
            'address',
            'status',
            'duty',
            'account_holder',
            'bank',
            'bank_account',
            'remark',
        ]);
        $sameNameSupplier = $this->service->getByCondition(['name' => data_get($data, 'name')]);
        if (!is_null($sameNameSupplier) && $sameNameSupplier->id != $data['id']) {
            error('供应商名已存在');
        }
        $sameNoSupplier = $this->service->getByCondition(['no' => data_get($data, 'no')]);
        if (!is_null($sameNoSupplier) && data_get($sameNoSupplier, 'id') != $data['id']) {
            error('供应商编号已存在');
        }
        $this->service->update($data);
        success([
            'id' => data_get($data, 'id')
        ]);
    }

    /**
     * 获取分页
     * @param Request $request
     */
    public function getList(Request $request)
    {
        $conditions = $request->only(['status', 'ids', 'name', 'no', 'limit']);
        if (!empty(data_get($conditions, 'ids'))) {
            data_set($conditions, 'ids', explode(',', $conditions['ids']));
        }
        success($this->service->getList($conditions));
    }
}
