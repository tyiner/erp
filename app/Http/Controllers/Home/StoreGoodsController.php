<?php
namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\GoodsBrandService;
use App\Services\StoreGoodsService;
use Illuminate\Http\Request;

class StoreGoodsController extends Controller
{
    public function getBrandList(Request $request, GoodsBrandService $service) {
        try {
            $list = $service->getBrands()['data'];
            $data = [
                'list' => $list,
            ];

            return [
                'code' => 0,
                'message' => 'success',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'code' => 1,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function getGoodsList(Request $request, StoreGoodsService $service) {
        try {
            $filters = [];
            if ($request->store_id) {
                $filters[] = ['sg.store_id', '=', $request->store_id];
            }

            if ($request->brand_id) {
                $filters[] = ['g.brand_id', '=', $request->brand_id];
            }

            if ($request->class_id) {
                $filters[] = ['g.class_id', '=', $request->class_id];
            }

            $options = [
                'page' => $request->page ?? 1,
                'pageSize' => $request->per_page ?? 30,
            ];

            [$list, $total] = $service->getList($filters, $options);
            $data = [
                'data' => $list,
                'total' => $total,
                'per_page' => $options['pageSize'], // 每页数量
                'current_page' => $options['page'], // 当前页
            ];
            return $this->success($data);
        } catch (\Exception $e) {
            return [
                'code' => 1,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function getGoodsDetail(Request $request, StoreGoodsService $service, $id) {
        try {
            $id = $request->id;
            if (empty($id)) {
                throw new \Exception('id不能为空');
            }

            $goods = $service->get($id);

            return [
                'code' => 0,
                'message' => 'success',
                'data' => $goods,
            ];
        } catch (\Exception $e) {
            return [
                'code' => 1,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
