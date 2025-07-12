<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // KEV-2024-0001
            $table->unsignedBigInteger('user_id'); // Sans FK

            // Montants
            $table->decimal('subtotal', 10, 0); // Sous-total
            $table->decimal('tax_amount', 10, 0)->default(0); // TVA
            $table->decimal('shipping_amount', 10, 0)->default(0); // Frais de livraison
            $table->decimal('discount_amount', 10, 0)->default(0); // Remise
            $table->decimal('total_amount', 10, 0); // Total final
            $table->string('currency', 3)->default('XAF');

            // Statuts
            $table->enum('status', [
                'pending',      // En attente
                'confirmed',    // Confirmée
                'processing',   // En traitement
                'shipped',      // Expédiée
                'delivered',    // Livrée
                'cancelled',    // Annulée
                'refunded'      // Remboursée
            ])->default('pending');

            $table->enum('payment_status', [
                'pending',      // En attente
                'paid',         // Payée
                'failed',       // Échec
                'refunded',     // Remboursée
                'partial'       // Paiement partiel
            ])->default('pending');

            // Informations de livraison
            $table->json('shipping_address'); // Adresse de livraison
            $table->json('billing_address');  // Adresse de facturation
            $table->string('shipping_method')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('tracking_number')->nullable();

            // Informations client
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('notes')->nullable(); // Notes du client
            $table->text('admin_notes')->nullable(); // Notes admin

            // Métadonnées
            $table->json('metadata')->nullable(); // Données additionnelles

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index(['order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
