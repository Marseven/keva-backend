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
        Schema::table('product_images', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('product_id');
            
            // Add indexes for store-specific image operations
            $table->index('store_id');
            $table->index(['store_id', 'product_id']);
            $table->index(['store_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropIndex(['store_id', 'product_id']);
            $table->dropIndex(['store_id', 'is_primary']);
            $table->dropColumn('store_id');
        });
    }
};