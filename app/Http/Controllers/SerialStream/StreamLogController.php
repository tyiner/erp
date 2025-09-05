<?php

namespace App\Http\Controllers\SerialStream;

use App\Http\Controllers\Controller;
use App\Services\SerialStream\StreamLogService;
use Illuminate\Http\Request;

/**
 * Sn码信息推送
 * Class StreamLogController
 * @package App\Http\Controllers\SerialStream
 */
class StreamLogController extends Controller
{
    protected $service;

    public function __construct(StreamLogService $service)
    {
        $this->service = $service;
    }

    /**
     * 推送Sn码信息
     * @param Request $request
     */
    public function send(Request $request)
    {
        $rules = [
            'id' => 'required|int'
        ];
        $this->handleValidateRequest($request, $rules);
        $id = $request->input('id');
        $ret = $this->service->send($id);
    }
}
