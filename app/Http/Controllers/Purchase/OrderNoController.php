<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderNoController extends Controller
{
    public function getName(Request $request)
    {
        $type = (int)$request->get('type');
        switch ($type) {
            //总公司采购计划单
            case PURCHASE_PLAN:
                $str = 'CGJH';
                break;
            //采购到货
            case PURCHASE_ARRIVAL:
                $str = 'CGDH';
                break;
            //采购入库
            case PURCHASE_STORE_IN:
                $str = 'CGRK';
                break;
            //采购退货计划
            case PURCHASE_BACK_PLAN:
                $str = 'CGTH';
                break;
            //采购退货出库单
            case PURCHASE_STORE_OUT:
                $str = 'CTCK';
                break;
            //采购其他入库
            case STOCK_OTHER_IN:
            case PURCHASE_OTHER_IN:
                $str = 'QTRK';
                break;
            //采购其他出库
            case STOCK_OTHER_OUT:
            case PURCHASE_OTHER_OUT:
                $str = 'QTCK';
                break;
            //采购其他出库红字
            case PURCHASE_OTHER_RED:
                $str = 'QTCH';
                break;
            //采购销售订单
            case PURCHASE_SALE:
                $str = 'XSDD';
                break;
            //销售发货
            case PURCHASE_SALE_SHIP:
                $str = 'XSFH';
                break;
            //采购销售出库
            case PURCHASE_SALE_OUT:
                $str = 'XSCK';
                break;
            //销售退货
            case PURCHASE_SALE_BACK:
                $str = 'XSTH';
                break;
            //销售出库红字
            case PURCHASE_SALE_RED:
                $str = 'XSHZ';
                break;
            //总公司备货计划单
            case PURCHASE_STOCK_PLAN:
                $str = 'BHJH';
                break;
            //总公司备货发货单
            case PURCHASE_STOCK_SHIP:
                $str = 'BHFH';
                break;
            //总公司备货出库单
            case PURCHASE_STOCK_OUT:
                $str = 'BHCK';
                break;
            //总公司备货退货单
            case PURCHASE_STOCK_BACK:
                $str = 'BHTH';
                break;
            //总公司备货出库单红字
            case PURCHASE_STOCK_RED:
                $str = 'BCHZ';
                break;
            case STOCK_TRANSFER:
            case PURCHASE_TRANSFER:
                $str = 'DB';
                break;

            //分公司备货计划
            case STOCK_PLAN:
                $str = 'BH';
                break;
            //备货到货
            case STOCK_ARRIVAL:
                $str = 'BHDH';
                break;
            //备货入库
            case STOCK_STORE_IN:
                $str = 'BHRK';
                break;
            //备货退货计划
            case STOCK_BACK_PLAN:
                $str = 'BTJH';
                break;
            //备货退货出库
            case STOCK_STORE_OUT:
                $str = 'BTCK';
                break;
            //分公司销售出库
            case STOCK_SALE_OUT:
                $str = 'FXSCK';
                break;
            //分公司销售退货
            case STOCK_SALE_BACK:
                $str = 'FXSTH';
                break;
            default:
                $str = 0;
        }
        if (0 === $str) {
            error('单据类型不存在');
        }
        success(orderNo($str));
    }
}
