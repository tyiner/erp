<?php

//定义状态码
define('PACKAGE_NUM', 100); //定义一箱商品的数量
define('SUCCESS_CODE', 200);//请求成功返回码
define('COMMON_ERROR_CODE', -1);//请求失败返回码

define('ADMIN_NAME', 'admin'); //admin账户名称

define('UNCHECKED', 1); //单据未审核
define('FIRST_CHECKED', 2); //单据完成一级审核
define('SECOND_CHECKED', 3); //单据完成二级审核状态
define('THIRD_CHECKED', 4); //单据完成三级审核状态

//采购模块表单类型
define('PURCHASE_PLAN', 120021); //总公司采购计划单    3级审核
define('PURCHASE_ARRIVAL', 120022); //总公司采购到货单  1级审核
define('PURCHASE_STORE_IN', 120023); //总公司采购入库单  1级审核
define('PURCHASE_BACK_PLAN', 120024); //总公司采购退货计划单
define('PURCHASE_STORE_OUT', 120025); //总公司采购入库退货单
define('PURCHASE_STOCK_PLAN', 120026); //总公司备货计划单  3级审核
define('PURCHASE_STOCK_SHIP', 120027); //总公司备货发货单  2级审核
define('PURCHASE_STOCK_OUT', 120028); //总公司备货出库单   2级审核
define('PURCHASE_STOCK_BACK', 120029); //总公司备货退货单  3级审核
define('PURCHASE_STOCK_RED', 120030); //总公司备货出库红字单   2级审核

define('PURCHASE_OTHER_IN', 140021); //总公司采购其他入库单   1级审核
define('PURCHASE_OTHER_OUT', 140022); //总公司采购其他出库单   1级审核
define('PURCHASE_OTHER_RED', 140023); //总公司采购其他出库退货单  1级审核

define('PURCHASE_TRANSFER', 160621); //采购调拨单

define('PURCHASE_SALE', 180021); //总公司销售订单
define('PURCHASE_SALE_SHIP', 180022); //总公司销售发货单
define('PURCHASE_SALE_OUT', 180023); //总公司销售出库单
define('PURCHASE_SALE_BACK', 180024); //采购销售退货单
define('PURCHASE_SALE_RED', 180025); //采购销售出库红字单

//备货表单类型
define('STOCK_PLAN', 130021); //备货计划单
define('STOCK_ARRIVAL', 130022); //备货到货单
define('STOCK_STORE_IN', 130023); //备货入库单
define('STOCK_BACK_PLAN', 130024); //备货退货计划单
define('STOCK_STORE_OUT', 130025); //备货退货出库单
define('STOCK_OTHER_IN', 150021); //备货其他入库单
define('STOCK_OTHER_OUT', 150022); //备货其他出库单
define('STOCK_SALE_PLAN', 160031); //销售计划单
define('STOCK_SALE_OUT', 160021); //销售出库单
define('STOCK_SALE_BACK', 160022); //销售退货入库单
define('STOCK_TRANSFER', 170021); //分公司调拨单

define('SU_YUAN_PUSH_URL', 'http://test-suyuan-task.lititong.net:81/api/erp/input_process'); //溯源系统追踪

/**
 * 越海仓相关数据
 */
define('YUE_HAI_URL', 'http://swms.boeadapter.staging.yhglobal.cn'); //越海仓WMS域名地址
define('YUE_HAI_INFORM_GOODS', YUE_HAI_URL . '/api/OpenApi/ReceiveCargo'); //越海WMS接口,商品资料推送接口
define('YUE_HAI_STORE', YUE_HAI_URL . '/api/OpenApi/ReceiveInboundOrder'); //越海WMS接口,收货接口
define('YUE_HAI_SALES', YUE_HAI_URL . '/api/OpenApi/ReceiveSaleOrder'); //越海WMS接口,销售出库接口
define('YUE_HAI_OTHER_OUT', YUE_HAI_URL . '/api/OpenApi/ReceiveOtherStockOutOrder'); //越海WMS接口,收货接口

/**
 * 百路池相关数据
 */
define('BAI_LU_URL', 'http://39.105.14.222/wms_api/wdt_service.php'); //百路池接口地址
