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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('user_id');
            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropIndex(['store_id', 'status']);
            $table->dropIndex(['store_id', 'user_id']);
            $table->dropColumn('store_id');
        });
    }
};
