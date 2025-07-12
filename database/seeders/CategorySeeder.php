<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Vêtements et Mode',
                'slug' => 'vetements-mode',
                'description' => 'Tous les articles de mode et vêtements',
                'icon' => 'shirt',
                'color' => '#FF6B6B',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'children' => [
                    ['name' => 'Homme', 'slug' => 'homme', 'sort_order' => 1],
                    ['name' => 'Femme', 'slug' => 'femme', 'sort_order' => 2],
                    ['name' => 'Enfant', 'slug' => 'enfant', 'sort_order' => 3],
                    ['name' => 'Chaussures', 'slug' => 'chaussures', 'sort_order' => 4],
                    ['name' => 'Accessoires', 'slug' => 'accessoires', 'sort_order' => 5],
                ]
            ],
            [
                'name' => 'Alimentation et Boissons',
                'slug' => 'alimentation-boissons',
                'description' => 'Produits alimentaires et boissons',
                'icon' => 'utensils',
                'color' => '#4ECDC4',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'children' => [
                    ['name' => 'Fruits et Légumes', 'slug' => 'fruits-legumes', 'sort_order' => 1],
                    ['name' => 'Viandes et Poissons', 'slug' => 'viandes-poissons', 'sort_order' => 2],
                    ['name' => 'Boissons', 'slug' => 'boissons', 'sort_order' => 3],
                    ['name' => 'Épicerie', 'slug' => 'epicerie', 'sort_order' => 4],
                ]
            ],
            [
                'name' => 'Technologie et Électronique',
                'slug' => 'technologie-electronique',
                'description' => 'Appareils électroniques et technologie',
                'icon' => 'laptop',
                'color' => '#45B7D1',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'children' => [
                    ['name' => 'Smartphones', 'slug' => 'smartphones', 'sort_order' => 1],
                    ['name' => 'Ordinateurs', 'slug' => 'ordinateurs', 'sort_order' => 2],
                    ['name' => 'Audio/Vidéo', 'slug' => 'audio-video', 'sort_order' => 3],
                    ['name' => 'Accessoires Tech', 'slug' => 'accessoires-tech', 'sort_order' => 4],
                ]
            ],
            [
                'name' => 'Beauté et Cosmétiques',
                'slug' => 'beaute-cosmetiques',
                'description' => 'Produits de beauté et cosmétiques',
                'icon' => 'sparkles',
                'color' => '#F093FB',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 4,
                'children' => [
                    ['name' => 'Soins du visage', 'slug' => 'soins-visage', 'sort_order' => 1],
                    ['name' => 'Maquillage', 'slug' => 'maquillage', 'sort_order' => 2],
                    ['name' => 'Parfums', 'slug' => 'parfums', 'sort_order' => 3],
                    ['name' => 'Soins cheveux', 'slug' => 'soins-cheveux', 'sort_order' => 4],
                ]
            ],
            [
                'name' => 'Maison et Décoration',
                'slug' => 'maison-decoration',
                'description' => 'Articles pour la maison et décoration',
                'icon' => 'home',
                'color' => '#96CEB4',
                'is_active' => true,
                'sort_order' => 5,
                'children' => [
                    ['name' => 'Mobilier', 'slug' => 'mobilier', 'sort_order' => 1],
                    ['name' => 'Décoration', 'slug' => 'decoration', 'sort_order' => 2],
                    ['name' => 'Électroménager', 'slug' => 'electromenager', 'sort_order' => 3],
                    ['name' => 'Jardin', 'slug' => 'jardin', 'sort_order' => 4],
                ]
            ],
            [
                'name' => 'Sport et Loisirs',
                'slug' => 'sport-loisirs',
                'description' => 'Articles de sport et loisirs',
                'icon' => 'dumbbell',
                'color' => '#FECA57',
                'is_active' => true,
                'sort_order' => 6,
                'children' => [
                    ['name' => 'Fitness', 'slug' => 'fitness', 'sort_order' => 1],
                    ['name' => 'Sports collectifs', 'slug' => 'sports-collectifs', 'sort_order' => 2],
                    ['name' => 'Sports nautiques', 'slug' => 'sports-nautiques', 'sort_order' => 3],
                    ['name' => 'Outdoor', 'slug' => 'outdoor', 'sort_order' => 4],
                ]
            ],
            [
                'name' => 'Automobile',
                'slug' => 'automobile',
                'description' => 'Tout pour l\'automobile',
                'icon' => 'car',
                'color' => '#FF9FF3',
                'is_active' => true,
                'sort_order' => 7,
                'children' => [
                    ['name' => 'Pièces détachées', 'slug' => 'pieces-detachees', 'sort_order' => 1],
                    ['name' => 'Accessoires', 'slug' => 'accessoires-auto', 'sort_order' => 2],
                    ['name' => 'Entretien', 'slug' => 'entretien', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'Santé et Bien-être',
                'slug' => 'sante-bien-etre',
                'description' => 'Produits de santé et bien-être',
                'icon' => 'heart',
                'color' => '#54A0FF',
                'is_active' => true,
                'sort_order' => 8,
                'children' => [
                    ['name' => 'Compléments alimentaires', 'slug' => 'complements-alimentaires', 'sort_order' => 1],
                    ['name' => 'Hygiène', 'slug' => 'hygiene', 'sort_order' => 2],
                    ['name' => 'Bien-être', 'slug' => 'bien-etre', 'sort_order' => 3],
                ]
            ]
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );

            // Créer les sous-catégories
            foreach ($children as $childData) {
                $childData['parent_id'] = $category->id;
                $childData['is_active'] = true;
                $childData['color'] = $category->color;

                Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    $childData
                );
            }
        }
    }
}
