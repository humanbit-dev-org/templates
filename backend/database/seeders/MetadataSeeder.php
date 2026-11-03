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
			"it" => "Cashberry templates",
			"en" => "Cashberry templates",
			"code" => "title",
		]);
		Metadata::create([
			"it" => "Cashberry templates description",
			"en" => "Cashberry templates description",
			"code" => "description",
		]);
	}
}
