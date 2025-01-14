<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			"title_it" => fake()->sentence(),
			"title_en" => fake()->sentence(),
			"meta_title_it" => fake()->sentence(),
			"meta_title_en" => fake()->sentence(),
			"subtitle_it" => fake()->sentence(),
			"subtitle_en" => fake()->sentence(),
			"abstract_it" => Str::limit(fake()->sentence(), 25),
			"abstract_en" => Str::limit(fake()->sentence(), 25),
			"body_it" => fake()->paragraphs(5, true),
			"body_en" => fake()->paragraphs(5, true),
			"meta_body_it" => fake()->paragraphs(5, true),
			"meta_body_en" => fake()->paragraphs(5, true),
			"author" => fake()->name(),
			"published" => fake()->boolean(),
			"strillo" => false,
		];
	}
}
