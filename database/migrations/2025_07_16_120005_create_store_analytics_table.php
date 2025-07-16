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
        Schema::create('store_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->date('date');
            
            // Sales metrics
            $table->integer('total_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('cancelled_orders')->default(0);
            $table->decimal('total_revenue', 12, 0)->default(0);
            $table->decimal('net_revenue', 12, 0)->default(0);
            
            // Product metrics
            $table->integer('products_sold')->default(0);
            $table->integer('unique_products_sold')->default(0);
            $table->integer('out_of_stock_products')->default(0);
            $table->integer('low_stock_products')->default(0);
            
            // Customer metrics
            $table->integer('new_customers')->default(0);
            $table->integer('returning_customers')->default(0);
            $table->integer('total_customers')->default(0);
            
            // Traffic metrics
            $table->integer('store_views')->default(0);
            $table->integer('product_views')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0); // Percentage
            
            // Payment metrics
            $table->decimal('average_order_value', 10, 0)->default(0);
            $table->integer('refunded_orders')->default(0);
            $table->decimal('refunded_amount', 10, 0)->default(0);
            
            // Performance metrics
            $table->decimal('fulfillment_rate', 5, 2)->default(0); // Percentage
            $table->integer('average_processing_time')->default(0); // Minutes
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['store_id', 'date']);
            $table->index(['store_id', 'date']);
            $table->index(['date', 'total_revenue']);
            $table->index(['store_id', 'total_orders']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_analytics');
    }
};