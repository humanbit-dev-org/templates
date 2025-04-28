<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Institutional;
use Illuminate\Database\Seeder;
use PhpParser\Node\Stmt\GroupUse;
use App\Models\Metadata;
use Database\Seeders\MetadataSeeder;
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
			BackpackRoleSeeder::class,
			UserSeeder::class,
			ArticleSeeder::class,
			InstitutionalSeeder::class,
			TranslateSeeder::class,
			MetadataSeeder::class,
		]);
	}
}
