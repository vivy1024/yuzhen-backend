<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户协议同意记录表
 *
 * 记录用户同意隐私政策和用户协议的时间、版本，用于合规举证。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('consent_type', 30)->comment('terms|privacy');
            $table->string('consent_version', 20)->comment('协议版本，如 2026-03-01');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();

            $table->index(['user_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consent_records');
    }
};
