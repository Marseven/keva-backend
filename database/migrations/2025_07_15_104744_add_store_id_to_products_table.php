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
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable();
            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop foreign key first if it exists
            if (Schema::hasColumn('products', 'store_id')) {
                $table->dropForeign(['store_id']);
            }
            $table->dropIndex(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
