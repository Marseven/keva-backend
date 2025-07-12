<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Sans FK
            $table->unsignedBigInteger('order_id')->nullable(); // Sans FK

            // Identifiants de transaction
            $table->string('payment_id')->unique(); // ID interne
            $table->string('transaction_id')->nullable(); // ID du fournisseur
            $table->string('bill_id')->nullable(); // ID EBILLING
            $table->string('external_reference')->nullable(); // Référence externe

            // Montants
            $table->decimal('amount', 10, 0);
            $table->string('currency', 3)->default('XAF');

            // Méthode de paiement
            $table->enum('payment_method', [
                'airtel_money',
                'moov_money',
                'visa_mastercard',
                'bank_transfer',
                'cash',
                'other'
            ]);

            // Fournisseur de paiement
            $table->string('payment_provider')->default('ebilling'); // ebilling, stripe, paypal, etc.

            // Statuts
            $table->enum('status', [
                'pending',      // En attente
                'processing',   // En cours de traitement
                'completed',    // Terminé avec succès
                'failed',       // Échec
                'cancelled',    // Annulé
                'refunded'      // Remboursé
            ])->default('pending');

            // Informations du payeur
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payer_phone')->nullable();

            // Métadonnées
            $table->json('gateway_response')->nullable(); // Réponse du fournisseur
            $table->json('metadata')->nullable(); // Données additionnelles
            $table->text('failure_reason')->nullable(); // Raison de l'échec

            // Dates importantes
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index(['payment_method', 'status']);
            $table->index(['bill_id']);
            $table->index(['transaction_id']);
            $table->index(['payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
