<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Express;
use Illuminate\Http\Request;

class ExpressController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $list = Express::orderBy('id', 'asc')->get();
        return $this->success($list);
    }
}
