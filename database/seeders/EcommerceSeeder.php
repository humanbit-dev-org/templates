<?php

namespace Database\Seeders;

use App\Models\Ecommerce;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EcommerceSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		Ecommerce::factory()->count(15)->create();
	}
}
