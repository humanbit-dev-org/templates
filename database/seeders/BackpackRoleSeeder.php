<?php

namespace Database\Seeders;

use App\Models\BackpackRole;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class BackpackRoleSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		BackpackRole::create([
			"name" => "admin",
			"description" => "Administrator",
		]);
		BackpackRole::create([
			"name" => "developer",
			"description" => "Developer",
		]);
		BackpackRole::create([
			"name" => "guest",
			"description" => "Guest",
		]);
		BackpackRole::create([
			"name" => "author",
			"description" => "Author",
		]);
	}
}
