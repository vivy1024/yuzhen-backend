<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider')->comment('登录提供商：wechat/qq/weibo/apple');
            $table->string('provider_id')->comment('第三方用户ID');
            $table->string('provider_token')->nullable()->comment('访问令牌');
            $table->string('provider_refresh_token')->nullable()->comment('刷新令牌');
            $table->json('provider_data')->nullable()->comment('第三方用户数据');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['provider', 'provider_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};



































