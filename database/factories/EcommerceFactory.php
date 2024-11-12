<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ecommerce>
 */
class EcommerceFactory extends Factory
{
    protected $model = \App\Models\Ecommerce::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "name" => fake()->company(),
            "url" => fake()->url(),
            "showcase" => fake()->boolean(),
        ];
    }
}
