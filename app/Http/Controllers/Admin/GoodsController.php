<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\TreeHelper;
use App\Http\Controllers\Controller;
use App\Models\Goods;
use App\Models\GoodsSku;
use App\Services\GoodsClassService;
use App\Services\GoodsService;
use App\Services\StoreService;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Class GoodsController
 * @package App\Http\Controllers\Admin
 */
class GoodsController extends Controller
{
    protected $service;

    public function __construct(GoodsService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $rules = [
            'limit' => 'int',
            'page' => 'int',
            'goods_verify' => 'int|in:0,1',
            'goods_name' => 'string',
            'goods_no' => 'int',
            'goods_status' => 'int|in:0,1',
            'is_software' => 'int|in:1,2',
            'purchase_name' => 'string|max:45',
        ];
        // 条件筛选
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->getGoodsList($data);
        success($ret);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        $rules = [
            'goods_name' => 'string|max:45',                           // 商品名
            'purchase_name' => 'required|string|max:45',                    // 供应链采购商品名
            'goods_type' => 'required|array',                               // 存货类型 成品/1； 半成品/2；内销/3；采购/4；
            'classify' => 'required|int|between:0,20',                      // 存货分类；手机膜：1；备品膜：2；服务：3；
            'goods_no' => 'required|int',                                   // 商品编号
            'is_software' => 'required|int',                                // 商品是否为软件；否：0；是：1；
            'attribute' => 'required|string|max:45',                        // 供应链部商品规格属性
            'goods_subname' => 'string',                                    // 商品介绍
            'brand_id' => 'int',                                            // 商品品牌
            'class_id' => 'int',                                            // 商品分类
            'mobile_image' => 'string',                                     // 机型图片
            'goods_price' => 'numeric|min:0',                               // 商品价格
            'goods_market_price' => 'numeric|min:0',                        // 商品市场价
            'cost_price' => 'numeric|min:0',                                // 商品成本价
            'goods_weight' => 'numeric|min:0',                              // 商品重量
            'goods_stock' => 'numeric|min:0',                               // 商品库存
            'stock_guard' => 'numeric|min:0',                               // 库存警戒线
            'goods_content' => 'string',                                    // 商品内容详情
            'goods_content_mobile' => 'string',                             // 商品内容详情（手机）
            'goods_status' => 'int|in:0,1',                                 // 商品上架状态
            'freight_id' => 'numeric|min:0',                                // 运费模版ID
            'goods_images' => 'array',
            'purchase_limit' => 'numeric|min:0',                            // 限购数量
            'sort' => 'int|min:0',                // 排序
            'is_coupon' => 'int',                // 使用优惠券
            'purchase_start' => 'date_format:Y-m-d H:i:s',                // 限购开始时间
            'purchase_end' => 'date_format:Y-m-d H:i:s',                // 限购截止时间
            'purchase_point' => 'int|min:0',                // 购买赠送积分
            'decr_stock_mode' => 'int|min:1',                // 减库存方式
            'pack_length' => 'int|min:1',                  // 包装长
            'pack_width' => 'int|min:1',                   // 包装宽
            'pack_height' => 'int|min:1',                  // 包装高
            'banner_height' => 'int|min:1',                // 橱窗高度
            'unit' => 'required|string|max:10',            //商品单位
            'skuList.*.goods_image' => 'string',
            'skuList.*.spec_id' => 'int',
            'skuList.*.sku_name' => 'string',
            'skuList.*.goods_price' => 'numeric|min:0',
            'skuList.*.goods_market_price' => 'numeric|min:0',
            'skuList.*.goods_stock' => 'int|min:0',
            'skuList.*.goods_weight' => 'numeric|min:0',
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->add($data);
        if ($ret) {
            success(['id' => data_get($ret, 'id')]);
        }
        error("添加商品失败");
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        $rules = [
            'id' => 'required|int',
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        $info = $this->service->getStoreGoodsInfo($id);
        if ($info['status']) {
            return $this->success($info['data']);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, GoodsService $goods_service, $id)
    {
        $info = $goods_service->edit($id);
        if ($info['status']) {
            return $this->success([], __('goods.add_success'));
        }
        return $this->error(__('goods.add_error'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id, Goods $goods_model, GoodsSku $goods_sku_model)
    {
        $idArray = array_filter(explode(',', $id), function ($item) {
            return is_numeric($item);
        });
        $goods_list = $goods_model->select('store_id', 'goods_no')->whereIn('id', $idArray)->get();
        $keys = [];
        foreach ($goods_list as $v) {
            if ($v['store_id'] != $this->get_store(true)) {
                return $this->error(__('goods.del_error'));
            }
            if (!is_null(data_get($v, 'goods_no'))) {
                $keys[] = $v['goods_no'] . '_goods';
            }
        }
        $redis = Redis::connection('cache');
        $redis->del($keys);
        $goods_model->whereIn('id', $idArray)->delete();
        $goods_sku_model->whereIn('goods_id', $idArray)->delete();
        return $this->success([], __('base.success'));
    }

    // 商家拥有商品栏目信息
    public function store_goods_classes(StoreService $store_service)
    {
        $goods_classes = $store_service->getStoreGoodsClasses($this->get_store(true));
        return $this->success($goods_classes['data']);
    }

    /**
     * 获取商品分类
     * @param GoodsClassService $service
     * @return array
     */
    public function goodsClasses(GoodsClassService $service)
    {
        $data = [];
        TreeHelper::goodsChildren($service->getGoodsClasses()['data'], $data);
        return $this->success($data);
    }

    // 商品图片上传
    public function goods_upload()
    {
        $upload_service = new UploadService();
        $rs = $upload_service->goods();
        if ($rs['status']) {
            return $this->success($rs['data'], $rs['msg']);
        } else {
            return $this->error($rs['msg']);
        }
    }

    // 机型图片上传
    public function mobile_upload()
    {
        $upload_service = new UploadService();
        $rs = $upload_service->mobile();
        if ($rs['status']) {
            return $this->success($rs['data'], $rs['msg']);
        } else {
            return $this->error($rs['msg']);
        }
    }

    /**
     * 发布商品
     * @param Request $request
     * @return array
     */
    public function publish(Request $request)
    {
        try {
            $id = explode(',', $request->id);
            $rs = Goods::whereIn('id', $id)->update([
                'goods_status' => $request->type,
            ]);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    /**
     * 商品详情
     * @param Request $request
     * @return array
     */
    public function detail(Request $request)
    {
        $locationId = $request->only('location_id');
        $goodsNo = $request->input('goods_no');
        if (!empty($locationId)) {
            $storage = $this->service->getStorageNumByLocationId($locationId);
            $actual = data_get($storage, $locationId['location_id'] . '.' . $goodsNo, 0);
            $lockNum = $this->service->getLockNumByLocationId($locationId);
            $usable = $actual - data_get($lockNum, $locationId['location_id'] . '.' . $goodsNo, 0);
        } else {
            $current = $this->service->getCurrentUser();
            $companyId = data_get($current, 'company_id');
            $storage = $this->service->getStorageNumByCompanyId($companyId);
            $actual = data_get($storage, $goodsNo, 0);
            $lockNum = $this->service->getLockNumByCompanyId($companyId);
            $usable = $actual - data_get($lockNum, $goodsNo, 0);
        }
        try {
            $goods = new Goods();
            if (isset($request->goods_no)) {
                $goods = $goods->where('goods_no', $request->goods_no);
            }
            $goods = $goods->with('goods_attr')->first();
            $goods->existing_num = $actual;   //仓库实际数量
            $goods->usable_num = $usable;   //仓库可用数量
            return $this->success($goods);
        } catch (\Exception $e) {
            Log::error($e->getCode() . '-' . $e->getMessage());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 批量获取商品
     * @param Request $request
     */
    public function batchDetail(Request $request)
    {
        $rules = [
            "goods_nos" => "required|array",
            "location_id" => "required|int"
        ];
        $this->handleValidateRequest($request, $rules);
        $data = $request->all();
        $ret = $this->service->batchDetail($data);
        success($ret);
    }
}
