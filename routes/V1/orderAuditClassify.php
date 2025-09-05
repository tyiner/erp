<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase','middleware' => 'jwt.admin', 'prefix' => 'Admin/'], function () {
    Route::post("/order-audit", "OrderAuditClassifyController@store"); //新建订单审核等级分类
    Route::put("/order-audit", "OrderAuditClassifyController@update"); //更新订单审核等级分类
    Route::delete("/order-audit", "OrderAuditClassifyController@delete"); //删除订单审核等级分类
    Route::get("/order-audit", "OrderAuditClassifyController@getDetail"); //获取订单审核等级分类详情
    Route::get("/order-audit/order-type", "OrderAuditClassifyController@getByType"); //根据类型获取订单审核等级分类详情
    Route::get("/order-audit/get-list", "OrderAuditClassifyController@getList"); //获取订单审核等级分类列表
});
