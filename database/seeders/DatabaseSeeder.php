<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Institutional;
use Illuminate\Database\Seeder;
use PhpParser\Node\Stmt\GroupUse;
use App\Models\SeoMetaInformation;
use Database\Seeders\SeoMetaInformationSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$this->call([
			RoleSeeder::class,
			UserSeeder::class,
			ArticleSeeder::class,
			InstitutionalSeeder::class,
			TranslateSeeder::class,
			SeoMetaInformationSeeder::class,
		]);
	}
}
