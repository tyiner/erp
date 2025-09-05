<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/**
 * 公共接口 路由
 */
Route::any('/test', 'Home\IndexController@test'); // 测试接口

Route::any('/edit_order_status', 'Home\TestController@editOrderStatus'); // TODO：修改订单状态（测试）
Route::get('/promoter', 'Home\TestController@getPromoterInfo'); // TODO：获取推广者（测试）

/**
 * 商城总后台 路由
 */
Route::namespace('Admin')->prefix('Admin')->group(function () {

    Route::post('/login', 'LoginController@login'); // 登陆
    Route::get('/logout', 'LoginController@logout'); // 退出账号
    Route::get('/check_login', 'LoginController@check_login'); // 检测登陆

    Route::group(['middleware' => 'jwt.admin'], function () {
        Route::get('/goods/batch', 'GoodsController@batchDetail');
        Route::get('/admins/search', 'AdminController@list');
        Route::apiResources([
            'admins' => 'AdminController', // 超级管理员
            'users' => 'UserController', // 平台用户
            'roles' => 'RoleController', // 用户角色
            'menus' => 'MenuController', // 用户菜单
            'permissions' => 'PermissionController', // 角色权限
            'permission_groups' => 'PermissionGroupController', // 接口权限分组
        ]);
        // 菜单处理
        Route::get('/menus/cache/clear', 'MenuController@clear_cache')->name('menus.clear_cache'); // 缓存清除接口
        Route::get('/user-menus', 'MenuController@userMenus')->name('user.menus'); // 获取用户菜单列表

        // 配置中心
        Route::apiResource('configs', 'ConfigController')->except(['update', 'show', 'destroy']);
        Route::post('/configs/upload/logo',
            'ConfigController@config_logo')->name('configs.config_logo'); // 配置中心图上传(Logo)
        Route::post('/configs/upload/icon',
            'ConfigController@config_icon')->name('configs.config_icon'); // 配置中心上传(icon)

        Route::apiResource('agreements', 'AgreementController'); // 站点协议
        Route::apiResource('articles', 'ArticleController'); // 文章，帮助中心

        // 物流公司
        Route::apiResource('expresses', 'ExpressController');

        // 编辑器上传图片接口
        Route::post('/editor/upload', 'EditorController@editor')->name('public.editor');  // 富文本编辑器图上传

        // 短信管理
        Route::apiResource('sms_logs', 'SmsLogController')->except(['update', 'show', 'store']); // 短信日志
        Route::apiResource('sms_signs', 'SmsSignController'); // 短信签名

        // 商品分类
        Route::apiResource('goods_classes', 'GoodsClassController');
        Route::post('/goods_classes/upload/thumb',
            'GoodsClassController@goods_class_upload')->name('goods_classes.goods_class_upload'); // 缩略图上传
        Route::get('/goods_classes/cache/clear',
            'GoodsClassController@clear_cache')->name('goods_classes.clear_cache'); // 缓存清除商品分类

        // 店铺管理
        Route::apiResource('stores', 'StoreController')->except(['store']);

        // 全国省市区地址
        Route::apiResource('areas', 'AreaController');
        Route::get('/areas/cache/clear', 'AreaController@clear_cache')->name('areas.clear_cache'); // 缓存清除行政地址

        // 商品品牌
        Route::apiResource('goods_brands', 'GoodsBrandController');
        Route::post('/goods_brands/upload/thumb',
            'GoodsBrandController@goods_brand_upload')->name('goods_brands.goods_brand_upload'); // 品牌缩略图上传
        Route::get('/goods_brands/cache/clear',
            'GoodsBrandController@clear_cache')->name('goods_brands.clear_cache'); // 缓存清除商品品牌

        // 属性规格
        Route::apiResource('goods_attrs', 'GoodsAttrController');

        // 商品管理
        Route::apiResource('goods', 'GoodsController');
        Route::get('store_goods_classes', 'GoodsController@goodsClasses')->name('goods.classes'); // 获取店铺有权的商品栏目信息
        Route::post('/goods/upload/images', 'GoodsController@goods_upload'); // 图片上传
        Route::post('/goods/upload/mobile_images', 'GoodsController@mobile_upload'); // 图片上传
        Route::post('/goods/publish/{id}', 'GoodsController@publish');
        Route::get('/goods_detail', 'GoodsController@detail');

        // 广告位管理
        Route::apiResource('adv_positions', 'AdvPositionController');
        Route::apiResource('advs', 'AdvController');
        Route::post('/advs/upload/thumb', 'AdvController@adv_upload')->name('advs.adv_upload'); // 缩略图上传

        // 订单管理
        Route::apiResource('orders', 'OrderController')->except(['store']);

        // 积分订单管理
        Route::apiResource('integral_orders', 'IntegralOrderController')->except(['store', 'destroy']);

        // 订单评论
        Route::apiResource('order_comments', 'OrderCommentController')->except(['store', 'update']);

        // 分销
        Route::apiResource('distribution_logs', 'DistributionLogController')->only(['index']); // 分销日志

        // 结算日志
        Route::apiResource('order_settlements', 'OrderSettlementController')->except(['update', 'destroy']);

        // 资金日志
        Route::apiResource('money_logs', 'MoneyLogController')->only(['index']);

        // 资金提现
        Route::apiResource('cashes', 'CashController')->except(['destroy', 'show']);

        // 积分商城
        Route::apiResource('integral_goods_classes', 'IntegralGoodsClassController'); // 积分商品栏目
        Route::apiResource('integral_goods', 'IntegralGoodsController'); // 积分商品
        Route::post('/integral_goods/upload/images',
            'IntegralGoodsController@goods_upload')->name('integral_goods.goods_upload'); // 图片上传
        Route::apiResource('integral_orders', 'IntegralOrderController'); // 积分订单

        // 数据统计
        Route::get('/statistics/all', 'StatisticController@all')->name('statistics.all'); // 所有
        Route::get('/statistics/user', 'StatisticController@user')->name('statistics.user'); // 用户
        Route::get('/statistics/store', 'StatisticController@store')->name('statistics.store'); // 店铺
        Route::get('/statistics/order', 'StatisticController@order')->name('statistics.order'); // 订单
        Route::get('/statistics/pay', 'StatisticController@pay')->name('statistics.pay'); // 支付

        // 客户（备货单位）
        Route::apiResource('customers', 'CustomerController');

    });

});

/**
 * 商城商家后台 路由
 */
Route::namespace('Seller')->prefix('Seller')->group(function () {

    Route::post('/login', 'LoginController@login'); // 登陆
    Route::get('/logout', 'LoginController@logout'); // 退出账号
    Route::get('/check_login', 'LoginController@check_login'); // 检测登陆

    Route::group(['middleware' => 'jwt.user'], function () {

        // 商家菜单
        Route::apiResource('menus', 'MenuController')->except(['update', 'show', 'store', 'destroy']);

        // 商品管理
        Route::apiResource('goods', 'GoodsController');
        Route::get('store_goods_classes', 'GoodsController@store_goods_classes'); // 获取店铺有权的商品栏目信息
        Route::get('goods_brands', 'GoodsBrandController@index'); // 商品品牌

        Route::apiResource('store_goods', 'StoreGoodsController');
        Route::post('/store_goods/publish/{id}', 'StoreGoodsController@publish'); // 图片上传

        Route::apiResource('goods_attrs', 'GoodsAttrController'); // 属性规格

        Route::post('/goods/upload/images', 'GoodsController@goods_upload'); // 图片上传
        Route::post('/editor/upload', 'EditorController@editor'); // 编辑器上传图片接口

        // 订单管理
        Route::apiResource('orders', 'OrderController')->except(['store', 'destroy']);
        Route::put('/order/{id}/audit', 'OrderController@audit');
        Route::put('/order/{id}/cancel', 'OrderController@cancel');
        Route::put('/order/{id}/delivery', 'OrderController@delivery'); // 发货&填写订单快递信息
        Route::put('/order/{id}/exchange', 'OrderController@exchange'); // 已收到退货&换货

        // 订单评论
        Route::apiResource('order_comments', 'OrderCommentController')->except(['store', 'destroy']);

        // 订单售后
        Route::apiResource('refunds', 'RefundController')->except(['index', 'store', 'destroy']);
        Route::put('/refund/{id}/agree', 'RefundController@agree'); // 售后同意
        Route::put('/refund/{id}/refuse', 'RefundController@refuse'); // 售后拒绝
        Route::put('/refund/{id}/refund', 'RefundController@refund'); // 商户确定收货并退款
//        Route::put('/refund/{id}/delivery', 'RefundController@delivery');

        // 物流公司
        Route::apiResource('expresses', 'ExpressController')->except(['update', 'store', 'destroy']);

        // 运费配置
        Route::apiResource('freights', 'FreightController')->except(['show', 'update']);

        // 客户管理
        Route::apiResource('members', 'MemberController')->only(['index', 'show', 'update']);
        Route::get('/member-accounts', 'MemberController@getAccountList');

        // 资金提现
        Route::apiResource('cashes', 'CashController')->except(['update', 'show', 'destroy']);

        // 分销管理
        Route::apiResource('distribution_settings', 'DistributionSettingController')->only(['index', 'store']); // 分销设置
        Route::get('distributions', 'DistributionController@index'); // 分销订单结算列表

        Route::apiResource('withdrawal_settings', 'WithdrawalSettingController')->only(['index', 'store']); // 提现设置
        Route::get('withdrawals', 'WithdrawalController@index'); // 提现列表

        // 优惠券
        Route::apiResource('coupons', 'CouponController');
        Route::apiResource('coupon_logs', 'CouponLogController')->only(['index']); // 优惠券日志

        // 满减
        Route::apiResource('full_reductions', 'FullReductionController');

        // 秒杀
        Route::apiResource('seckills', 'SeckillController');
        Route::get('seckills/goods/get_seckill_goods', 'SeckillController@get_seckill_goods'); // 获取商品列表

        // 拼团
        Route::apiResource('collectives', 'CollectiveController');
        Route::get('collectives/goods/get_collective_goods', 'CollectiveController@get_collective_goods'); // 获取商品列表
        Route::apiResource('collective_logs', 'CollectiveLogController')->only(['index']); // 拼团日志


        // 结算日志
        Route::apiResource('order_settlements', 'OrderSettlementController')->only(['index', 'show']);

        // 店铺资金日志
        Route::apiResource('money_logs', 'MoneyLogController')->only(['index']);

        // 商家配置
        Route::get('configs', 'ConfigController@show');
        Route::put('configs', 'ConfigController@update'); // 修改
        Route::post('configs/upload/images', 'ConfigController@config_upload'); // 配置上传图片

        // 全国省市区地址获取
        Route::get('/areas', 'AreaController@areas'); // 商家状态

        // 数据统计
        Route::get('/statistics/all', 'StatisticController@all'); // 所有
        Route::get('/statistics/order', 'StatisticController@order'); // 订单

    });

});

/**
 * 商城小程序端 路由
 */
Route::namespace('Home')->group(function () {

    // 获取站点协议
    Route::get('/agreement/{ename}', 'AgreementController@show');

    // 网站公共配置获取
    Route::get('/common', 'CommonController@common');

    // PC端首页
    Route::get('/index', 'IndexController@index');

    // 登录
    Route::post('/login_test', 'MemberController@loginTest'); // 登陆
    Route::any('/login', 'MemberController@login'); // 登陆
    Route::post('/register', 'LoginController@register'); // 注册
    Route::post('/forget_password', 'LoginController@forget_password'); // 忘记密码
    Route::get('/logout', 'LoginController@logout'); // 退出账号
    Route::get('/check_login', 'LoginController@check_login'); // 检测登陆
    Route::get('/send_sms', 'LoginController@send_sms'); // 发送短信

    // 商品
    Route::get('/goods_classes', 'GoodsClassController@goods_classes');
    Route::get('/goods_brands', 'GoodsBrandController@goods_brands');
//    Route::get('/goods/comment_statistics/{id}', 'GoodsController@goods_comment_statistics'); // 获取商品评论统计
//    Route::get('/goods/comments/{id}', 'GoodsController@goods_comments'); // 获取商品评论列表
//    Route::post('/goods/search/all', 'GoodsController@goods_search'); // 搜索商品列表

//    Route::apiResource('/store', 'StoreController')->only(['index', 'show']); // 店铺列表和详情
    Route::get('/store/{id}/goods', 'StoreController@getGoodsList'); // 店铺商品列表
    Route::get('/store/{id}/goods/{goods_id}', 'StoreController@getGoodsInfo'); // 店铺商品详情
    Route::post('/store/{id}/search', 'StoreController@goodsSearch'); // 店铺搜索商品
    Route::get('/store/{id}/goods_stock/{goods_id}', 'StoreController@getGoodsStock'); // 店铺商品库存

    // 积分商城
    Route::get('/integral', 'IntegralController@index'); // 首页数据
    Route::get('/integral/goods/{id}', 'IntegralController@show'); // 积分商品详情
    Route::get('/integral/goods_class', 'IntegralController@get_integral_class'); // 积分商品分类
    Route::post('/integral/search', 'IntegralController@search'); // 积分商品列表
    Route::post('/integral/pay', 'IntegralController@pay'); // 积分商品支付

    // 秒杀页面
    Route::get('/seckills', 'SeckillController@index'); // 首页数据

    // 支付
    Route::post('/wxpay/notify', 'PaymentController@wxNotify'); // 微信支付通知
    Route::post('/wxpay/refund', 'PaymentController@wxRefund'); // 微信退款通知
    Route::post('/alipay/notify', 'PaymentController@aliNotify');
    Route::post('/alipay/refund', 'PaymentController@aliRefund');

    Route::get('/express', 'ExpressController@index'); // 获取物流公司列表

    Route::group(['middleware' => 'jwt.member'], function () {

        // 购物车
        Route::apiResource('cart', 'CartController')->except(['show']);
        Route::get('/cart_count', 'CartController@cart_count'); // 获取购物车商品数量

        // 优惠券
        Route::get('/coupons', 'CouponController@index'); // 优惠券列表
        Route::post('/coupons/receive', 'CouponController@receive_coupon'); // 领取优惠券

        // 用户收货地址
        Route::apiResource('address', 'AddressController');
        Route::put('/address/default/set', 'AddressController@set_default'); // 设置默认地址
        Route::get('/address/default/get', 'AddressController@get_default'); // 获取默认地址

        // 用户
        Route::get('/user/default', 'UserController@default'); // 个人中心首页
        Route::post('bind-phone', 'MemberController@bindPhone');
        Route::get('/users/info', 'UserController@user_info'); // 获取用户资料
        Route::match(['get', 'put'], '/users/edit_user', 'UserController@edit_user'); // 修改用户资料
        Route::post('/users/avatar_upload', 'UserController@avatar_upload'); // 用户头像上传

        // 用户认证
        Route::get('/users/user_check', 'UserCheckController@user_check'); // 获取用户认证资料
        Route::post('/users/edit_user_check', 'UserCheckController@edit_user_check'); // 修改用户认证资料
        Route::post('/users/user_check_upload', 'UserCheckController@user_check_upload'); // 用户认证图片上传

        // 收藏/关注
        Route::apiResource('favorite', 'FavoriteController')->except(['update']);

        // 资金日志
        Route::apiResource('money_logs', 'MoneyLogController')->except(['update', 'show', 'store', 'destroy']);

        // 分销
        Route::get('distribution/link', 'DistributionController@link');

        // 资金提现
        Route::apiResource('cashes', 'CashController')->except(['update', 'show', 'destroy']);

        // 订单
        Route::apiResource('order', 'OrderController')->except(['destroy']); // 注意apiResource下get方法会匹配 /order/*
        Route::post('/order/create_before', 'OrderController@createOrderBefore'); // 生成订单前处理
        Route::post('/order/create_after', 'OrderController@createOrderAfter'); // 生成订单后处理
        Route::post('/order/pay', 'OrderController@pay'); // 订单支付
        Route::put('/order/cancel/{id}', 'OrderController@cancel'); // 取消订单
        Route::put('/order/confirm/{id}', 'OrderController@confirm'); // 确定收货

        // 订单售后
        Route::apiResource('refunds', 'RefundController')->except(['index', 'destroy']);
        Route::put('/refund/{id}/delivery', 'RefundController@delivery'); // 填写退货快递
        Route::put('/refund/{id}/cancel', 'RefundController@cancel'); // 取消申请售后

        // 积分订单
        Route::get('/integral_order', 'IntegralController@get_orders'); // 获取积分订单列表
        Route::get('/integral_order/get_order_info/{id}', 'IntegralController@get_order_info'); // 查看积分订单信息

        // 评论管理
        Route::apiResource('order_comments', 'OrderCommentController')->except(['destroy']);
        Route::post('/order_comments/thumb/upload', 'OrderCommentController@comment_upload'); // 评论管理图片上传

        // 商家入驻
        Route::get('/store/join/store_verify', 'StoreController@store_verify'); // 商家状态
        Route::match(['get', 'post'], '/store/join/store_join', 'StoreController@store_join');// 商家入驻
        Route::post('/store/join/upload', 'StoreController@store_join_upload'); // 商家入驻图片上传

        // 全国省市区地址获取
        Route::get('/areas', 'AreaController@areas'); // 商家状态

        // 帮助中心文章获取
        Route::get('/article/{ename}', 'ArticleController@show');
    });

});

/**
 * 在线聊天 路由 ['middleware'=>'jwt.user'],
 */
Route::namespace('Chat')->prefix('Chat')->middleware('jwt.user')->group(function () {

    Route::get('/friends', 'IndexController@friends'); //
    Route::post('/add_friend', 'IndexController@add_friend'); //
    Route::post('/chat_msg', 'IndexController@chat_msg'); //
    Route::post('/read_msg', 'IndexController@read_msg'); //
    Route::post('/chat_event', 'IndexController@chat_event'); //
    Route::post('/image', 'IndexController@image'); //

});
Route::namespace('Chat')->prefix('Seller')->middleware('jwt.user')->group(function () {
    // 商家端
    Route::get('/chat_friends', 'SellerChatController@friends'); //
    Route::post('/chat_msg', 'SellerChatController@chat_msg'); //
    Route::post('/chat_read_msg', 'SellerChatController@read_msg'); //
    Route::post('/chat_event', 'SellerChatController@chat_event'); //
});

/**
 * 商城支付回调|其他回调 路由
 */
Route::namespace('PayCallBack')->group(function () {

    Route::any('/payment/{name}', 'PaymentController@payment'); // 回调地址  [/api/payment/wechat] | [/api/payment/ali]
    Route::any('/oauth/{name}', 'OauthController@oauth'); // Oauth 第三方登录  [/api/oauth/wechat] | [/api/payment/github]
    Route::any('/oauth/callback/{name}',
        'OauthController@oauthCallback'); // Oauth 第三方登录回调地址  [/api/oauth/wechat] | [/api/payment/github]

});

Route::group(
    [],
    function () {
        $files = \Symfony\Component\Finder\Finder::create()->files()->in(__DIR__ . '/V1')->notName('web.php');
        foreach ($files as $file) {
            require $file->getPathname();
        }
    }
);
