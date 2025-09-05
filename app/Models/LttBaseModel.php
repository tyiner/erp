<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * lititong  base Model
 * Class LttBaseModel
 * @package App\Models
 */
class LttBaseModel extends Model
{
    use SoftDeletes;

    /**
     * 批量添加数据
     * @param array $data
     * @return bool
     */
    public function addAll(array $data): bool
    {
        $rs = DB::table($this->getTable())->insert($data);
        return $rs;
    }
}
