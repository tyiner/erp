<?php

namespace App\Services;

use App\Http\Resources\Home\FavoriteResource\FavoriteCollection;
use App\Http\Resources\Home\FavoriteResource\FollowCollection;
use App\Models\Favorite;
use Illuminate\Support\Facades\DB;

class FavoriteService extends BaseService
{

    public function getFav()
    {
        $user_service = new UserService;
        if (!$user_info = $user_service->getUserInfo('member')) {
            return $this->format_error(__('carts.add_error') . '4'); // 获取用户失败
        }

        $fav_model = new Favorite();
        $fav_model = $fav_model->where([
            'user_id' => $user_info['id'],
            'store_id' => $user_info['store_id'],
        ]);
        $fav_model = $fav_model->with(['goods' => function ($q) {
            return $q->select('id', 'goods_master_image', 'goods_price', 'goods_name', 'goods_subname')->with('goods_sku');
        }]);
        $fav_list = $fav_model->paginate(request()->per_page ?? 30);
        return $this->format(new FavoriteCollection($fav_list));
    }

    // 添加收藏和关注
    public function addFav()
    {
        $goods_id = request()->id;
        $user_service = new UserService;
        if (!$user_info = $user_service->getUserInfo('member')) {
            return $this->format_error(__('carts.add_error') . '4'); // 获取用户失败
        }

        $fav_model = new Favorite();
        $fav_info = $fav_model->where([
            'user_id' => $user_info['id'],
            'goods_id' => $goods_id,
            'store_id' => $user_info['store_id'],
        ])->first();
        if (!empty($fav_info)) {
            return $this->format([], __('base.success'));
        }

        $fav_model->user_id = $user_info['id'];
        $fav_model->goods_id = $goods_id;
        $fav_model->store_id = $user_info['store_id'];
        $fav_model->save();
        return $this->format([], __('base.success'));

    }

    // 删除
    public function delFav($goods_id)
    {
        $user_service = new UserService;
        if (!$user_info = $user_service->getUserInfo('member')) {
            return $this->format_error(__('carts.add_error') . '4'); // 获取用户失败
        }

        $fav_model = new Favorite();
        $fav_model->whereIn('goods_id', $goods_id)
            ->where([
                'user_id' => $user_info['id'],
                'store_id' => $user_info['store_id'],
            ])
            ->delete();
        return $this->format([], __('base.success'));
    }

    // 判断是否有收藏
    public function isFav($goods_id)
    {
        $user_service = new UserService;
        if (!$user_info = $user_service->getUserInfo('member')) {
            return $this->format_error(__('carts.add_error') . '4'); // 获取用户失败
        }

        $fav_model = new Favorite();
        $fav_info = $fav_model->where([
            'user_id' => $user_info['id'],
            'goods_id' => $goods_id,
            'store_id' => $user_info['store_id'],
        ])->first();
        if (empty($fav_info)) {
            return $this->format_error();
        }
        return $this->format($fav_info);
    }

}
