<?php

namespace App\Services;

use App\Http\Resources\Home\GoodsResource\GoodsListCollection;
use App\Http\Resources\Home\GoodsResource\GoodsSearchCollection;
use App\Http\Resources\Home\GoodsResource\SeckillGoodsCollection;
use App\Models\Goods;
use App\Models\GoodsAttr;
use App\Models\GoodsSku;
use App\Models\GoodsSpec;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class GoodsService extends BaseService
{
    protected $model;

    public function __construct(Goods $model)
    {
        $this->model = $model;
    }

    protected $status = ['goods_status' => 1, 'goods_verify' => 1];

    /**
     * 获取商品信息
     * @param array $data
     * @return array
     */
    public function batchDetail(array $data): array
    {
        $locationId = data_get($data, 'location_id');
        $goodsNos = data_get($data, 'goods_nos');
        if ($locationId) {
            $storage = collect($this->getStorageNumByLocationId([$locationId]))->first();
            $lockNum = collect($this->getLockNumByLocationId([$locationId]))->first();
        } else {
            $current = $this->getCurrentUser();
            $companyId = data_get($current, 'company_id');
            $storage = $this->getStorageNumByCompanyId($companyId);
            $lockNum = $this->getLockNumByCompanyId($companyId);
        }
        $list = [];
        foreach ($goodsNos as $goodsNo) {
            $goods = $this->getGoodsByNo($goodsNo);
            unset($goods['goods_name']);
            $list[$goodsNo] = $goods;
            $list[$goodsNo]['existing_num'] = data_get($storage, $goodsNo, 0);
            $list[$goodsNo]['usable_num'] = data_get($storage, $goodsNo, 0)
                - data_get($lockNum, $goodsNo, 0);
        }
        return $list;
    }

    /**
     * 新建商品
     * @param array $data
     * @return Goods
     */
    public function add(array $data): Goods
    {
        $config_service = new ConfigService;
        $storeId = $this->get_store(true);
        // 判断是否开启添加商品审核
        $ret = $this->model->where('goods_no', data_get($data, 'goods_no'))->first();
        if (!is_null($ret)) {
            error("相同编号商品已经存在");
        }
        if (!empty($config_service->getFormatConfig('goods_verify'))) {
            $data['goods_verify'] = 2;
        }
        $data['send_status'] = 0;
        $data['store_id'] = intval($storeId);
        $data['goods_images'] = json_encode(data_get($data, 'goods_images', '[]'));
        $data['goods_type'] = json_encode($data['goods_type']);
        try {
            DB::beginTransaction();
            $this->model->fill($data)->save();
            // 规格处理
            if (isset($data['skuList']) && !empty($data['skuList'])) {
                $skuData = [];
                foreach ($data['skuList'] as $k => $v) {
                    $skuData[$k]['goods_image'] = $v['goods_image'] ?? ''; // sku图片
                    $skuData[$k]['spec_id'] = implode(',', $v['spec_id']); // sku 属性
                    $skuData[$k]['sku_name'] = implode(',', $v['sku_name']); // sku名称
                    $skuData[$k]['goods_price'] = abs($v['goods_price'] ?? 0); // sku价格
                    $skuData[$k]['goods_market_price'] = abs($v['goods_market_price'] ?? 0); // sku市场价
                    $skuData[$k]['goods_stock'] = abs($v['goods_stock'] ?? 0); // sku库存
                    $skuData[$k]['goods_weight'] = abs($v['goods_weight'] ?? 0); // sku 重量
                }
                $this->model->goods_skus()->createMany($skuData);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            error("商品添加失败");
        }
        return $this->model;
    }

    /**
     * 按条件获取商品列表
     * @param array $data
     * @return mixed
     */
    public function getGoodsList(array $data)
    {
        $limit = data_get($data, 'limit', 20);
        $query = $this->model;
        if (!is_null(data_get($data, 'goods_name'))) {
            $query = $query->where('goods_name', 'like', '%' . data_get($data, 'goods_name') . '%');
        }
        if (!is_null(data_get($data, 'purchase_name'))) {
            $query = $query->where('purchase_name', 'like', '%' . data_get($data, 'purchase_name') . '%');
        }
        if (data_get($data, 'goods_no')) {
            $query = $query->where('goods_no', data_get($data, 'goods_no'));
        }
        if (!is_null(data_get($data, 'goods_status'))) {
            $query = $query->where('goods_status', data_get($data, 'goods_status'));
        }
        if (!is_null(data_get($data, 'goods_verify'))) {
            $query = $query->where('goods_verify', data_get($data, 'goods_verify'));
        }
        if (!is_null(data_get($data, 'is_software'))) {
            $query = $query->where('is_software', data_get($data, 'is_software'));
        }
        $list = $query->orderBy('created_at', 'desc')->paginate($limit);
        return $list;
    }

    // 商品编辑
    public function edit($goods_id)
    {
        $redis = Redis::connection('cache');
        $redis->flushall();
        $goods_model = new Goods();
        $config_service = new ConfigService;
        $goods_skus_model = new GoodsSku;
        $store_id = $this->get_store(true);
        $goods_model = $goods_model
            ->where([
//                'store_id'=>$store_id,
                'id' => $goods_id
            ])
            ->first();
        $redis = Redis::connection('cache');
        $keys[] = data_get($goods_model, 'goods_no') . '_goods';
        $redis->del($keys);

        // 商品名
        if (isset(request()->goods_name) && !empty(request()->goods_name)) {
            $goods_model->goods_name = request()->goods_name;
        }
        // 供应链部商品名
        if (isset(request()->purchase_name) && !empty(request()->purchase_name)) {
            $goods_model->purchase_name = request()->purchase_name;
        }
        //商品属性
        if (isset(request()->attribute) && !empty(request()->attribute)) {
            $goods_model->attribute = request()->attribute;
        }
        //存货类型 成品：1；软件：2; 半成品：3；内销：4；采购：5
        if (isset(request()->goods_type) && !empty(request()->goods_type)) {
            $goods_model->goods_type = request()->goods_type;
        }
        //存货分类；手机膜：1；备品膜：2；服务：3；
        if (isset(request()->classify) && !empty(request()->classify)) {
            $goods_model->classify = request()->classify;
        }
        // 副标题
        if (isset(request()->goods_subname) && !empty(request()->goods_subname)) {
            $goods_model->goods_subname = request()->goods_subname;
        }
        // 商品编号
        if (isset(request()->goods_no) && !empty(request()->goods_no)) {
            $goods_model->goods_no = request()->goods_no;
        }
        // 商品品牌
        if (isset(request()->brand_id) && !empty(request()->brand_id)) {
            $goods_model->brand_id = request()->brand_id;
        }
        // 商品分类
        if (isset(request()->classInfo[2]['id']) && !empty(request()->classInfo[2]['id'])) {
            $goods_model->class_id = request()->classInfo[2]['id'];
        }
        // 机型图片
        if (isset(request()->mobile_image) && !empty(request()->mobile_image)) {
            $goods_model->mobile_image = request()->mobile_image;
        }
        // 商品主图
        if (isset(request()->goods_master_image) && !empty(request()->goods_master_image)) {
            $goods_model->goods_master_image = request()->goods_master_image;
        }
        // 商品价格
        if (isset(request()->goods_price) && !empty(request()->goods_price)) {
            $goods_model->goods_price = abs(request()->goods_price ?? 0);
        }
        // 商品市场价
        if (isset(request()->goods_market_price) && !empty(request()->goods_market_price)) {
            $goods_model->goods_market_price = abs(request()->goods_market_price ?? 0);
        }
        // 商品成本价
        if (isset(request()->cost_price) && !empty(request()->cost_price)) {
            $goods_model->cost_price = abs(request()->cost_price ?? 0);
        }
        // 商品重量
        if (isset(request()->goods_weight) && !empty(request()->goods_weight)) {
            $goods_model->goods_weight = abs(request()->goods_weight ?? 0);
        }
        // 商品库存
        if (isset(request()->goods_stock) && !empty(request()->goods_stock)) {
            $goods_model->goods_stock = abs(request()->goods_stock ?? 0);
        }
        // 库存警戒线
        if (isset(request()->stock_guard) && !empty(request()->stock_guard)) {
            $goods_model->stock_guard = abs(request()->stock_guard ?? 0);
        }
        // 排序
        if (isset(request()->sort) && !empty(request()->sort)) {
            $goods_model->sort = abs(request()->sort ?? 0);
        }
        // 商品内容详情
        if (isset(request()->goods_content) && !empty(request()->goods_content)) {
            $goods_model->goods_content = request()->goods_content;
        }
        // 商品内容详情（手机）
        if (isset(request()->goods_content_mobile) && !empty(request()->goods_content_mobile)) {
            $goods_model->goods_content_mobile = request()->goods_content_mobile;
        }
        // 商品上架状态
        if (isset(request()->goods_status)) {
            $goods_model->goods_status = abs(request()->goods_status ?? 0);
        }
        // 商品上架状态
        if (isset(request()->freight_id)) {
            $goods_model->freight_id = abs(request()->freight_id ?? 0);
        }
        // 商品图片
        if (isset(request()->goods_images) && !empty(request()->goods_images)) {
            $goods_model->goods_images = implode(',', request()->goods_images ?? []);
        }

        if (isset(request()->is_coupon) && !empty(request()->is_coupon)) {
            $goods_model->is_coupon = abs(request()->is_coupon ?? 0);
        }

        if (isset(request()->purchase_limit) && !empty(request()->purchase_limit)) {
            $goods_model->purchase_limit = abs(request()->purchase_limit ?? 0);
        }

        if (isset(request()->purchase_start) && !empty(request()->purchase_start)) {
            $goods_model->purchase_start = request()->purchase_start;
        }

        if (isset(request()->purchase_end) && !empty(request()->purchase_end)) {
            $goods_model->purchase_end = request()->purchase_end;
        }

        if (isset(request()->purchase_point) && !empty(request()->purchase_point)) {
            $goods_model->purchase_point = abs(request()->purchase_point ?? 0);
        }

        if (isset(request()->decr_stock_mode) && !empty(request()->decr_stock_mode)) {
            $goods_model->decr_stock_mode = abs(request()->decr_stock_mode ?? 1);
        }

        if (isset(request()->unit) && !empty(request()->unit)) {
            $goods_model->unit = request()->unit;
        }

        if (isset(request()->pack_length) && !empty(request()->pack_length)) {
            $goods_model->pack_length = abs(request()->pack_length ?? 0);
        }

        if (isset(request()->pack_width) && !empty(request()->pack_width)) {
            $goods_model->pack_width = abs(request()->pack_width ?? 0);
        }

        if (isset(request()->pack_height) && !empty(request()->pack_height)) {
            $goods_model->pack_height = abs(request()->pack_height ?? 0);
        }

        if (isset(request()->banner_height) && !empty(request()->banner_height)) {
            $goods_model->banner_height = abs(request()->banner_height ?? 0);
        }

        // 判断是否开启添加商品审核
        if (!empty($config_service->getFormatConfig('goods_verify'))) {
            // 如果是内容标题修改则进行审核（暂时不写）
            $goods_model->goods_verify = 2;
        }

        try {
            DB::beginTransaction();
            $goods_model = $goods_model->save();
            // 规格处理
            if (isset(request()->skuList) && !empty(request()->skuList)) {
                $skuData = [];
                $skuId = []; // 修改的skuID 不存在则准备删除
                foreach (request()->skuList as $k => $v) {
                    if (isset($v['id']) && !empty($v['id'])) {
                        // 如果ID不为空则代表存在此sku 进行修改
                        $skuId[] = $v['id'];
                        $goods_skus_model->where('goods_id', $goods_id)->where('id', $v['id'])->update([
                            'goods_image' => $v['goods_image'] ?? '',// sku图片
                            'spec_id' => implode(',', $v['spec_id']), // sku 属性
                            'sku_name' => implode(',', $v['sku_name']), // sku名称
                            'goods_price' => abs($v['goods_price'] ?? 0), // sku价格
                            'goods_market_price' => abs($v['goods_market_price'] ?? 0), // sku市场价
                            'goods_stock' => abs($v['goods_stock'] ?? 0), // sku库存
                            'goods_weight' => abs($v['goods_weight'] ?? 0), // sku 重量
                        ]);
                    } else {
                        // 否则进行插入数据库
                        $skuData[$k]['goods_image'] = $v['goods_image'] ?? ''; // sku图片
                        $skuData[$k]['spec_id'] = implode(',', $v['spec_id']); // sku 属性
                        $skuData[$k]['sku_name'] = implode(',', $v['sku_name']); // sku名称
                        $skuData[$k]['goods_price'] = abs($v['goods_price'] ?? 0); // sku价格
                        $skuData[$k]['goods_market_price'] = abs($v['goods_market_price'] ?? 0); // sku市场价
                        $skuData[$k]['goods_stock'] = abs($v['goods_stock'] ?? 0); // sku库存
                        $skuData[$k]['goods_weight'] = abs($v['goods_weight'] ?? 0); // sku 重量
                    }
                }

                // 如果ID不为空则代表存在此sku 进行修改
                if (!empty($skuId)) {
                    $goods_skus_model->where('goods_id', $goods_id)->whereNotIn('id', $skuId)->delete();
                }

                // 新建不存在sku进行插入数据库
                if (!empty($skuData)) {
                    $goods_model = new Goods;
                    $goods_model = $goods_model->find($goods_id);
                    $goods_model->goods_skus()->createMany($skuData);
                }

            } else {
                // 清空所有sku
                $goods_skus_model->where('goods_id', $goods_id)->delete();
            }
            DB::commit();
            return $this->format([], __('goods.add_success'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('qwlog')->debug('商品编辑失败');
            Log::channel('qwlog')->debug($e->getMessage());
            return $this->format_error(__('goods.add_error'));
        }
    }

    // 修改商品的状态审核
    public function editGoodsVerify($goods_id, $status = 1, $msg = '')
    {
        $goods_model = new Goods;
        $goods_model = $goods_model->where('id', $goods_id);
        $data = [
            'goods_verify' => $status,
        ];
        if ($status == 0) {
            $data['refuse_info'] = $msg;
        }
        $rs = $goods_model->update($data);
        return $this->format($rs);
    }

    // 获取商家的商品详情
    public function getStoreGoodsInfo($id)
    {
        $goods_model = new Goods;
        $goods_skus_model = new GoodsSku();
        $goods_attr_model = new GoodsAttr();
        $goods_spec_model = new GoodsSpec();
//        $store_id = $this->get_store(true);
        $goods_info = $goods_model
            ->with('goods_brand')
//            ->where('store_id',$store_id)
            ->where('id', $id)
            ->first();
        $goods_info['goods_images'] = explode(',', $goods_info['goods_images']);

        // 获取处理后的规格信息
        $sku = $goods_skus_model->where('goods_id', $id)->get()->toArray();
        if (!empty($sku)) {
            $skuList = [];
            $spec_id = [];
            foreach ($sku as $v) {
                $v['spec_id'] = explode(',', $v['spec_id']);
                $v['sku_name'] = explode(',', $v['sku_name']);
                $spec_id = array_merge($spec_id, $v['spec_id']);
                $skuList[] = $v;
            }
            $spec_id = array_unique($spec_id);
            $goods_spec = $goods_spec_model->whereIn('id', $spec_id)->orderBy('id', 'desc')->get()->toArray();
            $attr_id = [];
            foreach ($goods_spec as $v) {
                if (!in_array($v['attr_id'], $attr_id)) {
                    $attr_id[] = $v['attr_id'];
                }
            }
            $goods_attr = $goods_attr_model->whereIn('id', $attr_id)->with('specs')->orderBy('id',
                'desc')->get()->toArray();
            foreach ($goods_attr as $k => $v) {
                foreach ($v['specs'] as $key => $vo) {
                    if (in_array($vo['id'], $spec_id)) {
                        $goods_attr[$k]['specs'][$key]['check'] = true;
                    }
                }
            }
            $goods_info['attrList'] = $goods_attr;
            $goods_info['skuList'] = $skuList;
        }
        return $this->format($goods_info);
    }

    /**
     * 根据商品编号获取商品信息
     * @param $goodsNo
     * @return mixed
     */
    public function getGoodsInfoByNo($goodsNo)
    {
        $goods_model = new Goods;
        return $goods_model->where('goods_no', $goodsNo)
            ->select([
                'goods_name',
                'goods_master_image',
                'goods_images',
                'unit',
            ])
            ->first();
    }

    // 获取商品详情
    public function getGoodsInfo($id, $auth = 'user'): array
    {
        $goods_model = new Goods;
        $store_service = new StoreService();
        $goods_skus_model = new GoodsSku();
        $goods_attr_model = new GoodsAttr();
        $goods_spec_model = new GoodsSpec();
        if ($auth != 'admin') {
            $goods_model = $goods_model->where($this->status);
        }
        $goods_info = $goods_model->with('goods_brand')->where('id', $id)->first();

        if (empty($goods_info)) {
            return $this->format_error(__('goods.goods_not_found'));
        }

//        $store_info = $store_service->getStoreInfo($goods_info['store_id']);

        /*if(!$store_info['status']){
            return $this->format_error($store_info['msg']);
        }*/

        /*if($store_info['data']['store_status']!=1 || $store_info['data']['store_verify']!=3){
            return $this->format_error(__('stores.store_not_defined'));
        }*/

        $goods_info['goods_images'] = explode(',', $goods_info['goods_images']);
        $goods_info['goods_images_thumb_150'] = $this->thumb_array($goods_info['goods_images'], 150);
        $goods_info['goods_images_thumb_400'] = $this->thumb_array($goods_info['goods_images'], 400);

        // 获取处理后的规格信息
        $sku = $goods_skus_model->where('goods_id', $id)->get()->toArray();
        if (!empty($sku)) {
            $skuList = [];
            $spec_id = [];
            foreach ($sku as $v) {
                $v['spec_id'] = explode(',', $v['spec_id']);
                $v['sku_name'] = explode(',', $v['sku_name']);
                $spec_id = array_merge($spec_id, $v['spec_id']);
                $skuList[] = $v;
            }
            $spec_id = array_unique($spec_id);
            $goods_spec = $goods_spec_model->whereIn('id', $spec_id)->orderBy('id', 'desc')->get()->toArray();
            $attr_id = [];
            foreach ($goods_spec as $v) {
                if (!in_array($v['attr_id'], $attr_id)) {
                    $attr_id[] = $v['attr_id'];
                }
            }
            $goods_attr = $goods_attr_model->whereIn('id', $attr_id)->with('specs')->orderBy('id',
                'desc')->get()->toArray();
            $goods_info['goods_price'] = $sku[0]['goods_price'];
            $goods_info['goods_price'] = $sku[0]['goods_stock'];
            $goods_info['attrList'] = $goods_attr;
            $goods_info['skuList'] = $skuList;
        }

        $goods_class_service = new GoodsClassService;
        $goods_info['goods_class'] = $goods_class_service->getGoodsClassByGoodsId($id)['data'];


        return $this->format($goods_info);
    }

    // 获取统计数据
    public function getCount($auth = "seller")
    {
        $goods_model = new Goods();

        if ($auth == 'seller') {
//            $store_id = $this->get_store(true);
            $data = [
//                'wait' => $goods_model->where('goods_verify', 2)->where('store_id', $store_id)->count(),
//                'refuse' => $goods_model->where('goods_verify', 0)->where('store_id', $store_id)->count(),
            ];
        } else {
            $data = [
                'wait' => $goods_model->where('goods_verify', 2)->count(),
                'refuse' => $goods_model->where('goods_verify', 0)->count(),
            ];
        }

        return $data;
    }

    /**
     * 商品搜索
     * @param int $store_id
     * @return array
     */
    public function goodsSearch($store_id = 0)
    {
        $goods_model = new Goods;

        // 商家
        if ($store_id) {
            $goods_model = $goods_model->whereHas('store_goods', function ($q) use ($store_id) {
                $q->where('store_id', $store_id);

                // 价格
                if ($goods_price = request()->goods_price) {
                    $q->where('goods_price', $goods_price);
                }
            });
        }

        // 品牌
        if ($brand_id = request()->brand_id) {
            $goods_model = $goods_model->whereIn('brand_id', explode(',', $brand_id));
        }

        // 栏目
        if ($class_id = request()->class_id) {
            $goods_model = $goods_model->whereIn('class_id', explode(',', $class_id));
        }

        // 关键词
        if ($keywords = request()->keywords) {
            $goods_model = $goods_model->where('goods_name', 'like', '%' . urldecode($keywords) . '%');
        }

        // 排序
        if ($sort_type = request()->sort_type) {
            $goods_model = $goods_model->orderBy($sort_type, request()->sort_order ?? 'desc');
        } else {
            $goods_model = $goods_model->orderBy('id', 'desc')->orderBy('goods_sale', 'desc');
        }

        $list = $goods_model->where($this->status)
            ->with([
                'goods_sku' => function ($q) {
                    return $q->select('goods_id', 'goods_price', 'goods_stock')->orderBy('goods_price', 'asc');
                },
                'store_goods'
            ])
            ->withCount('order_comment')
            ->paginate(request()->per_page ?? 30);
        return $this->format(new GoodsSearchCollection($list));
    }

    // 获取指定条件销售排行
    public function getSaleSortGoods($where, $take = 6)
    {
        $goods_model = new Goods();
        $list = $goods_model->whereHas('store', function ($q) {
            return $q->where(['store_status' => 1, 'store_verify' => 3]);
        })->with([
            'goods_skus' => function ($q) {
                return $q->orderBy('goods_price', 'asc');
            }
        ])->where($where)->where($this->status)->take($take)->orderBy('goods_sale', 'desc')->get();
        return $this->format(new GoodsListCollection($list));

    }

    // 获取店铺商品列表——按品牌分组
    public function getStoreGoodsList($id)
    {
        $goods_model = new Goods;
        try {
            if ($search = request()->search) {
                $goods_model = $goods_model->where('goods_name', 'like', '%' . $search . '%');
            }

            if ($brand_id = request()->brand_id) {
                $goods_model = $goods_model->whereIn('brand_id', explode(',', $brand_id));
            }

            if ($class_id = request()->class_id) {
                $goods_model = $goods_model->where('class_id', explode(',', $class_id));
            }

            $list = $goods_model
                ->where($this->status)
                ->with([
                    'goods_sku' => function ($q) {
                        return $q->select('goods_id', 'goods_price', 'goods_stock',
                            'goods_market_price')->orderBy('goods_price', 'asc');
                    },
                    'goods_brand'
                ])
                ->whereHas('store_goods', function ($q) use ($id) {
                    $q->where('store_id', $id);
                })
                ->get()
                ->map(function ($item) {
                    $goods_price = $item->goods_price;
                    $goods_market_price = $item->goods_market_price;

                    // 判断是否存在sku
                    if (isset($item->goods_sku)) {
                        $goods_price = $item->goods_sku['goods_price'];
                        $goods_market_price = $item->goods_sku['goods_market_price'];
                    }
                    return [
                        'id' => $item->id,
                        'goods_name' => $item->goods_name,
                        'goods_subname' => $item->goods_subname,
                        'goods_price' => $goods_price,
                        'goods_market_price' => $goods_market_price,
                        'goods_sale' => $item->goods_sale,
                        'goods_master_image' => $this->thumb($item->goods_master_image, 300),
                        'mobile_image' => $item->mobile_image,
                        'brand_id' => $item->brand_id,
                        'brand_name' => $item->goods_brand->name,
                        'goods_stock' => $item->store_goods->goods_stock,
                    ];
                });

            $data = [];
            foreach ($list as $item) {
                $brand_id = Arr::pull($item, 'brand_id');
                $data[$brand_id][] = $item;
            }

            $list = [];
            foreach ($data as $k => $v) {
                $item = new \stdClass();
                $item->brand_id = $k;
                $item->brand_name = $v[0]['brand_name'];
                $item->goods_list = array_map(function ($val) {
//                    unset($val['brand_id']);
                    unset($val['brand_name']);
                    return $val;
                }, $v);
                $list[] = $item;
            }

            return $this->format($list);
        } catch (\Exception $e) {
            Log::debug($e->getMessage());
            return $this->format_error($e->getMessage());
        }
    }

    // 获取首页秒杀商品
    public function getHomeSeckillGoods()
    {
        $goods_model = new Goods;
        $list = $goods_model->where($this->status)
            ->with([
                'goods_sku' => function ($q) {
                    return $q->select('goods_id', 'goods_price', 'goods_stock',
                        'goods_market_price')->orderBy('goods_price', 'asc');
                }
            ])
            ->whereHas('seckill', function ($q) {
                if (empty(request()->start_time)) {
                    $q->where('start_time', now()->format('Y-m-d H') . ':00');
                }
                $q->where('start_time', now()->addHours(request()->start_time)->format('Y-m-d H') . ':00');
            })
            ->paginate(request()->per_page ?? 30);
        return $this->format(new SeckillGoodsCollection($list));
    }
}
