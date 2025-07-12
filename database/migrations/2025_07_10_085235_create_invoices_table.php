<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // FAC-2024-0001
            $table->unsignedBigInteger('user_id'); // Sans FK
            $table->unsignedBigInteger('order_id')->nullable(); // Sans FK
            $table->unsignedBigInteger('payment_id')->nullable(); // Sans FK

            // Informations facture
            $table->enum('type', ['invoice', 'quote', 'receipt', 'refund'])->default('invoice');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');

            // Montants
            $table->decimal('subtotal', 10, 0);
            $table->decimal('tax_amount', 10, 0)->default(0);
            $table->decimal('discount_amount', 10, 0)->default(0);
            $table->decimal('total_amount', 10, 0);
            $table->string('currency', 3)->default('XAF');

            // Informations client
            $table->json('client_details'); // Nom, adresse, etc.
            $table->json('seller_details'); // Informations du vendeur

            // Lignes de facture
            $table->json('line_items'); // Produits/services facturés

            // Dates
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Fichiers
            $table->string('pdf_path')->nullable(); // Chemin du PDF généré

            // Notes
            $table->text('notes')->nullable();
            $table->text('terms')->nullable(); // Conditions de paiement

            // Métadonnées
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['order_id']);
            $table->index(['status', 'due_date']);
            $table->index(['issue_date', 'type']);
            $table->index(['invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
