<?php

namespace Database\Seeders;

use App\Models\SeoMetaInformation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeoMetaInformationSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		SeoMetaInformation::create([
			"it" => "Humanbit templates",
			"en" => "Humanbit templates",
			"code" => "title",
		]);
		SeoMetaInformation::create([
			"it" => "Humanbit templates description",
			"en" => "Humanbit templates description",
			"code" => "description",
		]);
	}
}
