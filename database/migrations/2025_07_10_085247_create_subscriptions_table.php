<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');

            // Informations abonnement
            $table->string('subscription_id')->unique(); // SUB-2024-0001
            $table->enum('status', [
                'active',       // Actif
                'pending',      // En attente
                'cancelled',    // Annulé
                'expired',      // Expiré
                'suspended'     // Suspendu
            ])->default('pending');

            // Dates importantes
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Tarification
            $table->decimal('amount', 10, 0); // Montant payé
            $table->string('currency', 3)->default('XAF');
            $table->boolean('auto_renew')->default(true);

            // Métadonnées
            $table->json('features_snapshot')->nullable(); // Fonctionnalités au moment de la souscription
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
