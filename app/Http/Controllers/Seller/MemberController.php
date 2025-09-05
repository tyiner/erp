<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\MemberResource\MemberAccountCollection;
use App\Http\Resources\Seller\MemberResource\MemberCollection;
use App\Http\Resources\Seller\MemberResource\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MemberController extends Controller
{
    /**
     * 客户列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        try {
            $params = [
                'store_id' => $this->getStoreId(),
            ];

            if (!empty($request->phone)) {
                $params['phone'] = $request->phone;
            }

            if (!empty($request->nickname)) {
                $params['nickname'] = $request->nickname;
            }

            if (!empty($request->parent_id)) {
                $params['parent_id'] = $request->parent_id;
            }

            if (!empty($request->head_id)) {
                $params['head_id'] = $request->head_id;
            }

            // 用户类型（0/普通 1/金牌 2/推广）
            if (isset($request->type)) {
                $params['type'] = $request->type;
            }

            // 状态（0/正常 -1/拉黑）
            if (isset($request->status)) {
                $params['status'] = $request->status;
            }

            $list = Member::where($params)
                ->orderBy('id', 'desc')
                ->paginate($request->per_page ?? 30);
            return $this->success(new MemberCollection($list));
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 客户详情
     * @param MemberService $service
     * @param $id
     * @return array
     */
    public function show(MemberService $service, $id) {
        try {
            $member = $service->get($id);
            return $this->success(new MemberResource($member));
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 客户修改
     * @param Request $request
     * @param $id
     * @return array
     */
    public function update(Request $request, $id) {
        try {
            $member = Member::find($id);

            if (isset($request->type)) {
                if (!Arr::exists(Member::getTypeOptions(), $request->type)) {
                    $this->throwException('客户类型不正确', 5111);
                }
                $member->type = $request->type;
            }

            if (isset($request->status)) {
                if (!Arr::exists(Member::getStatusOptions(), $request->status)) {
                    $this->throwException('客户状态不正确', 5112);
                }
                $member->status = $request->status;
            }

            if (isset($request->parent_id)) {
                $member->parent_id = $request->parent_id;
            }

            if (isset($request->head_id)) {
                $member->head_id = $request->head_id;
            }

            $member->save();

            return $this->success();
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }

    /**
     * 获取账户列表
     * @param Request $request
     * @param MemberService $service
     * @return array
     */
    public function getAccountList(Request $request, MemberService $service) {
        try {
            $list = Member::where('store_id', $this->getStoreId())
                ->orderBy('id', 'desc')
                ->paginate($request->per_page ?? 30);
            return $this->success(new MemberAccountCollection($list));
        } catch (\Exception $e) {
            Log::error($e->getTraceAsString());
            return $this->error($e->getMessage(), ['errno' => $e->getCode()]);
        }
    }
}
