<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    /** @var int 后台菜单 */
    const TYPE_ADMIN = 0;
    /** @var int 商家菜单 */
    const TYPE_SELLER = 1;
}
