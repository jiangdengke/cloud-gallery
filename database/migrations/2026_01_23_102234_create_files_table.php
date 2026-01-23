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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            
            // 1. 文件夹逻辑
            // parent_id 为空表示根目录
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->boolean('is_folder')->default(false); // 标记是否为文件夹

            // 2. 基础信息
            $table->string('name'); // 文件名
            $table->unsignedBigInteger('size')->default(0); // 大小
            $table->string('mime_type')->nullable(); // 类型

            // 3. 核心：物理存储与秒传逻辑
            $table->string('disk_path')->nullable(); 
            // 文件的指纹 (Hash)。用于实现“秒传”。
            $table->string('hash', 64)->nullable()->index(); 

            // 4. 辅助字段
            $table->timestamps();
            $table->softDeletes(); // 软删除

            // 5. 索引优化
            $table->index(['parent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
