<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\GroupResource;

class GroupController extends Controller
{
    // Restituisce le resources Group relative ai gruppi di cui l'utente è membro e ai gruppi da lui creati.
    public function index(Request $request){
        // $groups = $request->user()->groups;
        // $created_groups = $request->user()->createdGroups;

        // return response()->json([
        //     'groups' => GroupResource::collection($groups),
        //     'created_groups' => GroupResource::collection($created_groups),
        //     'categories' => Category::all(),
        // ]);


		// Simula alcuni dati da inviare al frontend
		$data = [
			'message' => 'Benvenuto nell\'API di Laravel',
			'status' => 'success',
		];

		return response()->json($data);
    }

    // Crea un nuovo gruppo.
    public function create(Request $request){
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'selectedCategory' => ['required', 'exists:App\Models\Category,id'],
        ]);

        $group = Group::create([
            'name' => $request->name,
            'creator_id' => $request->user()->id,
            'category_id' => $request->selectedCategory,
        ]);

        return response()->json(["message" => "Gruppo $group->name creato"], 200);
    }
}
