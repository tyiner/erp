<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource\UserCollection;
use App\Models\Store;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request,User $user_model)
    {
        $list = $user_model->orderBy('id','desc')->paginate($request->per_page??30);
        return $this->success(new UserCollection($list) );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request,User $user_model)
    {
        if($user_model->where('username',$request->username)->exists()){
            return $this->error(__('admins.username_existence'));
        }
        if($user_model->where('phone',$request->phone)->exists()){
            return $this->error(__('admins.phone_existence'));
        }
        /*$user_model = $user_model->create([
            'username'      =>  $request->username,
            'phone'         =>  $request->phone,
            'password'      =>  Hash::make($request->password??'123456'),
            'nickname'      =>  $request->nickname??'神秘人',
            'avatar'        =>  $request->avatar??'',
        ]);*/
        $user_service = new UserService();
        $rs = $user_service->createUserAndStore();

        return $rs['status']?$this->success($rs['data']):$this->error($rs['msg']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(User $user_model,$id)
    {
        $info = $user_model->find($id);

        $store_model = new Store();
        $store_info = $store_model->where('user_id', $id)->first();

        $info->store_name = $store_info->store_name;
        $info->due = $store_info->due;
        $info->store_status = $store_info->store_status;
        $info->copr_logo = $store_info->copr_logo;
        $info->team_num = $store_info->team_num;
        $info->msg_num = $store_info->msg_num;

        return $this->success($info);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,User $user_model, $id)
    {
        if($user_model->where('username',$request->username)
                        ->where('id','<>',$id)
                        ->exists()
        ){
            return $this->error(__('admins.username_existence'));
        }
        if($user_model->where('phone',$request->phone)
                        ->where('id','<>',$id)
                        ->exists()
        ){
            return $this->error(__('admins.phone_existence'));
        }
//        $user_model = $user_model->find($id);
//        $user_model->username = $request->username;
//        if(!empty($request->password)){
//            $user_model->password = Hash::make($request->password??'123456');
//        }
//        $user_model->nickname = $request->nickname??'神秘人';
//        $user_model->avatar = $request->avatar??'';
//        $user_model->save();
        $user_service = new UserService();
        $rs = $user_service->updateUserAndStore($id);

        return $rs['status']?$this->success($rs['data']):$this->error($rs['msg']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user_model,$id)
    {
        $idArray = array_filter(explode(',',$id),function($item){
            return is_numeric($item);
        });

        $user_model->destroy($idArray);
        return $this->success([],__('base.success'));
    }
}
