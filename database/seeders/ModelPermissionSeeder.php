<?php

namespace Database\Seeders;

use App\Models\BackpackRole;
use App\Models\ModelPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ModelPermissionSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$models = [];

		foreach (File::allFiles(app_path("Models")) as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			$models[$name] = $name;
		}

		ModelPermission::create([
			"model_name" => $models,
			"backpack_role_id" => BackpackRole::where("name", "Developer")->first()->id,
			"can_read" => true,
			"can_create" => true,
			"can_update" => true,
			"can_delete" => true,
		]);

		ModelPermission::create([
			"model_name" => array_diff_key($models, array_flip(["BackpackRole", "Role", "ModelPermission"])),
			"backpack_role_id" => BackpackRole::where("name", "Admin")->first()->id,
			"can_read" => true,
			"can_create" => true,
			"can_update" => true,
			"can_delete" => true,
		]);
		ModelPermission::create([
			"model_name" => ["BackpackRole", "Role", "ModelPermission"],
			"backpack_role_id" => BackpackRole::where("name", "Admin")->first()->id,
			"can_read" => true,
			"can_create" => false,
			"can_update" => false,
			"can_delete" => false,
		]);

		ModelPermission::create([
			"model_name" => array_diff_key(
				$models,
				array_flip([
					"Article",
					"Institutional",
					"Media",
					"Attachment",
					"User",
					"BackpackRole",
					"Role",
					"ModelPermission",
				])
			),
			"backpack_role_id" => BackpackRole::where("name", "Author")->first()->id,
			"can_read" => true,
			"can_create" => false,
			"can_update" => false,
			"can_delete" => false,
		]);
		ModelPermission::create([
			"model_name" => ["Article", "Institutional", "Media", "Attachment"],
			"backpack_role_id" => BackpackRole::where("name", "Author")->first()->id,
			"can_read" => true,
			"can_create" => true,
			"can_update" => true,
			"can_delete" => true,
		]);

		ModelPermission::create([
			"model_name" => array_diff_key($models, array_flip(["BackpackRole", "Role", "ModelPermission", "User", "Page"])),
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
			"can_read" => true,
			"can_create" => false,
			"can_update" => false,
			"can_delete" => false,
		]);
	}
}
