<?php
namespace App\Services;

use App\Models\Goods;
use App\Models\StoreGoods;
use App\Traits\HelperTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreGoodsService extends BaseService {
    use HelperTrait;

    public function getList($filters = [], $options = ['page' => 1, 'pageSize' => 30]) {
        $query = DB::table('store_goods AS sg')
            ->join('goods AS g', 'g.id', '=', 'sg.goods_id')
            ->join('goods_brands AS gb', 'gb.id', '=', 'g.brand_id')
            ->join('goods_classes AS gc', 'gc.id', '=', 'g.class_id');

        if (is_array($filters) && count($filters)) {
            foreach ($filters as $filter) {
                if (!is_array($filter)) {
                    continue;
                }
                $query->where($filter[0], $filter[1], $filter[2]);
            }
        }

        $total = $query->count();

        $data = $query->orderBy('sg.id', 'desc')
            ->select([
                'sg.id',
                'sg.goods_status',
                'g.id AS goods_id',
                'g.goods_no',
                'g.goods_name',
                'g.goods_price',
                'g.goods_stock',
                'g.goods_sale',
                'g.goods_master_image',
//                'g.goods_status',
                'g.brand_id',
                'g.class_id',
                'g.created_at',
                'g.updated_at',
                'gb.name AS brand_name',
                'gc.name AS class_name',
            ])
            ->forPage($options['page'], $options['pageSize'])
            ->get();

        $list = $data->map(function ($item) {

            $goods_price = $item->goods_price;
            $goods_stock = $item->goods_stock;

            $goodsSkus = DB::table('goods_skus')
                ->where('goods_id', '=', $item->goods_id)
                ->orderBy('goods_price')
                ->get([
                    'goods_id',
                    'goods_price',
                    'goods_stock',
                ])->all();

            // 判断是否存在sku
            if(isset($goodsSkus) && count($goodsSkus)){
                $goods_stock = 0;
                foreach($goodsSkus as $v){
                    $goods_stock += $v['goods_stock'];
                }

                if(count($goodsSkus)>1){
                    $goods_price = $goodsSkus[0]['goods_price'].' ~ '.$goodsSkus[count($goodsSkus)-1]['goods_price'];
                }else{
                    $goods_price = $goodsSkus[0]['goods_price'];
                }
            }

            $classIds = [];
            $classId = $item->class_id;
            while($classId) {
                array_unshift($classIds, $classId);
                $classId = DB::table('goods_classes')
                    ->where('id', '=', $classId)
                    ->first()->pid;
            }

            return [
                'id'                    =>  $item->id,
                'goods_id'              =>  $item->goods_id,
                'goods_no'              =>  $item->goods_no,
                'goods_name'            =>  $item->goods_name,
                'goods_price'           =>  $goods_price,
                'goods_stock'           =>  $goods_stock,
                'goods_sale'            =>  $item->goods_sale,
                'goods_master_image'    =>  $this->thumb($item->goods_master_image,150),
                'brand_name'            =>  $item->brand_name,
                'class_name'            =>  $item->class_name,
                'class_id'              =>  $classIds,
                'goods_status'          =>  $item->goods_status,
                'created_at'            =>  $item->created_at,
                'updated_at'            =>  $item->updated_at,
            ];
        });

        return [$list, $total];
    }

    public function add($goodsIds = []) {
        $storeId = $this->get_store(true);
        if (empty($goodsIds) || empty($storeId)) {
            return false;
        }
        $goodsList = Goods::whereIn('id', $goodsIds)
            ->orderBy(DB::raw('FIND_IN_SET(id, "' . implode(",", $goodsIds) . '"' . ')'))
            ->get();

        $data = [];
        foreach ($goodsList as $goods) {
            $item = [
                'goods_id' => $goods->id,
                'store_id' => $storeId,
                'goods_status' => $goods->goods_status,
                'goods_price' => $goods->goods_price,
                'goods_market_price' => $goods->goods_market_price,
                'cost_price' => $goods->cost_price,
                'goods_stock' => $goods->goods_stock,
                'stock_guard' => $goods->stock_guard,
                'decr_stock_mode' => $goods->decr_stock_mode,
                'purchase_point' => $goods->purchase_point,
                'unit' => $goods->unit,
                'is_coupon' => $goods->is_coupon,
                'purchase_limit' => $goods->purchase_limit,
                'purchase_start' => $goods->purchase_start,
                'purchase_end' => $goods->purchase_end,
                'goods_weight' => $goods->goods_weight,
                'pack_length' => $goods->pack_length,
                'pack_width' => $goods->pack_width,
                'pack_height' => $goods->pack_height,
                'freight_id' => $goods->freight_id,
            ];
            $data[] = $item;
        }
        if (empty($data)) {
            return false;
        }
        $result = DB::table('store_goods')->insert($data);
        return $result;
    }

    public function get($id) {
        $goods = DB::table('store_goods AS sg')
            ->join('goods AS g', 'g.id', '=', 'sg.goods_id')
            ->join('goods_brands AS gb', 'gb.id', '=', 'g.brand_id')
            ->join('goods_classes AS gc', 'gc.id', '=', 'g.class_id')
            ->where('sg.id', '=', $id)
            ->first([
                'sg.*',
                'g.goods_no',
                'g.goods_name',
//                'g.goods_price',
//                'g.goods_stock',
                'g.goods_sale',
                'g.goods_master_image',
                'g.goods_images',
//                'g.goods_status',
                'g.brand_id',
                'g.class_id',
                'g.created_at',
                'g.updated_at',
                'gb.name AS brand_name',
                'gc.name AS class_name',
            ]);

        $goods->goods_images = explode(',', $goods->goods_images);
        $goods->goods_images_thumb_150 = $this->thumb_array($goods->goods_images,150);
        $goods->goods_images_thumb_400 = $this->thumb_array($goods->goods_images,400);

        return $this->format($goods);
    }

    public function edit($store_goods_id) {
        try {
            $storeGoods = StoreGoods::find($store_goods_id);
            if (isset(request()->goods_status)) {
                $storeGoods->goods_status = request()->goods_status;
            }
            if (isset(request()->goods_price)) {
                $storeGoods->goods_price = abs(request()->goods_price ?? 0);
            }
            if (isset(request()->goods_market_price)) {
                $storeGoods->goods_market_price = abs(request()->goods_market_price ?? 0);
            }
            if (isset(request()->cost_price)) {
                $storeGoods->cost_price = abs(request()->cost_price ?? 0);
            }
            if (isset(request()->goods_stock)) {
                $storeGoods->goods_stock = request()->goods_stock;
            }
            if (isset(request()->stock_guard)) {
                $storeGoods->stock_guard = request()->stock_guard;
            }
            if (isset(request()->sort)) {
                $storeGoods->sort = request()->sort;
            }
            if (isset(request()->golduser_fee_ratio)) {
                $storeGoods->golduser_fee_ratio = request()->golduser_fee_ratio;
            }
            if (isset(request()->promote_fee_ratio)) {
                $storeGoods->promote_fee_ratio = request()->promote_fee_ratio;
            }
            if (isset(request()->area_fee_ratio)) {
                $storeGoods->area_fee_ratio = request()->area_fee_ratio;
            }
            if (isset(request()->goods_sale)) {
                $storeGoods->goods_sale = request()->goods_sale;
            }
            if (isset(request()->unit)) {
                $storeGoods->unit = request()->unit;
            }
            if (isset(request()->is_coupon)) {
                $storeGoods->is_coupon = request()->is_coupon;
            }
            if (isset(request()->purchase_limit)) {
                $storeGoods->purchase_limit = request()->purchase_limit;
            }
            if (isset(request()->purchase_start)) {
                $storeGoods->purchase_start = request()->purchase_start;
            }
            if (isset(request()->purchase_end)) {
                $storeGoods->purchase_end = request()->purchase_end;
            }
            if (isset(request()->purchase_point)) {
                $storeGoods->purchase_point = request()->purchase_point;
            }
            if (isset(request()->goods_weight)) {
                $storeGoods->goods_weight = request()->goods_weight;
            }
            if (isset(request()->pack_length)) {
                $storeGoods->pack_length = request()->pack_length;
            }
            if (isset(request()->pack_width)) {
                $storeGoods->pack_width = request()->pack_width;
            }
            if (isset(request()->pack_height)) {
                $storeGoods->pack_height = request()->pack_height;
            }
            if (isset(request()->freight_id)) {
                $storeGoods->freight_id = request()->freight_id;
            }

            $rs = $storeGoods->save();
            return $this->format($rs);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->format_error($e->getMessage());
        }
    }
}
