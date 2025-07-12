<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id'); // Sans FK
            $table->unsignedBigInteger('product_id'); // Sans FK

            $table->string('product_name'); // Nom du produit au moment de la commande
            $table->string('product_sku');  // SKU du produit
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 0); // Prix unitaire
            $table->decimal('total_price', 10, 0); // Prix total (quantity * unit_price)

            $table->json('product_options')->nullable(); // Variantes sélectionnées
            $table->json('product_snapshot')->nullable(); // Snapshot du produit

            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
