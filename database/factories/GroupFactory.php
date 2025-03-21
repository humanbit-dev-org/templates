<?php

namespace Database\Factories;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
	protected $model = \App\Models\Group::class;
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			"name" => fake()->unique()->word(),
			"private" => fake()->boolean(),
			"max_members" => fake()->numberBetween(2, 20),
			"creator_id" => User::inRandomOrder()->first()->id,
			"category_id" => Category::inRandomOrder()->first()->id,
			"created_at" => Carbon::instance(fake()->dateTimeBetween("-6 month", now())),
		];
	}
}
