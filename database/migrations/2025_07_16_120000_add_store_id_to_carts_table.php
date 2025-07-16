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
        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('product_id');
            
            // Add indexes for store-specific cart operations
            $table->index('store_id');
            $table->index(['user_id', 'store_id']);
            $table->index(['session_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropIndex(['user_id', 'store_id']);
            $table->dropIndex(['session_id', 'store_id']);
            $table->dropColumn('store_id');
        });
    }
};