<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Contact;
use App\Models\Ecommerce;
use App\Mail\ContactEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\ContactRequest;

class ContactController extends Controller
{
	private function generateUniqueUsername($name, $surname)
	{
		$name = strtolower($name);
		$surname = strtolower($surname);

		do {
			// Ottiene parole correlate al nome o cognome
			$response = Http::get("https://api.datamuse.com/words", [
				"ml" => $name . " " . $surname, // parole correlate semanticamente
				"max" => 10,
			]);

			$words = $response->json();

			if (empty($words)) {
				// Fallback se non troviamo parole correlate
				$username = substr($name, 0, 3) . substr($surname, 0, 3) . Str::random(4);
			} else {
				$word = $words[array_rand($words)]["word"];
				$namePart = substr($name, 0, 3);
				$surnamePart = substr($surname, 0, 3);
				$username = str_replace(" ", "", $word . $namePart . $surnamePart);
			}
		} while (User::where("username", $username)->exists());

		return $username;
	}

	public function store(ContactRequest $request)
	{
		$validatedData = $request->validated();
		Contact::create($validatedData);

		return response()->json(["message" => "La richiesta è stata inviata con successo!"], 200);
	}

	public function contactOption($id, $option)
	{
		$contact = Contact::find($id);
		$contact->status = $option;

		if ($option == "reject" && request()->has("reason")) {
			$contact->reject_reason = request()->get("reason");
		}

		$contact->save();

		$lang = $contact->lang;
		$contactEmail = $contact->email;
		$user = null;
		$plainPassword = null;
		$isNewUser = false;

		if ($option == "accept") {
			// Trova o crea l'utente
			$user = User::where("email", $contactEmail)->first();

			if (!$user) {
				$isNewUser = true;
				// Genera una password casuale
				$plainPassword = Str::random(12);

				$user = User::create([
					"username" => $this->generateUniqueUsername($contact->name, $contact->surname),
					"name" => $contact->name,
					"surname" => $contact->surname,
					"email" => $contact->email,
					"password" => bcrypt($plainPassword),
					"role_id" => 1,
					"backpack_role" => "user",
					"email_verified_at" => now(),
					"email_verified" => true,
				]);
			}

			// Crea l'e-commerce
			$ecommerce = Ecommerce::create([
				"name" => $contact->company,
				"url" => $contact->url,
				"public_key" => "pk_" . strtoupper(Str::random(32)),
				"user_id" => $user->id,
				"showcase" => 0,
				"unit_type" => $contact->unit_type,
				"order_time" => $contact->order_time,
			]);

			// Invia email di accettazione con la password in chiaro solo se è un nuovo utente
			Mail::to($contactEmail)->send(
				new ContactEmail($ecommerce, $user, $lang, "new_contact_accept", $plainPassword, $isNewUser)
			);
		} else {
			// Invia email di rifiuto con la motivazione
			Mail::to($contactEmail)->send(
				new ContactEmail(null, null, $lang, "new_contact_reject", null, false, $contact->reject_reason)
			);
		}

		return response()->json([
			"success" => true,
		]);
	}
}
