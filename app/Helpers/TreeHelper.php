<?php

namespace App\Helpers;

class TreeHelper
{
    /**
     * 递归无线树状结构
     * @param $data
     * @param int $pid
     * @param int $lev
     * @return array
     */
    public static function normal($data, $pid = 0, $lev = 0)
    {
        static $arr = [];
        foreach ($data as $v) {
            if ($v['pid'] == $pid) {
                $v['lev'] = $lev;
                $arr[] = $v;
                self::normal($data, $v['id'], $lev + 1);
            }
        }
        return $arr;
    }

    /**
     * 递归无线树状结构 多元数组
     * @param $data
     * @param int $pid
     * @param int $lev
     * @return array
     */
    public static function children($data, $pid = 0, $lev = 0)
    {
        $arr = [];
        foreach ($data as $v) {

            if ($v['pid'] == $pid) {
                $v['lev'] = $lev;
                $v['children'] = self::children($data, $v['id'], $lev + 1);
                if (count($v['children']) <= 0) {
                    unset($v['children']);
                }
                $arr[] = $v;
            }
        }
        return $arr;
    }

    /**
     * 递归无线树状结构（按 code——用于 AreaService）
     * @param $data
     * @param int $pid
     * @param int $lev
     * @return array
     */
    public static function areaChildren($data, $pid = 0, $lev = 0)
    {
        $arr = [];
        foreach ($data as $v) {

            if ($v['pid'] == $pid) {
                $v['lev'] = $lev;
                $v['children'] = self::areaChildren($data, $v['code'], $lev + 1);
                if (count($v['children']) <= 0) {
                    unset($v['children']);
                }
                $arr[] = $v;
            }
        }
        return $arr;
    }

    /**
     * 按第一层平级递归（用于 GoodsService）
     * @param array $array1
     * @param array $array2
     * @param array $array3
     * @return array|mixed
     */
    public static function goodsChildren($array1 = [], &$array2 = [], $array3 = [])
    {
        if (is_array($array1)) {
            foreach ($array1 as $k => $v) {
                $item = new \stdClass();
                $item->id = $v['id'];
                $item->name = $v['name'];
                $array3[] = $item;
                if (isset($v['children']) && is_array($v['children'])) {
                    $array3 = self::goodsChildren($v['children'], $array2, $array3);
                } else {
                    $array2[] = $array3;
                }
                array_pop($array3);
            }
        }
        return $array3;
    }

    /**
     * 递归无线树状结构（只返回指定节点）
     * @param $data
     * @param int $pid
     * @param int $lev
     * @param array $allow 指定需要的叶节点
     * @return array
     */
    public static function filter($data, $pid = 0, $lev = 0, $allow = [])
    {
        $arr = [];
        foreach ($data as $v) {
            if ($v['pid'] == $pid) {
                $v['lev'] = $lev;
                $v['children'] = self::filter($data, $v['id'], $lev + 1, $allow);
                if (count($v['children']) <= 0) { // 叶节点
                    unset($v['children']);
                    if (in_array($v['id'], $allow)) {
                        $arr[] = $v;
                    }
                } else { // 非叶节点（根节点、枝节点）
                    $arr[] = $v;
                }
            }
        }
        return $arr;
    }
}
