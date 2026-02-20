<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
	protected $model = \App\Models\User::class;
	/**
	 * The current password being used by the factory.
	 */
	protected static ?string $password;

	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		$verified = fake()->boolean();
		return [
			"username" => fake()->unique()->userName(),
			"name" => fake()->name(),
			"surname" => fake()->lastName(),
			"address" => fake()->address(),
			"email" => fake()->unique()->safeEmail(),
			"email_verified_at" => $verified ? now() : null,
			"password" => (static::$password ??= Hash::make("password")),
			"phone" => fake()->phoneNumber(),
			"role_id" => $verified ? Role::where("name", "User")->first()->id : Role::where("name", "Public")->first()->id,
			"remember_token" => Str::random(10),
		];
	}

	/**
	 * Indicate that the model's email address should be unverified.
	 */
	public function unverified(): static
	{
		return $this->state(
			fn(array $attributes) => [
				"email_verified_at" => null,
			]
		);
	}
}
