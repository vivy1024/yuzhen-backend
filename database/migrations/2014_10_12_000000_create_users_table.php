<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('role')->default('user');
            $table->string('status', 1)->default('1')->comment('状态：1正常/0禁用');
            $table->string('del_flag', 1)->default('0')->comment('删除标志：0未删/1已删');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('membership_tier')->default('newbie')->comment('会员等级');
            $table->json('preferences')->nullable()->comment('用户偏好设置');
            $table->json('favorites')->nullable()->comment('收藏的动作ID列表');
            $table->json('exercise_reviews')->nullable()->comment('动作评价');
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('onboarding_completed')->default(false)->comment('是否完成引导');
            $table->timestamp('profile_completed_at')->nullable()->comment('档案完成时间');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

































