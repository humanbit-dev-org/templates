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
			"it" => "Humanbit templates",
			"en" => "Humanbit templates",
			"code" => "title",
		]);
		Metadata::create([
			"it" => "Humanbit templates description",
			"en" => "Humanbit templates description",
			"code" => "description",
		]);
	}
}
