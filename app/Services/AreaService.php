<?php

namespace App\Services;

use App\Helpers\TreeHelper;
use App\Models\Area;
use Illuminate\Support\Facades\Cache;

class AreaService extends BaseService
{
    public $cache_name = 'areas_cache';

    public function getAreas()
    {
        $area_model = new Area();
        $cache_name = $this->cache_name;
        $list = [];
        if (!Cache::has($cache_name)) {
            $area_list = $area_model->select('id', 'pid', 'name', 'code', 'deep')->orderBy('id', 'asc')->get()->toArray();
            $list = TreeHelper::areaChildren($area_list);
            Cache::set($cache_name, $list);
        } else {
            $list = Cache::get($cache_name);
        }
        return $this->format($list);
    }

    // 清空缓存
    public function clearCache()
    {
        $rs = Cache::forget($this->cache_name);
        return $this->format($rs);
    }
}
