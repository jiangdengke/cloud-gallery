<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->index();
            $table->string('password_hash')->nullable();

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

