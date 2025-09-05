<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Right', 'prefix' => 'Admin/right'], function () {
    Route::post("/location-right", "ManagerController@addLocation"); //总后台添加仓库权限
    Route::put("/location-right", "ManagerController@update"); //总公司修改仓库权限
    Route::get("/location-right", "ManagerController@get"); //总公司获取仓库列表
});

Route::group(['namespace' => 'Right', 'prefix' => 'Seller/right'], function () {
    Route::post("/location-right", "ManagerController@addLocation"); //分公司添加仓库权限
    Route::put("/location-right", "ManagerController@update"); //分公司修改仓库权限
    Route::get("/location-right", "ManagerController@get"); //分公司获取仓库列表
});
