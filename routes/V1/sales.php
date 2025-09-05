<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase', 'middleware' => 'jwt.admin', 'prefix' => 'Admin/sale'], function () {

    Route::post('/order', 'SaleOrderController@saleOrderAdd')->name('admin.saleOrder.create'); //新建销售订单
    Route::get('/order', 'SaleOrderController@getSaleOrderByIds')
        ->name('admin.saleOrder.getSaleOrderByIds'); //根据id获取销售订单详情
    Route::get('/get-list', 'SaleOrderController@getSaleOrderList')
        ->name('admin.saleOrder.getSaleOrderList'); //获取销售订单列表
    Route::put('/order', 'SaleOrderController@update')->name('admin.saleOrder.update'); //更新销售订单
    Route::put('/order/first-check', 'SaleOrderController@firstCheck')->name('sale.order.firstCheck'); //销售订单审核
    Route::put('/order/second-check', 'SaleOrderController@secondCheck')->name('sale.order.secondCheck'); //二级审核销售订单审核
    Route::delete('/order', 'SaleOrderController@delete')->name('sale.order.delete'); //删除销售订单

    Route::post('/ship', 'SaleShipController@saleShipAdd')->name('sale.ship.saleShipAdd'); //新建销售发货单
    Route::put('/ship', 'SaleShipController@update')->name('sale.ship.update'); //更新销售发货单
    Route::patch('/ship/first-check', 'SaleShipController@firstCheck')->name('sale.ship.firstCheck'); //销售发货单审核
    Route::get('/ship/get-list', 'SaleShipController@getSaleShipList')->name('sale.ship.getSaleShipList'); //获取销售发货单列表
    Route::get('/ship', 'SaleShipController@getSaleShipByIds')->name('sale.ship.getSaleShipByIds'); //根据id获取销售发货单详情
    Route::delete('/ship', 'SaleShipController@delete')->name('sale.ship.delete'); //删除销售发货单

    Route::post('/out', 'SaleOutController@saleOutAdd')->name('sale.out.saleOutAdd'); //新建销售出库单
    Route::put('/out', 'SaleOutController@update')->name('sale.out.update'); //更新销售出库单
    Route::get('/out', 'SaleOutController@getSaleOutByIds')->name('sale.out.getSaleOutByIds'); //新建销售出库单
    Route::get('/out/get-list', 'SaleOutController@getSaleOutList')->name('sale.out.getSaleOutList'); //获取销售出库列表单
    Route::patch('/out', 'SaleOutController@firstCheck')->name('sale.out.firstCheck');//审核销售出库单
    Route::patch('/out/second', 'SaleOutController@secondCheck')->name('sale.out.secondCheck');//二级审核销售出库单
    Route::delete('/out', 'SaleOutController@delete')->name('sale.out.delete'); //删除销售出库单

    Route::post('/back', 'SaleBackController@saleBackAdd')->name('sale.back.saleBackAdd'); //新建销售退货单
    Route::put('/back', 'SaleBackController@update')->name('sale.back.update'); //更新销售退货单
    Route::get('/back', 'SaleBackController@getSaleBackByIds')->name('sale.back.getSaleBackByIds'); //根据id获取销售退货单详情
    Route::get('/back/get-list', 'SaleBackController@getSaleBackList')->name('sale.back.getSaleBackList'); //按条件获取销售列表
    Route::delete('/back', 'SaleBackController@delete')->name('sale.back.delete');  //删除单条销售退货单
    Route::put('/back', 'SaleBackController@update')->name('sale.back.update'); //更新销售退货单
    Route::patch('/back', 'SaleBackController@audit')->name('sale.back.audit'); //更新销售退货单

    Route::post('/red', 'SaleRedController@saleRedAdd')->name('sale.red.saleRedAdd'); //新建销售出库单红字
    Route::put('/red', 'SaleRedController@update')->name('sale.red.update'); //更新销售出库单红字
    Route::get('/red', 'SaleRedController@getSaleRedByIds')->name('sale,red.getSaleRedByIds'); //新建销售出库单红字
    Route::patch('/red', 'SaleRedController@audit')->name('sale.red.audit'); //审核销售出库单红字
    Route::delete('/red', 'SaleRedController@delete')->name('sale.red.delete'); //删除销售出库单红字
    Route::get('/red/get-list', 'SaleRedController@getSaleRedList')->name('sale.red.getSaleRedList'); //获取销售出库单红字列表
});
