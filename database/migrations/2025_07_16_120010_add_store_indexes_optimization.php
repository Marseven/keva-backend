<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add additional indexes for better performance in multi-store queries
     */
    public function up(): void
    {
        // Optimize products table for store-specific queries
        Schema::table('products', function (Blueprint $table) {
            $table->index(['store_id', 'status', 'is_featured']);
            $table->index(['store_id', 'category_id', 'status']);
            $table->index(['store_id', 'published_at', 'status']);
            $table->index(['store_id', 'stock_quantity', 'track_inventory']);
            $table->index(['store_id', 'price']);
            $table->index(['store_id', 'created_at']);
        });
        
        // Optimize orders table for store-specific queries
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['store_id', 'payment_status']);
            $table->index(['store_id', 'created_at', 'status']);
            $table->index(['store_id', 'total_amount']);
        });
        
        // Optimize order_items table for store-specific reporting
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['product_id', 'created_at']);
        });
        
        // Optimize store_user table for role-based queries
        Schema::table('store_user', function (Blueprint $table) {
            $table->index(['user_id', 'is_active']);
            $table->index(['role', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'status', 'is_featured']);
            $table->dropIndex(['store_id', 'category_id', 'status']);
            $table->dropIndex(['store_id', 'published_at', 'status']);
            $table->dropIndex(['store_id', 'stock_quantity', 'track_inventory']);
            $table->dropIndex(['store_id', 'price']);
            $table->dropIndex(['store_id', 'created_at']);
        });
        
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'payment_status']);
            $table->dropIndex(['store_id', 'created_at', 'status']);
            $table->dropIndex(['store_id', 'total_amount']);
        });
        
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'created_at']);
        });
        
        Schema::table('store_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropIndex(['role', 'is_active']);
        });
    }
};