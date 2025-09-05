<?php

namespace App\Http\Controllers\PurchaseStore;

use App\Http\Controllers\Controller;
use App\Services\PurchaseStore\PurchaseStoreService;
use Illuminate\Http\Request;

/**
 * Class PurchaseStoreController
 * @package App\Http\Controllers\PurchaseStore
 */
class PurchaseStoreController extends Controller
{
    private $service;

    public function __construct(PurchaseStoreService $service)
    {
        $this->service = $service;
    }

    /**
     * 根据id获取入库单详情
     * @param Request $request
     */
    public function getPurchaseStore(Request $request)
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        success($this->service->getById($id));
    }
}
