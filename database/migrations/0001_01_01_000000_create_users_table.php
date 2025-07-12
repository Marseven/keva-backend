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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Informations personnelles
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->unique();
            $table->string('whatsapp_number')->nullable();
            $table->string('password');

            // Informations entreprise
            $table->string('business_name');
            $table->string('business_type');
            $table->string('city');
            $table->text('address');

            // Abonnement et status
            $table->string('selected_plan')->default('basic');
            $table->boolean('agree_to_terms')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            // Métadonnées
            $table->json('preferences')->nullable();
            $table->string('avatar')->nullable();
            $table->string('timezone')->default('Africa/Libreville');
            $table->string('language')->default('fr');

            $table->rememberToken();
            $table->timestamps();

            // Index pour performance
            $table->index(['email', 'is_active']);
            $table->index(['business_type', 'city']);
            $table->index(['is_admin', 'is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
