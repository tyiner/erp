<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'jwt.admin', 'namespace' => 'Purchase', 'prefix' => 'Admin/purchase'], function () {
    Route::get("/order-no", "OrderNoController@getName")->name("purchase.getNo"); //获取采购序号
    Route::get("/transfer", "PurchaseController@getTransferById")->name('purchase.transfer.getById'); //获取采购单详情
    Route::get("/other-in/get", "PurchaseController@getById")->name('purchase.otherIn.getById'); //获取其他入库单详情
    Route::get("/other-out/get", "PurchaseController@getById")->name('purchase.otherOut.getById'); //获取其他出库单详情
    Route::get("/other-out-back/get", "PurchaseController@getById")
        ->name('purchase.otherOutBack.getById'); //获取其他出库单红字详情
    Route::get("/arrival/get", "PurchaseController@getById")->name('purchase.arrival.getById'); //获取采购到货单详情
    Route::get("/back/get", "PurchaseController@getById")->name('purchase.back.getById'); //获取采购退货单详情
    Route::get("/stock-plan/get", "StockPlan\StockPlanController@getById")->name('purchase.stockPlan.getById'); //备货计划
    Route::get("/stock-ship/get", "PurchaseController@getById")->name('purchase.stockShip.getById'); //备货发货
    Route::get("/stock-out/get", "PurchaseController@getById")->name('purchase.stockOut.getById'); //备货出库
    Route::get("/stock-back/get", "PurchaseController@getById")->name('purchase.stockBack.getById'); //备货退货
    Route::get("/stock-red/get", "PurchaseController@getById")->name('purchase.stockRed.getById'); //备货出库红字

    Route::get("/get-list", "PurchaseController@getList")->name('purchase.getList'); //获取采购单列表
    /*Route::get("/arrival/get-list", "PurchaseController@getList")->name('purchase.arrival.getList'); //获取采购到货单列表
    Route::get("/store/get-list", "PurchaseController@getList")->name('purchase.store.getList'); //获取采购入库单列表
    Route::get("/stock-plan/get-list", "PurchaseController@getList")->name('purchase.stockPlan.getList'); //备货计划单列表
    Route::get("/stock-ship/get-list", "PurchaseController@getList")->name('purchase.stockShip.getList'); //备货发货单列表
    Route::get("/stock-out/get-list", "PurchaseController@getList")->name('purchase.stockOut.getList'); //备货出库单列表*/
    Route::get(
        "/arrival/get-detail-list",
        "PurchaseController@getDetailList"
    )->name('purchase.arrival.getDetailList'); //获取采购到货明细列表
    Route::get("/store/get-detail-list", "PurchaseController@getDetailList")
        ->name('purchase.store.getDetailList'); //获取采购入库明细列表
    Route::get("/stock-out/get-detail-list", "PurchaseController@getDetailList")
        ->name('purchase.stockOut.getDetailList'); //备货出库
    Route::get("/transfer/get-detail-list", "PurchaseController@getTransferDetailList")
        ->name('purchase.transfer.getDetailList'); //获取调拨入库明细列表
    Route::get("/other-in/get-detail-list", "PurchaseController@getDetailList")
        ->name('purchase.otherIn.getDetailList'); //获取其他入库明细列表
    Route::get("/other-out/get-detail-list", "PurchaseController@getDetailList")
        ->name('purchase.OtherOut.getDetailList'); //获取其他出库明细列表
    Route::get("/other-out-back/get-detail-list", "PurchaseController@getDetailList")
        ->name('purchase.OtherOutBack.getDetailList'); //获取其他出库红字明细列表
    Route::get("/stock-back/get-detail-list", "PurchaseController@getDetailList")
        ->name('purchase.stockBack.getDetailList'); //获取备货退货明细列表
    Route::get("/stock-red/get-detail-list", "PurchaseController@getDetailList")
        ->name('purchase.StockRed.getDetailList'); //获取备货出库红字明细列表

    Route::get("/sn", "SnCodeController@getGoodsByCode")
        ->name('purchase.sn'); //根据sn码信息获取商品信息

    Route::post("/", "PurchaseController@create")->name('purchase.order.create'); //新建采购单
    Route::post("/arrival", "PurchaseController@create")->name('purchase.arrival.create'); //新建采购到货单
    Route::post("/transfer", "PurchaseController@createTransfer")->name('purchase.transfer.create'); //新建调拨单
    Route::post("/store", "PurchaseController@create")->name('purchase.store.create'); //新建采购入库单
    Route::post("/store/serials", "PurchaseController@postExcel")->name('purchase.store.serials.postExcel');//Excel提交
    Route::post("/back", "PurchaseController@create")->name('purchase.back.create'); //新建采购退货单
    Route::post("/other-in", "PurchaseController@create")->name('purchase.otherIn.create'); //新建采购其他入库单
    Route::post("/other-out", "PurchaseController@create")->name('purchase.otherOut.create'); //新建采购其他出库单
    Route::post("/other-out-back", "PurchaseController@create")->name('purchase.otherOutBack.create'); //新建采购其他出库单红字
    Route::post("/stock-plan", "PurchaseController@create")->name('purchase.StockPlan.create'); //新建备货计划单
    Route::post("/stock-ship", "PurchaseController@create")->name('purchase.stockShip.create'); //新建备货发货单
    Route::post("/stock-out", "PurchaseController@create")->name('purchase.stockOut.create'); //新建备货出库单
    Route::post("/stock-back", "PurchaseController@create")->name('purchase.stockBack.create'); //新建备货退货入库单
    Route::post("/stock-red", "PurchaseController@create")->name('purchase.stockRed.create'); //新建备货出库红字单

    Route::put("/order/status", "PurchaseController@changeStatus")->name('purchase.order.status'); //修改采购订单状态
    Route::put("/stock-plan/status", "PurchaseController@changeStatus")->name('purchase.stockPlan.status'); //修改备货计划单状态
    Route::put("/sale-order/status", "PurchaseController@changeStatus")->name('purchase.saleOrder.status'); //修改销售订单状态

    Route::put("/first/check", "PurchaseController@firstChecked")->name('purchase.first.check'); //修改采购单一级审核状态
    Route::put("/other-out-back/first/check", "PurchaseController@firstChecked")
        ->name('purchase.otherOutBack.first.check'); //修改其他出库红字单一级审核状态
    Route::put("/back/first/check", "PurchaseController@firstChecked")->name('purchase.back.first.check'); //修改退货单一级审核状态
    Route::put("/arrival/first/check", "PurchaseController@firstChecked")
        ->name('purchase.arrival.first.check'); //修改到货一级审核状态
    Route::put(
        "/store/first/check",
        "PurchaseController@firstChecked"
    )->name('purchase.store.first.check'); //修改入库单一级审核状态
    Route::put("/stock-plan/first/check", "PurchaseController@firstChecked")
        ->name('purchase.stockPlan.first.check'); //修改备货计划单一级审核状态
    Route::put("/stock-ship/first/check", "PurchaseController@firstChecked")
        ->name('purchase.stockShip.first.check'); //修改备货发货单一级审核状态
    Route::put("/stock-out/first/check", "PurchaseController@firstChecked")
        ->name('purchase.stockOut.first.check'); //修改备货出库单一级审核状态
    Route::put("/stock-back/first/check", "PurchaseController@firstChecked")
        ->name('purchase.stockBack.first.check'); //修改备货退货单一级审核状态
    Route::put("/stock-red/first/check", "PurchaseController@firstChecked")
        ->name('purchase.stockRed.first.check'); //修改备货出库红字单一级审核状态
    Route::put("/other-in/first/check", "PurchaseController@firstChecked")
        ->name('purchase.otherIn.first.check'); //修改其他入库单一级审核状态
    Route::put("/other-out/first/check", "PurchaseController@firstChecked")
        ->name('purchase.otherOut.first.check'); //修改其他出库单一级审核状态
    Route::put("/stock-ship/first/check", "PurchaseController@firstChecked")
        ->name('purchase.stockShip.first.check'); //修改备货发货单一级审核状态
    Route::put("/transfer/first/check", "PurchaseController@firstChecked")
        ->name('purchase.transfer.first.check'); //调拨单一级审核状态
    Route::put("/second/check", "PurchaseController@secondChecked")
        ->name('purchase.second.check'); //修改采购订单二级审核状态
    Route::put("/back/second/check", "PurchaseController@secondChecked")
        ->name('purchase.back.second.check'); //修改采购退货单二级审核状态
    /*Route::put("/arrival/second/check", "PurchaseController@secondChecked")
        ->name('purchase.arrival.second.check'); //修改采购到货单二级审核状态*/
    Route::put("/stock-plan/second/check", "PurchaseController@secondChecked")
        ->name('purchase.stockPlan.second.check'); //修改备货计划单二级审核状态
    Route::put("/stock-ship/second/check", "PurchaseController@secondChecked")
        ->name('purchase.stockShip.second.check'); //备货发货单二级审核
    Route::put("/stock-out/second/check", "PurchaseController@secondChecked")
        ->name('purchase.stockOut.second.check'); //修改备货出库单二级审核状态
    Route::put("/stock-red/second/check", "PurchaseController@secondChecked")
        ->name('purchase.stockRed.second.check'); //修改备货出库红字单二级审核状态
    Route::put("/stock-back/second/check", "PurchaseController@secondChecked")
        ->name('purchase.stockBack.second.check'); //修改备货退货单二级审核状态

    Route::put("/", "PurchaseController@update")
        ->name('purchase.update'); //更新采购单
    Route::put("/arrival", "PurchaseController@update")->name('purchase.arrival.update'); //更新采购单
    Route::put("/transfer", "PurchaseController@updateTransfer")->name('purchase.transfer.update'); //更新采购单
    Route::put("/store", "PurchaseController@update")->name('purchase.store.update'); //更新采购单
    Route::put("/stock-plan", "PurchaseController@update")->name('purchase.stockPlan.update'); //更新备货计划单
    Route::put("/stock-ship", "PurchaseController@update")->name('purchase.stockShip.update'); //更新备货发货单
    Route::put("/stock-out", "PurchaseController@update")->name('purchase.stockOut.update'); //更新备货出库单
    Route::put("/stock-back", "PurchaseController@update")->name('purchase.stockBack.update'); //更新备货退货单
    Route::put("/stock-red", "PurchaseController@update")->name('purchase.stockRed.update'); //更新备货红字出库单
    Route::put("/other-in", "PurchaseController@update")->name('purchase.otherIn.update'); //更新采购单
    Route::put("/other-out", "PurchaseController@update")->name('purchase.otherOut.update'); //更新采购单
    Route::put("/other-out-back", "PurchaseController@update")->name('purchase.otherOutBack.update'); //更新采购单

    Route::delete("/", "PurchaseController@delete")->name('purchase.delete'); //删除采购单
    Route::delete("/back", "PurchaseController@delete")->name('purchase.back.delete'); //删除退货单
    Route::delete("/arrival", "PurchaseController@delete")->name('purchase.arrival.delete'); //删除采购到货单
    Route::delete("/transfer", "PurchaseController@deleteTransfer")->name('purchase.transfer.delete'); //删除采购到货单
    Route::delete("/store", "PurchaseController@delete")->name('purchase.store.delete'); //删除采购入库单
    Route::delete("/stock-plan", "PurchaseController@delete")->name('purchase.stockPlan.delete'); //删除备货计划单
    Route::delete("/stock-ship", "PurchaseController@delete")->name('purchase.stockShip.delete'); //删除备货发货单
    Route::delete("/stock-out", "PurchaseController@delete")->name('purchase.stockOut.delete'); //删除备货出库单
    Route::delete("/stock-back", "PurchaseController@delete")->name('purchase.stockBack.delete'); //删除备货退货单
    Route::delete("/stock-red", "PurchaseController@delete")->name('purchase.stockRed.delete'); //删除备货红字出库单
    Route::delete("/other-in", "PurchaseController@delete")->name('purchase.OtherIn.delete'); //删除其他入库单
    Route::delete("/other-out", "PurchaseController@delete")->name('purchase.otherOut.delete'); //删除其他出库单
    Route::delete("/other-out-back", "PurchaseController@delete")->name('purchase.otherOutBack.delete'); //删除其他出库红字单
    Route::put("/third/check", "PurchaseController@thirdChecked")->name('purchase.third.check');//采购订单三级审核
    Route::put("/stock-plan/third/check", "PurchaseController@thirdChecked")
        ->name('purchase.stockPlan.third.check');//备货计划单三级审核
    Route::put("/stock-back/third/check", "PurchaseController@thirdChecked")
        ->name('purchase.stockBack.third.check');//备货退货单三级审核
});

//采购订单接口
Route::group(['middleware' => 'jwt.admin', 'namespace' => 'PurchaseOrder', 'prefix' => 'Admin/purchase'], function () {
    Route::get("/get-detail-list", "PurchaseOrderController@getDetailList")
        ->name('purchase.getDetailList'); //获取采购明细列表
    Route::get("/get", "PurchaseOrderController@getPurchaseOrder")->name('purchase.getById'); //根据id获取采购单详情
});

//采购订单接口
Route::group(['middleware' => 'jwt.admin', 'namespace' => 'PurchaseStore', 'prefix' => 'Admin/purchase'], function () {
    Route::get("/store/get", "PurchaseStoreController@getPurchaseStore")->name('purchase.store.getById'); //获取采购入库单详情
});

//备货计划接口
Route::group(['middleware' => 'jwt.admin', 'namespace' => 'Stock', 'prefix' => 'Admin/purchase'], function () {
    Route::get("/stock-plan/get-detail-list", "StockPlanController@getDetailList")
        ->name('purchase.stockPlan.getDetailList'); //备货计划
    Route::get("/stock-ship/get-detail-list", "StockShipController@getDetailList")
        ->name('purchase.stockShip.getDetailList'); //备货发货
});
Route::post("/sn", "Purchase\SnCodeController@storeSnInfo"); //保存sn码并进行缓存
