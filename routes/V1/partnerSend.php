<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase', 'prefix' => 'Admin/purchase/partner'], function () {
    Route::post('/store', 'PartnerController@storeSend'); //采购推送
    Route::post('/store-out', 'PartnerController@storeOutSend'); //采购退货推送
    Route::post('/transfer', 'PartnerController@transferSend'); //调拨推送
    Route::post('/sale', 'PartnerController@saleSend'); //销售出库
    Route::post('/sale-back', 'PartnerController@saleBackSend'); //销售退货
    Route::post('/goods-info', 'PartnerController@informGoods'); //存货信息推送
    Route::get('/goods/unsuccessful', 'PartnerController@unSuccess'); //获取失败推送数据
    Route::post('/goods/retry', 'PartnerController@retry');// 二次提交失败的推送商品数据
});
