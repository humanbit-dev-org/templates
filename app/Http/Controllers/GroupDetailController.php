<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Mail\GroupEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Http\Resources\GroupResource;

class GroupDetailController extends Controller
{
    public function index($id){
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }
        return response()->json(GroupResource::make($group));
    }

    // Invia un'email all'utente con l'elenco dei gruppi di cui fa parte.
    public function sendInvite(Request $request, $id){
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $group = Group::find($id);

        Mail::to($request->email)->send(new GroupEmail($group, Auth::user()->email, $request->email));

        return response()->json(["message" => "Email sent"], 200);
    }

    public function acceptInvite(Request $request, $id)
    {
        $group = Group::findOrFail($id);

        // Assicurati che l'utente sia loggato
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Verifica che l'utente sia stato invitato
        $user = Auth::user();
        if (!$group->users()->where('id', $user->id)->exists()) {
            return response()->json(['message' => 'Not invited'], 403);
        }

        // Verifica che l'utente non sia già nel gruppo
        if ($group->members()->where('id', $user->id)->exists()) {
            return response()->json(['message' => 'User already in the group'], 400);
        }

        // Aggiungi l'utente al gruppo
        $group->members()->attach($user->id);

        return response()->json(['message' => 'User added to the group']);
    }
}
