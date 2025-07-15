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
        Schema::create('store_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['owner', 'admin', 'manager', 'staff'])->default('staff');
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable(); // Additional permissions per role
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            
            // Ensure unique combination of store_id and user_id
            $table->unique(['store_id', 'user_id']);
            
            // Indexes for performance
            $table->index(['store_id', 'role']);
            $table->index(['user_id', 'role']);
            $table->index(['store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_user');
    }
};
