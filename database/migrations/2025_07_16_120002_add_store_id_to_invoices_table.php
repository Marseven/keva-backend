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
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('payment_id');
            
            // Add indexes for store-specific invoice operations
            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'type']);
            $table->index(['store_id', 'issue_date']);
            $table->index(['store_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropIndex(['store_id', 'status']);
            $table->dropIndex(['store_id', 'type']);
            $table->dropIndex(['store_id', 'issue_date']);
            $table->dropIndex(['store_id', 'due_date']);
            $table->dropColumn('store_id');
        });
    }
};