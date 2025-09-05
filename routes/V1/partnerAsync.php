<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase', 'prefix' => 'Admin/purchase/partner-async'], function () {
    Route::post('/store', 'PartnerAsyncController@store'); //入库信息回传
    Route::post('/sale', 'PartnerAsyncController@sale'); //销售出库回传
    Route::post('/other', 'PartnerAsyncController@other'); //其他出库回传
    Route::any('/bai-lu', 'PartnerAsyncController@baiLuChi'); //百路驰回调接口
});
