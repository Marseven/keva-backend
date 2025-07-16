<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $storeName = $this->faker->randomElement([
            'Boutique ' . $this->faker->firstName,
            'Chez ' . $this->faker->firstName,
            $this->faker->company,
            $this->faker->firstName . ' Store',
            'Magasin ' . $this->faker->lastName,
            'Commerce ' . $this->faker->firstName,
            'Vente ' . $this->faker->lastName,
            'Shop ' . $this->faker->firstName,
            'Boutique Mode ' . $this->faker->lastName,
            'Marché ' . $this->faker->firstName,
        ]);

        $storeTypes = [
            'Vêtements et mode',
            'Beauté et cosmétiques',
            'Technologie et électronique',
            'Alimentation et boissons',
            'Maison et jardin',
            'Sport et loisirs',
            'Livres et papeterie',
            'Artisanat local',
            'Bijoux et accessoires',
            'Santé et bien-être',
        ];

        $cities = [
            'Libreville',
            'Port-Gentil',
            'Franceville',
            'Oyem',
            'Moanda',
            'Mouila',
            'Lambaréné',
            'Tchibanga',
            'Koulamoutou',
            'Makokou',
        ];

        $descriptions = [
            'Boutique spécialisée dans la vente de produits de qualité',
            'Votre magasin de confiance pour tous vos besoins',
            'Des produits authentiques et de qualité supérieure',
            'Service client exceptionnel et produits garantis',
            'Découvrez notre large gamme de produits',
            'Qualité, service et prix compétitifs',
            'Votre satisfaction est notre priorité',
            'Des produits soigneusement sélectionnés pour vous',
            'Innovation et tradition au service de votre bien-être',
            'Expertise et conseil personnalisé',
        ];

        // Generate Gabonese phone number
        $phoneNumber = '+241' . $this->faker->randomElement(['01', '02', '03', '04', '05', '06', '07']) . 
                      $this->faker->numberBetween(100000, 999999);

        return [
            'name' => $storeName,
            'slug' => Str::slug($storeName),
            'whatsapp_number' => $phoneNumber,
            'description' => $this->faker->randomElement($descriptions) . ' à ' . $this->faker->randomElement($cities) . '.',
            'user_id' => User::factory(),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Indicate that the store is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the store is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a store with a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}