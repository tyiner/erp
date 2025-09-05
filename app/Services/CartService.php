<?php

namespace App\Services;

use App\Http\Resources\Home\CartResource\CartCollection;
use App\Models\Cart;
use App\Models\Goods;
use App\Models\GoodsSku;
use Illuminate\Support\Facades\DB;

class CartService extends BaseService
{

    // 获取购物车列表
    public function getCarts()
    {
        $condition = [
            'user_id' => $this->getUserId(),
            'store_id' => $this->getStoreId(),
        ];

        $list = Cart::where($condition)
            ->paginate(request()->per_page ?? 30);

        return $this->format(new CartCollection($list));
    }

    // 获取购物车数量
    public function getCount()
    {
        $cart_model = new Cart();

        // 获取当前用户user_id
        $user_service = new UserService;

        if (!$user_info = $user_service->getUserInfo('member')) {
            return $this->format_error(__('carts.add_error') . '4'); // 获取用户失败
        }

        $cart_count = $cart_model->where(['user_id' => $user_info['id']])
//                                ->groupBy('store_id')
            ->sum('buy_num');
        return $this->format($cart_count);
    }

    // 加入购物车
    public function addCart()
    {
        $goods_id = abs(intval(request()->goods_id));
        $sku_id = intval(request()->sku_id ?? 0);
        $buy_num = abs(intval(request()->buy_num ?? 1));

        // 判断是否非法上传
        if (empty($goods_id) || empty($buy_num)) {
            return $this->format_error(__('carts.add_error'));
        }

        // 获取SKU信息
        $sku_info = [];
        if (!empty($sku_id)) {
            $goods_sku_model = new GoodsSku();
            $sku_info = $goods_sku_model->find($sku_id);
            if ($sku_info->goods_id != $goods_id) {
                $sku_info = [];
                return $this->format_error(__('carts.add_error') . '2');
            }
        }

        // 获取当前用户user_id
        $user_service = new UserService;
        if (!$user_info = $user_service->getUserInfo('member')) {
            return $this->format_error(__('carts.add_error') . '4'); // 获取用户失败
        }

        // 获取商品&规格信息
        $goods_model = new Goods();
        $goods_info = $goods_model->select('id')->with('goods_skus')->where('id', $goods_id)->first();

        if (!empty(count($goods_info->goods_skus)) && $sku_id == 0) {
            return $this->format_error(__('carts.not_chose_sku')); // 未选择SKU
        }

        // 判断购物车有没有同款商品
        $cart_model = new Cart();
        $cart_info = $cart_model->where([
            'user_id' => $user_info['id'],
            'goods_id' => $goods_id,
            'sku_id' => $sku_id,
            'store_id' => $user_info['store_id'],
        ])->first();

        // 如果数据库不存在
        try {
            DB::beginTransaction(); // 事务开始
            if (empty($cart_info)) {
                // 加入购物车
                $cart_model->user_id = $user_info['id'];
                $cart_model->goods_id = $goods_id;
                $cart_model->sku_id = $sku_id;
                $cart_model->store_id = $user_info['store_id'];
                $cart_model->buy_num = $buy_num;
                $cart_model->save();

            } else {
                $cart_info->buy_num += $buy_num;
                $cart_info->save();
            }
            DB::commit(); // 事务提交
        } catch (\Exception $e) {
            DB::rollBack(); // 事务回滚
            return $this->format_error(__('carts.add_error') . '3');
        }

        return $this->format([], __('carts.add_success'));

    }

    // 修改购物车状态
    public function editCart($id)
    {
        $is_type = intval(request()->is_type ?? 0);
        $buy_num = abs(intval(request()->buy_num ?? 0));

        // 获取当前用户user_id
        $user_service = new UserService;
        if (!$user_info = $user_service->getUserInfo('member')) {
            return $this->format_error(__('carts.add_error') . '4'); // 获取用户失败
        }

        // 判断购物车有没有同款商品
        $cart_model = new Cart();
        $cart_info = $cart_model->where([
            'user_id' => $user_info['id'],
            'id' => $id,
        ])->first();

        if (empty($cart_info)) {
            return $this->format_error(__('carts.add_error') . '5'); // 获取用户失败
        }

        // 判断是否修改数量大于0
        if (!empty($buy_num) && $buy_num > 0) {
            $cart_info->buy_num = $buy_num;
            $cart_info->save();
            return $this->format(['buy_num' => $cart_info->buy_num], __('carts.edit_success'));
        }

        // 判断是增加还是减少
        if (empty($is_type)) {
            $buy_num = 0;
            if ($cart_info->buy_num <= 1) {
                $cart_model->where('user_id', $user_info['id'])->where('id', $id)->delete();
            } else {
                $cart_info->buy_num -= 1; // 加减购物车只能为1
                $cart_info->save();
                $buy_num = $cart_info->buy_num;
            }
            return $this->format(['buy_num' => $buy_num], __('carts.edit_success'));
        } else {
            $cart_info->buy_num += 1; // 加减购物车只能为1
            $cart_info->save();
            return $this->format(['buy_num' => $cart_info->buy_num], __('carts.edit_success'));
        }
    }


}
