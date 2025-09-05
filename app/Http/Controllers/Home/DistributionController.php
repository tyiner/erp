<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\DistributionService;
use App\Traits\HelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DistributionController extends Controller
{
    use HelperTrait;

    /**
     * 获取分销分享链接和图片
     * @return array
     */
    public function link()
    {
        try {
            $user = $this->getUser();
            if (!$user->canPromoteShare()) {
                $this->throwException('无权进行推广分享', 6110);
            }

            $link = env('APP_URL') . "/?store_id={$this->getStoreId()}&user_id={$this->getUserId()}";
            $qrcode = $this->qrCode($link);
            return $this->success(['qrcode' => $qrcode, 'link' => $link]);
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

}
