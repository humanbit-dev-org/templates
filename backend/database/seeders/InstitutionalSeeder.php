<?php

namespace Database\Seeders;

use App\Models\Institutional;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InstitutionalSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		Institutional::factory()->count(5)->create();
	}
}
