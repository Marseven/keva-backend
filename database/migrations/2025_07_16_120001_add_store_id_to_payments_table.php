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
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('order_id');
            
            // Add indexes for store-specific payment operations
            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'payment_method']);
            $table->index(['store_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropIndex(['store_id', 'status']);
            $table->dropIndex(['store_id', 'payment_method']);
            $table->dropIndex(['store_id', 'created_at']);
            $table->dropColumn('store_id');
        });
    }
};