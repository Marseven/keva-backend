<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Propriétaire (sans FK)
            $table->unsignedBigInteger('category_id'); // Catégorie (sans FK)

            // Informations de base
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->string('sku')->unique(); // Code produit

            // Prix et stock
            $table->decimal('price', 10, 0); // Prix en XAF
            $table->decimal('compare_price', 10, 0)->nullable(); // Prix barré
            $table->decimal('cost_price', 10, 0)->nullable(); // Prix d'achat
            $table->string('currency', 3)->default('XAF');

            // Gestion stock
            $table->boolean('track_inventory')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_level')->default(5); // Alerte stock bas
            $table->boolean('allow_backorder')->default(false);

            // Caractéristiques physiques
            $table->decimal('weight', 8, 2)->nullable(); // kg
            $table->json('dimensions')->nullable(); // {length, width, height}
            $table->string('condition')->default('new'); // new, used, refurbished

            // Images et médias
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable(); // Array d'images
            $table->string('video_url')->nullable();

            // SEO et métadonnées
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('tags')->nullable(); // Tags de recherche

            // Attributs personnalisés
            $table->json('attributes')->nullable(); // Couleur, taille, etc.
            $table->json('variants')->nullable(); // Variantes du produit

            // Statut et visibilité
            $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_digital')->default(false);
            $table->timestamp('published_at')->nullable();

            // Statistiques
            $table->integer('views_count')->default(0);
            $table->integer('sales_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('reviews_count')->default(0);

            $table->timestamps();

            // Index pour performance
            $table->index(['user_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'is_featured']);
            $table->index(['published_at', 'status']);
            $table->index(['sku']);
            $table->index(['slug']);
            $table->fullText(['name', 'description', 'short_description']); // Recherche full-text
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
