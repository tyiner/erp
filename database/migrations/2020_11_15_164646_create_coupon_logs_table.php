<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coupon_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name','30')->default('优惠券')->comment('标题');
            $table->unsignedInteger('coupon_id')->default(0)->comment('优惠券ID');
            $table->unsignedInteger('user_id')->default(0)->comment('用户ID');
            $table->unsignedInteger('store_id')->default(0)->comment('店铺ID');
            $table->unsignedInteger('order_id')->default(0)->comment('订单ID');
            $table->unsignedDecimal('money',9,2)->default(0.00)->comment('分销金额');
            $table->unsignedDecimal('use_money',6,2)->default(0.00)->comment('分佣率');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态 0未使用 1使用');
            $table->timestamp('start_time')->default(now())->comment('开始时间');
            $table->timestamp('end_time')->default(now()->addDays(5))->comment('结束');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coupon_logs');
    }
}
