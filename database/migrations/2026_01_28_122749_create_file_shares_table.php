<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->onDelete('cascade');
            $table->string('token', 20)->unique()->comment('分享口令'); // 比如 /s/Ad3fQ2
            $table->string('password', 20)->nullable()->comment('提取码'); // 空代表公开
            $table->timestamp('expired_at')->nullable()->comment('过期时间'); // 空代表永久
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_shares');
    }
};
