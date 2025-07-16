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
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            
            // General settings
            $table->string('logo')->nullable();
            $table->string('banner_image')->nullable();
            $table->json('theme_colors')->nullable(); // Primary, secondary, accent colors
            $table->text('welcome_message')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('privacy_policy')->nullable();
            $table->text('return_policy')->nullable();
            
            // Business information
            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->text('business_address')->nullable();
            $table->json('business_hours')->nullable(); // Operating hours
            $table->json('social_links')->nullable(); // Facebook, Instagram, etc.
            
            // Operational settings
            $table->boolean('auto_accept_orders')->default(false);
            $table->boolean('require_phone_verification')->default(false);
            $table->boolean('allow_guest_checkout')->default(true);
            $table->boolean('enable_reviews')->default(true);
            $table->boolean('enable_wishlist')->default(true);
            $table->boolean('enable_notifications')->default(true);
            
            // Payment settings
            $table->json('accepted_payment_methods')->nullable();
            $table->decimal('minimum_order_amount', 10, 0)->default(0);
            $table->decimal('delivery_fee', 10, 0)->default(0);
            $table->decimal('free_delivery_threshold', 10, 0)->nullable();
            
            // Inventory settings
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_backorders')->default(false);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('hide_out_of_stock')->default(false);
            
            // SEO settings
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            
            // Advanced settings
            $table->json('custom_fields')->nullable(); // Custom store fields
            $table->json('integrations')->nullable(); // Third-party integrations
            $table->json('notification_settings')->nullable(); // Email/SMS preferences
            
            $table->timestamps();
            
            // Indexes
            $table->unique('store_id');
            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};