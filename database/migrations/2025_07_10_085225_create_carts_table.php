<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Sans FK
            $table->string('session_id')->nullable(); // Pour les utilisateurs non connectés
            $table->unsignedBigInteger('product_id'); // Sans FK

            $table->integer('quantity');
            $table->decimal('unit_price', 10, 0); // Prix unitaire au moment de l'ajout
            $table->json('product_options')->nullable(); // Variantes sélectionnées

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
