<?php

namespace App\Http\Controllers\Purchase\StockPlan;

use App\Http\Controllers\Controller;
use App\Services\Purchase\StockPlan\StockPlanService;
use Illuminate\Http\Request;

/**
 * Class StockPlanController
 * @package App\Http\Controllers\Purchase\StockPlan
 */
class StockPlanController extends Controller
{
    private $service;

    public function __construct(StockPlanService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取备货计划单详情
     * @param Request $request
     */
    public function getById(Request $request)
    {
        $id = $request->get('id');
        success($this->service->getById($id));
    }
}
