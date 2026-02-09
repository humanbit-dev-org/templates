<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tiraggio;
use App\Models\Delibera;
use App\Models\Mensile;
use App\Models\Trattenuta;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AdminController extends Controller
{
	/**
	 * Search users by NDG
	 */
	public function searchUsers(Request $request)
	{
		try {
			$query = $request->get("q", "");
			$ndg = $request->get("ndg", "");
			$limit = $request->get("limit", 10);

			// If searching by specific NDG
			if ($ndg) {
				$user = User::where("ndg", $ndg)
					->with([
						"filiale",
						"tiraggi" => function ($query) {
							$query->orderBy("created_at", "desc");
						},
					])
					->first();

				if ($user) {
					return response()->json([
						"user" => $user,
					]);
				} else {
					return response()->json(["error" => "Utente non trovato"], 404);
				}
			}

			// If searching by query string
			if (strlen($query) < 2) {
				return response()->json(["users" => []]);
			}

			$users = User::where("ndg", "LIKE", "%{$query}%")
				->orWhere("ragione_sociale", "LIKE", "%{$query}%")
				->select("id", "ndg", "ragione_sociale", "email")
				->limit($limit)
				->get();

			return response()->json(["users" => $users->toArray()]);
		} catch (\Exception $e) {
			Log::error("Search users error: " . $e->getMessage());
			return response()->json(["error" => "Errore interno del server"], 500);
		}
	}

	/**
	 * Get user details with related data
	 */
	public function getUserDetails(int $id): JsonResponse
	{
		$user = User::with(["filiale", "tiraggi", "delibere", "mensili"])->findOrFail($id);

		return response()->json([
			"user" => $user,
			"tiraggi" => $user->tiraggi,
			"delibere" => $user->delibere,
			"mensili" => $user->mensili->take(12), // Ultimi 12 mesi
		]);
	}

	/**
	 * Toggle user suspended status
	 */
	public function toggleUserSuspended(Request $request, int $id): JsonResponse
	{
		// Gestisci l'override del metodo PATCH
		if ($request->hasHeader("X-HTTP-Method-Override")) {
			$request->setMethod("PATCH");
		}

		$validator = Validator::make($request->all(), [
			"sospeso" => "required|boolean",
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$user = User::findOrFail($id);
		$user->update([
			"sospeso" => $request->get("sospeso"),
		]);

		return response()->json([
			"message" => "User status updated successfully",
			"sospeso" => $user->sospeso,
		]);
	}

	/**
	 * Update tiraggio status
	 */
	public function updateTiraggioStatus(Request $request, int $id): JsonResponse
	{
		Log::info("updateTiraggioStatus chiamato", [
			"id" => $id,
			"method" => $request->method(),
			"data" => $request->all(),
			"headers" => $request->headers->all(),
		]);

		// Gestisci l'override del metodo PATCH
		if ($request->hasHeader("X-HTTP-Method-Override")) {
			$request->setMethod("PATCH");
		}

		$validator = Validator::make($request->all(), [
			"status" => "required|string|in:open,requested,accepted,rejected,paid",
		]);

		if ($validator->fails()) {
			Log::error("Validazione fallita", $validator->errors()->toArray());
			throw new ValidationException($validator);
		}

		$tiraggio = Tiraggio::findOrFail($id);
		$tiraggio->update([
			"status" => $request->get("status"),
		]);

		Log::info("Tiraggio aggiornato", [
			"id" => $id,
			"new_status" => $tiraggio->status,
		]);

		return response()->json([
			"message" => "Tiraggio status updated successfully",
			"status" => $tiraggio->status,
		]);
	}

	/**
	 * Update delibera status and max_atp
	 */
	public function updateDeliberaStatus(Request $request, int $id): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			"status" => "sometimes|string|in:new,open,close",
			"max_atp" => "sometimes|nullable|numeric|min:0",
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$delibera = Delibera::findOrFail($id);

		$updateData = [];
		if ($request->has("status")) {
			// Normalize legacy 'closed' to 'close' if ever received
			$incoming = strtolower($request->get("status"));
			$updateData["status"] = $incoming === "closed" ? "close" : $incoming;
		}
		if ($request->has("max_atp")) {
			$updateData["max_atp"] = $request->get("max_atp");
		}

		$delibera->update($updateData);

		return response()->json([
			"message" => "Delibera updated successfully",
			"status" => $delibera->status,
			"max_atp" => $delibera->max_atp,
		]);
	}

	/**
	 * Search tiraggi by user ID
	 */
	public function searchTiraggi(Request $request): JsonResponse
	{
		try {
			$userNdg = $request->get("user_id"); // user_id now contains NDG (primary key)

			if (!$userNdg) {
				return response()->json(["error" => "User ID mancante"], 400);
			}

			$tiraggi = Tiraggio::where("user_ndg", $userNdg)->orderBy("created_at", "desc")->get();

			return response()->json(["tiraggi" => $tiraggi]);
		} catch (\Exception $e) {
			Log::error("Search tiraggi error: " . $e->getMessage());
			return response()->json(["error" => "Errore interno del server"], 500);
		}
	}

	/**
	 * Search delibere by user ID
	 */
	public function searchDelibere(Request $request): JsonResponse
	{
		try {
			$userNdg = $request->get("user_id"); // user_id now contains NDG (primary key)

			if (!$userNdg) {
				return response()->json(["error" => "User ID mancante"], 400);
			}

			$delibere = Delibera::where("user_ndg", $userNdg)->orderBy("created_at", "desc")->get();

			return response()->json(["delibere" => $delibere]);
		} catch (\Exception $e) {
			Log::error("Search delibere error: " . $e->getMessage());
			return response()->json(["error" => "Errore interno del server"], 500);
		}
	}

	/**
	 * Update delibera max_atp
	 */
	public function updateDeliberaMaxAtp(Request $request, int $id): JsonResponse
	{
		try {
			$validator = Validator::make($request->all(), [
				"max_atp" => "required|numeric|min:0",
			]);

			if ($validator->fails()) {
				return response()->json(
					[
						"success" => false,
						"message" => "Dati non validi",
						"errors" => $validator->errors(),
					],
					400
				);
			}

			$delibera = Delibera::findOrFail($id);
			$delibera->update([
				"max_atp" => $request->get("max_atp"),
			]);

			return response()->json([
				"success" => true,
				"message" => "Max ATP aggiornato con successo",
				"id_delibera" => $delibera,
			]);
		} catch (\Exception $e) {
			Log::error("Update delibera max_atp error: " . $e->getMessage());
			return response()->json(
				[
					"success" => false,
					"message" => "Errore interno del server",
				],
				500
			);
		}
	}

	/**
	 * Get monthly statistics for dashboard widgets
	 */
	public function getMonthlyStats(): array
	{
		$startOfMonth = Carbon::now()->startOfMonth();
		$endOfMonth = Carbon::now()->endOfMonth();

		// Calculate total trattenute for the current month
		$totalTrattenute = Trattenuta::whereBetween("data_contabile", [$startOfMonth, $endOfMonth])->sum(
			"importo_trattenuto"
		);

		// Count new users created in the current month
		$newUsersCount = User::whereBetween("created_at", [$startOfMonth, $endOfMonth])->count();

		// Count tiraggi with status 'requested' or 'accepted' with data_richiesta in the current month
		$requestedTiraggiCount = Tiraggio::whereIn("status", ["requested", "accepted"])
			->whereBetween("data_richiesta", [$startOfMonth, $endOfMonth])
			->count();

		// Sum requested/accepted tiraggi amounts with data_richiesta in the current month
		$requestedTiraggiAmount = Tiraggio::whereIn("status", ["requested", "accepted"])
			->whereBetween("data_richiesta", [$startOfMonth, $endOfMonth])
			->sum("importo_richiesto");

		return [
			"total_trattenute" => $totalTrattenute,
			"new_users" => $newUsersCount,
			"requested_tiraggi" => $requestedTiraggiCount,
			"requested_tiraggi_amount" => $requestedTiraggiAmount,
		];
	}

	public function getRecentAcceptedTiraggi(): JsonResponse
	{
		try {
			// Get tiraggi with status 'accepted' of any date with trattenuta >= 4/6
			$tiraggi = Tiraggio::where("status", "accepted")
				->whereRaw("trattenuta_totale >= (importo_deliberato * 4 / 6)") // Trattenuta totale >= 4/6 dell'importo deliberato
				->with([
					"user" => function ($query) {
						$query->select("id", "ndg", "ragione_sociale");
					},
				])
				->orderBy("data_delibera", "desc")
				->limit(10) // Limit to 10 most recent
				->get();

			return response()->json(["tiraggi" => $tiraggi]);
		} catch (\Exception $e) {
			Log::error("Get recent accepted tiraggi error: " . $e->getMessage());
			return response()->json(["error" => "Errore interno del server"], 500);
		}
	}
}
