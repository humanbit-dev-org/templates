<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\BackpackRole;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

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
			"backpack_role_id" => BackpackRole::where("name", "Admin")->first()->id,
			"role_id" => Role::where("name", "User")->first()->id,
			"password" => bcrypt("Adm1n."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "author",
			"name" => "Author",
			"surname" => "Humanbit",
			"address" => "Via della Moscova, 40",
			"email" => "author@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role_id" => BackpackRole::where("name", "Author")->first()->id,
			"role_id" => Role::where("name", "User")->first()->id,
			"password" => bcrypt("Auth0r."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "guest",
			"name" => "Guest",
			"surname" => "Humanbit",
			"address" => "Via della Moscova, 40",
			"email" => "guest@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
			"role_id" => Role::where("name", "User")->first()->id,
			"password" => bcrypt("Gu3st."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "aborreca",
			"name" => "Andrea",
			"surname" => "Borreca",
			"address" => "Via della Moscova, 40",
			"email" => "borreca@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role_id" => BackpackRole::where("name", "Developer")->first()->id,
			"role_id" => Role::where("name", "User")->first()->id,
			"password" => bcrypt("D3v3l0p3r."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "gsilveri",
			"name" => "Gabriel",
			"surname" => "Silveri",
			"address" => "Via della Moscova, 40",
			"email" => "silveri@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role_id" => BackpackRole::where("name", "Developer")->first()->id,
			"role_id" => Role::where("name", "User")->first()->id,
			"password" => bcrypt("D3v3l0p3r."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "aponzano",
			"name" => "Andrea",
			"surname" => "Ponzano",
			"address" => "Via della Moscova, 40",
			"email" => "ponzano@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role_id" => BackpackRole::where("name", "Developer")->first()->id,
			"role_id" => Role::where("name", "User")->first()->id,
			"password" => bcrypt("D3v3l0p3r."),
			"email_verified_at" => now(),
		]);
		User::create([
			"username" => "csolia",
			"name" => "Christian",
			"surname" => "Solia",
			"address" => "Via della Moscova, 40",
			"email" => "solia@humanbit.com",
			"phone" => fake()->phoneNumber(),
			"backpack_role_id" => BackpackRole::where("name", "Developer")->first()->id,
			"role_id" => Role::where("name", "User")->first()->id,
			"password" => bcrypt("D3v3l0p3r."),
			"email_verified_at" => now(),
		]);
		User::factory()->count(5)->create();
	}
}
