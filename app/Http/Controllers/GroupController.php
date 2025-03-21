<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Group;
use App\Models\Category;
use App\Models\GroupUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\GroupResource;

class GroupController extends Controller
{
	// Restituisce le resources Group relative ai gruppi di cui l'utente è membro e ai gruppi da lui creati.
	public function index(Request $request)
	{
		$openGroups = Group::where("private", false)->select("id", "name")->get();
		$groups = $request->user()->groups->select("id", "name");
		$createdGroups = $request->user()->createdGroups->select("id", "name");

		return response()->json([
			"open_groups" => $openGroups,
			"groups" => $groups,
			"created_groups" => $createdGroups,
			"categories" => Category::all(),
		]);
	}

	public function openGroups(Request $request)
	{
		$groups = Group::where("private", false)->select("id", "name", "category_id")->with("category")->limit(8)->get();

		return response()->json([
			"groups" => $groups,
		]);
	}

	// Crea un nuovo gruppo.
	public function create(Request $request)
	{
		$isPrivate = $request->private === "true" ? true : false;
		$request->validate([
			"name" => ["required", "string", "max:255"],
			"category" => ["required", "exists:App\Models\Category,id"],
			"max_members" => ["required", "integer", "min:2"],
		]);

		$group = Group::create([
			"name" => $request->name,
			"creator_id" => $request->user()->id,
			"category_id" => $request->category,
			"max_members" => $request->max_members,
			"private" => $isPrivate,
		]);

		$groupId = $group->id;

		GroupUser::create([
			"user_id" => $request->user()->id,
			"group_id" => $groupId,
		]);

		return response()->json(["message" => "Gruppo $group->name creato"], 200);
	}

	public function detail($id)
	{
		$group = Group::find($id);

		if (!$group) {
			return response()->json(["error" => "Group not found"], 404);
		}

		$isInGroup = $group
			->users()
			->where("users.id", Auth::id()) // Specify the table name for the `id` column
			->exists();

		if ($isInGroup) {
			$sendInvite = GroupUser::where("group_id", $id)->where("user_id", Auth::id())->first()->send_invite;
			return response()->json([
				"group" => GroupResource::make($group),
				"isInGroup" => true,
				"sendInvite" => $sendInvite,
			]);
		}

		if ($group->private) {
			return response()->json(["error" => "Group not found"], 404);
		} else {
			// For non-private groups, return limited fields with relationships
			$limitedGroup = Group::where("id", $id)
				->select("id", "name", "creator_id", "category_id", "created_at")
				->with(["creator", "category"])
				->first();

			$limitedGroup->created_at_date = Carbon::parse($limitedGroup->created_at)->format("d-m-Y");

			return response()->json([
				"group" => $limitedGroup,
				"isInGroup" => $isInGroup, // Add the check as part of the response
			]);
		}
	}
}
