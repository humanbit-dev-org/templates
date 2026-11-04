<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContattoRequest;
use App\Models\Contatto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ContattoController extends Controller
{
	/**
	 * Creazione di un nuovo contatto
	 *
	 * Salva la richiesta di contatto (email, oggetto e messaggio): le richieste
	 * sono consultabili in Backpack nella sezione Contatti.
	 *
	 * @unauthenticated
	 */
	public function store(ContattoRequest $request): Response
	{
		$values = $request->validated();

		$contatto = Contatto::create([
			"email" => $values["email"],
			// Stored as the human-readable label of the chosen subject.
			"oggetto" => Contatto::OGGETTI[$values["oggetto"]],
			"messaggio" => $values["messaggio"],
		]);

		Log::info("Contact request stored", ["contatto_id" => $contatto->id]);

		return response()->noContent();
	}
}
