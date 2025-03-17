<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Group;
use App\Models\Invite;
use App\Mail\GroupEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class InviteController extends Controller
{
	public function index()
	{
		$receivedInvites = Invite::where("receiver_id", Auth::user()->id)
			->whereIn("status", ["pending", "expired"])
			->get();
		$sentInvites = Invite::where("sender_id", Auth::user()->id)
			->whereIn("status", ["pending", "expired"])
			->get();
		return response()->json(["receivedInvites" => $receivedInvites, "sentInvites" => $sentInvites]);
	}

	public function sendInvite(Request $request, $lang, $id)
	{
		$request->validate([
			"email" => ["required", "email"],
		]);

		$group = Group::findOrFail($id);
		$email = $request->email;

		$userExists = User::where("email", $email)->exists();

		if ($userExists) {
			$user = User::where("email", $email)->first();
			$isInGroup = $group->users()->where("users.id", $user->id)->exists();
			if (!$isInGroup) {
				$isInvited = Invite::where("group_id", $group->id)->where("receiver_id", $user->id)->exists();
				if ($isInvited) {
					$invite = Invite::where("group_id", $group->id)->where("receiver_id", $user->id)->first();
					if ($invite->status === "expired") {
						$invite->status = "pending";
						$invite->expire_date = now()->addDays(7);
						$invite->save();
						$view = "new_invite_user";
						Mail::to($request->email)->send(
							new GroupEmail($group, Auth::user()->username, $request->email, $lang, $view)
						);
						return response()->json(["message" => "Email sent"], 200);
					} else {
						return response()->json(["errors" => "Utente già invitato"], 400);
					}
				} else {
					$invite = Invite::create([
						"group_id" => $group->id,
						"sender_id" => Auth::user()->id,
						"receiver_id" => $user->id,
						"email" => $request->email,
					]);
					$view = "new_invite_user";
					Mail::to($request->email)->send(
						new GroupEmail($group, Auth::user()->username, $request->email, $lang, $view)
					);
					return response()->json(["message" => "Email sent"], 200);
				}
			} else {
				return response()->json(["errors" => "L'utente fa già parte del gruppo"], 400);
			}
		} else {
			$isInvited = Invite::where("group_id", $group->id)->where("email", $email)->exists();
			if ($isInvited) {
				$invite = Invite::where("group_id", $group->id)->where("email", $email)->first();
				if ($invite->status === "expired") {
					$invite->status = "pending";
					$invite->expire_date = now()->addDays(7);
					$invite->save();
					$view = "new_invite_new_user";
					Mail::to($request->email)->send(
						new GroupEmail($group, Auth::user()->username, $request->email, $lang, $view)
					);
					return response()->json(["message" => "Email sent"], 200);
				} else {
					return response()->json(["errors" => "Utente già invitato"], 400);
				}
			} else {
				$invite = Invite::create([
					"group_id" => $group->id,
					"sender_id" => Auth::user()->id,
					"email" => $request->email,
				]);
				$view = "new_invite_new_user";
				Mail::to($request->email)->send(
					new GroupEmail($group, Auth::user()->username, $request->email, $lang, $view)
				);
				return response()->json(["message" => "Email sent"], 200);
			}
		}
	}

	public function acceptInvite(Request $request, $id)
	{
		$group = Group::findOrFail($id);

		// Assicurati che l'utente sia loggato
		if (!Auth::check()) {
			return response()->json(["message" => "Unauthorized"], 401);
		}

		// Verifica che l'utente sia stato invitato
		$user = Auth::user();
		if (!$group->users()->where("id", $user->id)->exists()) {
			return response()->json(["message" => "Not invited"], 403);
		}

		// Verifica che l'utente non sia già nel gruppo
		if ($group->users()->where("id", $user->id)->exists()) {
			return response()->json(["message" => "User already in the group"], 400);
		}

		// Aggiungi l'utente al gruppo
		$group->users()->attach($user->id);

		return response()->json(["message" => "User added to the group"]);
	}
}
