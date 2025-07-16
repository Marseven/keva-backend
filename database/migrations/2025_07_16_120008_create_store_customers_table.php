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
        Schema::create('store_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('user_id');
            
            // Customer relationship data
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 12, 0)->default(0);
            $table->decimal('average_order_value', 10, 0)->default(0);
            
            // Customer status
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->enum('tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            
            // Preferences
            $table->json('preferences')->nullable(); // Communication preferences, etc.
            $table->json('addresses')->nullable(); // Saved addresses
            $table->text('notes')->nullable(); // Store notes about customer
            
            // Loyalty metrics
            $table->integer('loyalty_points')->default(0);
            $table->decimal('lifetime_value', 12, 0)->default(0);
            $table->integer('referrals_count')->default(0);
            
            // Engagement metrics
            $table->integer('wishlist_items')->default(0);
            $table->integer('reviews_count')->default(0);
            $table->decimal('average_rating_given', 3, 2)->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['store_id', 'user_id']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'tier']);
            $table->index(['store_id', 'total_spent']);
            $table->index(['store_id', 'last_order_at']);
            $table->index(['store_id', 'loyalty_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_customers');
    }
};