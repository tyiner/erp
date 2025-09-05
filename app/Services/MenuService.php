<?php

namespace App\Services;

use App\Helpers\TreeHelper;
use App\Models\Admin;
use App\Models\Menu;
use Illuminate\Support\Facades\Cache;

class MenuService extends BaseService
{
    public function getMenus()
    {
        $is_type = request()->get('is_type') ?? Menu::TYPE_ADMIN;
        if ($this->isSeller()) {
            $is_type = Menu::TYPE_SELLER;
        }
        $menu_model = new Menu();
//        $cache_name = $is_type == 0 ? 'admin_menus_cache' : 'seller_menus_cache';
        $list = [];
//        if (!Cache::has($cache_name)) {
            $menus_list = $menu_model->where('is_type', $is_type)->orderBy('is_sort')->get()->toArray();
            $list = TreeHelper::children($menus_list);
//            Cache::set($cache_name, $list);
//        } else {
//            $list = Cache::get($cache_name);
//        }
        return $this->format($list);
    }

    // 清空缓存
    public function clearCache()
    {
        $rs = Cache::forget('admin_menus_cache');
        $rs = Cache::forget('seller_menus_cache');
        return $this->format($rs);
    }

    /**
     * 获取用户角色菜单
     * @param int $isType
     * @return array
     */
    public function getUserMenus($isType = Menu::TYPE_ADMIN) {
        $allow = [];
        $user = $this->getUser();
//        $user = Admin::find(request()->admin_id);
        $user->roles->map(function ($role) use (&$allow) {
            $role->menus->map(function ($menu) use (&$allow) {
                $allow[$menu->id] = $menu->toArray();
            });
        });
        $allow = array_keys($allow);
        $menus = Menu::where('is_type', $isType)->orderBy('is_sort')->get()->toArray();
        $tree = TreeHelper::filter($menus, 0, 0, $allow);
        return $this->format($tree);
    }
}
