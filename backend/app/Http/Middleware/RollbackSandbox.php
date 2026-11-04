<?php

namespace App\Http\Middleware;

use App\Models\Prodotto;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps every sandbox call in a transaction that is always rolled back.
 *
 * The sandbox has no state: requests execute normally against the demo data
 * and are then undone, so the same test IDs keep working forever. afterCommit
 * callbacks never fire, and Sanctum's last_used_at still persists because it
 * is written by the auth middleware before this transaction begins.
 */
class RollbackSandbox
{
	public function handle(Request $request, Closure $next): Response
	{
		$db = DB::connection("sandbox");
		$level = $db->transactionLevel();
		$db->beginTransaction();

		try {
			$this->applyConguaglioToggle($request);

			return $next($request);
		} finally {
			$db->rollBack($level);
		}
	}

	/**
	 * The docs' conguaglio toggle: `?sandbox_conguaglio=0|1` flips the durata
	 * parametrica bank's product flags inside this request's doomed
	 * transaction — real for this call, gone the moment it ends. Unknown
	 * values are ignored; the param means nothing outside /sandbox/*.
	 */
	private function applyConguaglioToggle(Request $request): void
	{
		$toggle = $request->query("sandbox_conguaglio");

		if ($toggle !== "0" && $toggle !== "1") {
			return;
		}

		$abi = array_key_first(
			array_filter(
				config("sandbox.banks", []),
				static fn(array $bank): bool => (bool) ($bank["durata_parametrica"] ?? false)
			)
		);

		if ($abi === null) {
			return;
		}

		Prodotto::query()
			->where("banca_abi", $abi)
			->update(
				$toggle === "1"
					? ["conguaglio_periodico" => true, "frequenza_mesi_conguaglio" => 3, "metodo_conguaglio" => "plafond"]
					: ["conguaglio_periodico" => false, "frequenza_mesi_conguaglio" => null, "metodo_conguaglio" => null]
			);
	}
}
