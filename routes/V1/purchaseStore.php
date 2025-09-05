<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase', 'prefix' => 'Admin/purchase'], function () {
    Route::get('/company/inventory', 'StoreController@getInv'); //获取公司库存
    Route::get('/location/inventory', 'StoreController@getLocationInv'); //获取仓库库存
});
