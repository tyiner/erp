<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoodsBrand;
use Illuminate\Http\Request;
use App\Services\GoodsBrandService;
use App\Services\UploadService;

class GoodsBrandController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(GoodsBrandService $goods_brand_service)
    {
        $list = $goods_brand_service->getGoodsBrands()['data'];
        return $this->success($list);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request,GoodsBrand $goods_brand_model)
    {
        $goods_brand_model->pid = $request->pid??0;
        $goods_brand_model->name = $request->name;
        $goods_brand_model->thumb = $request->thumb??'';
        $goods_brand_model->is_sort = $request->is_sort??0;
        $goods_brand_model->save();
        $this->clear_cache(); // 修改则清空缓存
        return $this->success([],__('base.success'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(GoodsBrand $goods_brand_model,$id)
    {
        $info = $goods_brand_model->find($id);
        return $this->success($info);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,GoodsBrand $goods_brand_model, $id)
    {
        $goods_brand_model = $goods_brand_model->find($id);
        $goods_brand_model->pid = $request->pid??0;
        $goods_brand_model->name = $request->name;
        $goods_brand_model->thumb = $request->thumb??'';
        $goods_brand_model->is_sort = $request->is_sort??0;
        $goods_brand_model->save();
        $this->clear_cache(); // 修改则清空缓存
        return $this->success([],__('base.success'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(GoodsBrand $goods_brand_model,$id)
    {
        $idArray = array_filter(explode(',',$id),function($item){
            return is_numeric($item);
        });
        if($goods_brand_model->whereIn('pid',$idArray)->exists()){
            return $this->error('请先删除商品子品牌');
        }
        $goods_brand_model->destroy($idArray);
        $this->clear_cache(); // 修改则清空缓存
        return $this->success([],__('base.success'));
    }

    // 栏目缩略图上传
    public function goods_brand_upload(UploadService $upload_service){
        $rs = $upload_service->goods_brand();
        if($rs['status']){
            return $this->success($rs['data'],$rs['msg']);
        }else{
            return $this->error($rs['msg']);
        }
    }

    public function clear_cache(){
        $goods_brand_service = new GoodsBrandService();
        $goods_brand_service->clearCache();
        return $this->success([],__('base.success'));
    }
}
