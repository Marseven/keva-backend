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
        Schema::create('store_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('user_id'); // Store staff member receiving notification
            
            // Notification content
            $table->string('title');
            $table->text('message');
            $table->enum('type', [
                'order_placed',
                'order_cancelled',
                'payment_received',
                'product_low_stock',
                'product_out_of_stock',
                'new_customer',
                'new_review',
                'system_alert',
                'custom'
            ]);
            
            // Related entities
            $table->unsignedBigInteger('related_id')->nullable(); // Order ID, Product ID, etc.
            $table->string('related_type')->nullable(); // Order, Product, etc.
            
            // Notification status
            $table->timestamp('read_at')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->json('actions')->nullable(); // Available actions for notification
            
            // Delivery channels
            $table->boolean('sent_email')->default(false);
            $table->boolean('sent_sms')->default(false);
            $table->boolean('sent_push')->default(false);
            
            // Metadata
            $table->json('data')->nullable(); // Additional data for the notification
            
            $table->timestamps();
            
            // Indexes
            $table->index(['store_id', 'user_id']);
            $table->index(['store_id', 'type']);
            $table->index(['store_id', 'read_at']);
            $table->index(['store_id', 'priority']);
            $table->index(['store_id', 'created_at']);
            $table->index(['related_id', 'related_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_notifications');
    }
};