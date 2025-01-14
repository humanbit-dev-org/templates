<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Institutional>
 */
class InstitutionalFactory extends Factory
{
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			"title_italian" => fake()->sentence(),
			"title_english" => fake()->sentence(),
			"meta_title_italian" => fake()->sentence(),
			"meta_title_english" => fake()->sentence(),
			"subtitle_italian" => fake()->sentence(),
			"subtitle_english" => fake()->sentence(),
			"abstract_italian" => Str::limit(fake()->sentence(), 25),
			"abstract_english" => Str::limit(fake()->sentence(), 25),
			"body_italian" => fake()->paragraphs(5, true),
			"body_english" => fake()->paragraphs(5, true),
			"meta_body_italian" => fake()->paragraphs(5, true),
			"meta_body_english" => fake()->paragraphs(5, true),
			"published" => fake()->boolean(),
			"strillo" => false,
		];
	}
}
