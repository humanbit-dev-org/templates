<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupUser;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class GroupUserSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$groups = Group::all();
		foreach ($groups as $group) {
			$maxMembers = $group->max_members;
			$creatorId = $group->creator_id;
			GroupUser::create([
				"group_id" => $group->id,
				"user_id" => $creatorId,
				"send_invite" => true,
			]);
			$users = User::where("id", "!=", $creatorId)->get();
			$randomUsers = $users->random($maxMembers);
			foreach ($randomUsers as $user) {
				GroupUser::create([
					"group_id" => $group->id,
					"user_id" => $user->id,
					"send_invite" => fake()->boolean(),
				]);
			}
		}
	}
}
