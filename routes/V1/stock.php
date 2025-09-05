<?php

use Illuminate\Support\Facades\Route;

Route::group(
    ['namespace' => 'Stock', 'prefix' => 'Admin/stock'],
    function () {
        Route::post('/', 'StockController@create'); //创建备货计划单
        Route::put('/', 'StockController@update'); //更改备货计划单
        Route::delete('/', 'StockController@destroy'); //删除备货计划单
        Route::get('/', 'StockController@get');//获取备货计划单详情
        Route::get('/get-list', 'StockController@getList'); //获取备货计划列表
        Route::get('/get-detail-list', 'StockController@getDetailList'); //获取备货计划明细列表

        Route::post('/', 'StockController@create'); //创建备货计划单
        Route::put('/', 'StockController@update'); //更改备货计划单
        Route::delete('/', 'StockController@destroy'); //删除备货计划单
        Route::get('/', 'StockController@get');//获取备货计划单详情
        Route::get('/get-list', 'StockController@getList'); //获取备货计划列表
        Route::get('/get-detail-list', 'StockController@getDetailList'); //获取备货计划明细列表

        Route::post('/lock', 'LockInventoryController@create'); //创建锁定仓
        Route::put('/lock', 'LockInventoryController@update'); //修改锁定仓商品数量
        Route::delete('/lock', 'LockInventoryController@destory'); //删除锁定仓
        Route::get('/lock', 'LockInventoryController@getList'); //获取锁定仓数量
    }
);

Route::group(
    ['namespace' => 'Stock', 'prefix' => 'Seller/stock'],
    function () {
        Route::post('/', 'StockController@create'); //创建备货计划单
        Route::put('/', 'StockController@update'); //更改备货计划单
        Route::delete('/', 'StockController@destroy'); //删除备货计划单
        Route::get('/', 'StockController@get');//获取备货计划单详情
        Route::get('/get-list', 'StockController@getList'); //获取备货计划列表
        Route::get('/get-detail-list', 'StockController@getDetailList'); //获取备货计划明细列表

        Route::post('/', 'StockController@create'); //创建备货计划单
        Route::put('/', 'StockController@update'); //更改备货计划单
        Route::delete('/', 'StockController@destroy'); //删除备货计划单
        Route::get('/', 'StockController@get');//获取备货计划单详情
        Route::get('/get-list', 'StockController@getList'); //获取备货计划列表
        Route::get('/get-detail-list', 'StockController@getDetailList'); //获取备货计划明细列表

        Route::post('/lock', 'LockInventoryController@create'); //创建锁定仓
        Route::put('/lock', 'LockInventoryController@update'); //修改锁定仓商品数量
        Route::delete('/lock', 'LockInventoryController@destory'); //删除锁定仓
        Route::get('/lock', 'LockInventoryController@getList'); //获取锁定仓数量
    }
);
