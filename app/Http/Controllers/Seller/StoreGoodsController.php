<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\GoodsResource\GoodsTabSellerCollection;
use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\StoreGoods;
use App\Services\GoodsService;
use App\Services\StoreGoodsService;
use App\Services\StoreService;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreGoodsController extends Controller
{
    public function index(Request $request, StoreGoodsService $service)
    {
        $options = [
            'page' => $request->page ?? 1,
            'pageSize' => $request->per_page ?? 30,
        ];

        $filters = [
            ['sg.store_id', '=', $this->get_store(true)],
        ];
        [$list, $total] = $service->getList($filters, $options);
        $data = [
            'data' => $list,
            'total' => $total,
            'per_page' => $options['pageSize'], // 每页数量
            'current_page' => $options['page'], // 当前页码
        ];

        return $this->success($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request,StoreGoodsService $service)
    {
        $goodsId = explode(',', $request->goods_id);
        $result = $service->add($goodsId);
        if($result){
            return $this->success([], '关联商品成功');
        }
        return $this->error('关联商品失败');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(StoreGoodsService $store_goods_service,$id)
    {
        $info = $store_goods_service->get($id);
        if($info['status']){
            return $this->success($info['data']);
        }
        return $this->error(__('goods.add_error'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,StoreGoodsService $store_goods_service, $id)
    {
        $info = $store_goods_service->edit($id);
        if($info['status']){
            return $this->success($info['data'],__('goods.add_success'));
        }
        return $this->error($info['msg']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(StoreGoods $store_goods_model,$id)
    {
        $idArray = array_filter(explode(',',$id),function($item){
            return is_numeric($item);
        });
        $goods_list = $store_goods_model->select('store_id')->whereIn('id',$idArray)->get();
        foreach($goods_list as $v){
            if($v['store_id'] != $this->get_store(true)){
                return $this->error('删除错误');
            }
        }
        $store_goods_model->whereIn('id',$idArray)->delete();
        return $this->success([], '删除成功');
    }

    // 商家拥有商品栏目信息
    public function store_goods_classes(StoreService $store_service){
        $goods_classes = $store_service->getStoreGoodsClasses($this->get_store(true));
        return $this->success($goods_classes['data']);
    }

    // 商品图片上传
    public function goods_upload(){
        $upload_service = new UploadService();
        $rs = $upload_service->goods();
        if($rs['status']){
            return $this->success($rs['data'],$rs['msg']);
        }else{
            return $this->error($rs['msg']);
        }
    }

    public function publish(Request $request) {
        try {
            $id = explode(',', $request->id);
            $rs = StoreGoods::whereIn('id', $id)->update([
                'goods_status' => $request->type,
            ]);
            return $this->success($rs);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error($e->getMessage());
        }
    }
}
