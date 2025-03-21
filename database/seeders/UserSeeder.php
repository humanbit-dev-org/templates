<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		User::create([
			"username" => "admin",
			"name" => "Admin",
			"surname" => "Humanbit",
			"address" => "Via della Moscova, 40",
			"email" => "admin@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "admin",
			"role_id" => 1,
			"password" => bcrypt("Adm1n666."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "guest",
			"name" => "Guest",
			"surname" => "Humanbit",
			"address" => "Via della Moscova, 40",
			"email" => "guest@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "guest",
			"role_id" => 1,
			"password" => bcrypt("Gu3st666."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "test",
			"name" => "Test",
			"surname" => "Humanbit",
			"address" => "Via della Moscova, 40",
			"email" => "test@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "user",
			"role_id" => 1,
			"password" => bcrypt("T3st666."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "aborreca",
			"name" => "Andrea",
			"surname" => "Borreca",
			"address" => "Via della Moscova, 40",
			"email" => "borreca@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "developer",
			"role_id" => 1,
			"password" => bcrypt("Hum4nb1t666,bis"),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "gsilveri",
			"name" => "Gabriel",
			"surname" => "Silveri",
			"address" => "Via della Moscova, 40",
			"email" => "silveri@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "developer",
			"role_id" => 1,
			"password" => bcrypt("Hum4nb1t666,bis"),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "aponzano",
			"name" => "Andrea",
			"surname" => "Ponzano",
			"address" => "Via della Moscova, 40",
			"email" => "ponzano@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "developer",
			"role_id" => 1,
			"password" => bcrypt("Hum4nb1t666,bis"),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "csolia",
			"name" => "Christian",
			"surname" => "Solia",
			"address" => "Via della Moscova, 40",
			"email" => "solia@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "developer",
			"role_id" => 1,
			"password" => bcrypt("Hum4nb1t666,bis"),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "lgiarrusso",
			"name" => "Luca",
			"surname" => "Giarrusso",
			"address" => "Via della Moscova, 40",
			"email" => "luca.giarrusso@gmail.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role" => "developer",
			"role_id" => 1,
			"password" => bcrypt("Hum4nb1t666,bis"),
			"email_verified_at" => now(),
		]);
		User::factory()->count(20)->create();
	}
}
