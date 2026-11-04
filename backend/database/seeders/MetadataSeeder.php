<?php

namespace Database\Seeders;

use App\Models\Metadata;
use Illuminate\Database\Seeder;

class MetadataSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		Metadata::create([
			"it" => "Templates",
			"en" => "Templates",
			"code" => "title",
		]);
		Metadata::create([
			"it" => "Templates description",
			"en" => "Templates description",
			"code" => "description",
		]);
	}
}
