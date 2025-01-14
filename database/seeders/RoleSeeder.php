<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		Role::create([
			"name" => "User",
			"create_op" => true,
			"read_op" => true,
			"update_op" => true,
			"delete_op" => true,
		]);
	}
}
