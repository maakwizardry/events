<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => fake()->paragraph(),
            'logo_url' => fake()->imageUrl(200, 200, 'business'),
            'website_url' => fake()->url(),
            'primary_color' => fake()->hexColor(),
            'secondary_color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
