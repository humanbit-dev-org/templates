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
			"name" => fake()->unique()->company(),
			"url" => fake()->unique()->url(),
			// 'image_path' => 'uploads/' . $this->faker->unique()->image('public/uploads', 440, 280, null, false) . '_' . uniqid(),
			"image_path" => "uploads/placeholder.png",
			"showcase" => true,
		];
	}
}
