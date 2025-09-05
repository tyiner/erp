<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CustomerResource\CustomerCollection;
use App\Models\Area;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $customer = new Customer();
        if (!empty($request->name)) {
            $customer->where('name', '%' . $request->name . '%');
        }
        if (!empty($request->phone)) {
            $customer->where('phone', '%' . $request->phone . '%');
        }
        $list = $customer->orderBy('id')->paginate($request->per_page ?? 30);
        return $this->success(new CustomerCollection($list));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $customer = new Customer();
        $customer->name = $request->name ?? '';
        $customer->contact_name = $request->contact_name ?? '';
        $customer->phone = $request->phone ?? '';
        $customer->province_id = $request->province_id;
        $customer->city_id = $request->city_id;
        $customer->region_id = $request->region_id;
        $customer->address = $request->address;
        $customer->save();
        return $this->success([]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $info = Customer::find($id);
        return $this->success($info);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);
        $customer->name = $request->name ?? '';
        $customer->contact_name = $request->contact_name ?? '';
        $customer->phone = $request->phone ?? '';
        $customer->province_id = $request->province_id;
        $customer->city_id = $request->city_id;
        $customer->region_id = $request->region_id;
        $customer->address = $request->address;
        $customer->save();
        return $this->success([]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $idArray = array_filter(explode(',', $id), function ($item) {
            return is_numeric($item);
        });
        Customer::destroy($idArray);
        return $this->success([]);
    }
}
