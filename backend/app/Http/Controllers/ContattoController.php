<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Filiale;
use App\Models\Contatto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use App\Notifications\UserContactRequestNotification;
use App\Notifications\FilialeContactRequestNotification;

class ContattoController extends Controller
{
	/**
	 * Creazione di un nuovo contatto
	 *
	 * Crea un nuovo contatto con email, oggetto e messaggio. Viene creata anche la notifica per l'utente e la filiale.
	 *
	 * @unauthenticated
	 */
	public function store(Request $request)
	{
		$user = Auth::guard("sanctum")->user();
		$ndg = $user->ndg;

		$validator = Validator::make($request->all(), [
			"email" => "required|email",
			"oggetto" => "required|string|in:richiesta,conferma_richiesta,gestione_credito",
			"messaggio" => "required|string",
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$values = $validator->validated();

		$oggetto =
			$values["oggetto"] == "richiesta"
				? "Richiesta"
				: ($values["oggetto"] == "conferma_richiesta"
					? "Conferma della richiesta"
					: "Gestione del credito");

		$contatto = Contatto::create([
			"email" => $values["email"],
			"oggetto" => $oggetto,
			"messaggio" => $values["messaggio"],
		]);
		Notification::route("mail", "bancodesio.assistenza@cashberry.it")->notify(
			new FilialeContactRequestNotification($contatto, $values["email"], $ndg)
		);

		return response()->noContent();
	}
}
