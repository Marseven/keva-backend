<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Basic, Pro, Premium
            $table->string('slug')->unique(); // basic, pro, premium
            $table->text('description');
            $table->decimal('price', 10, 0); // Prix en XAF (pas de centimes)
            $table->integer('duration_days')->default(30); // Durée en jours
            $table->string('currency', 3)->default('XAF');

            // Fonctionnalités
            $table->json('features'); // Liste des fonctionnalités
            $table->integer('max_products')->default(0); // 0 = illimité
            $table->integer('max_orders')->default(0); // 0 = illimité
            $table->integer('max_storage_mb')->default(1000); // Stockage en MB
            $table->boolean('has_analytics')->default(false);
            $table->boolean('has_priority_support')->default(false);
            $table->boolean('has_custom_domain')->default(false);

            // Statut
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);

            // Offres spéciales
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->timestamp('discount_expires_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
