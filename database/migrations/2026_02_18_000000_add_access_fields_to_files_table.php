<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 为 files 表补充访问控制字段（公开/私有 + Key 哈希）
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // is_public=false 时表示私有；配合 password_hash 校验 6 位数字 Key
            $table->boolean('is_public')->default(true)->index();
            $table->string('password_hash')->nullable();

            // 常用查询索引（按目录 + 公开状态筛选）
            $table->index(['parent_id', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'is_public']);
            $table->dropIndex(['is_public']);

            $table->dropColumn(['is_public', 'password_hash']);
        });
    }
};
