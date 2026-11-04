<?php

namespace App\Services\Restricted;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Scenarios of the Auth Simulation page: each one picks a random user in a
 * given state, so an operator can log in as "a user like that" without
 * knowing any username.
 *
 * Only frontend users are candidates (backpack_role_id empty): Backpack
 * operators log into the admin panel, not through SSO.
 *
 * Adding a scenario: add an entry to SCENARIOS (or ERROR_SCENARIOS) and the
 * matching constraint in applyScenario().
 */
class SamlSimulationScenarioService
{
<<<<<<< HEAD
	public const SCENARIO_MANUAL = "manual";

	/**
	 * Candidates drawn at a time when looking for a scenario match.
	 *
	 * Sized so a single draw almost always contains a match: the SQL pre-filter
	 * has already removed everyone who cannot qualify, so what is left fails
	 * only on eligibility, which most candidates pass.
	 */
	private const SAMPLE = 100;

	/**
	 * How many draws before giving up and reporting no candidate.
	 *
	 * A ceiling, not an expectation. It bounds the work for scenarios that are
	 * genuinely rare, where the pre-filter matches many users but almost none
	 * of them turn out eligible.
	 */
	private const SAMPLE_ROUNDS = 5;

	/**
	 * Error scenarios that trigger warning messages on the platform home page.
	 *
	 * @var array<string, array{key: string, label: string, translate_code: string, description: string}>
	 */
	public const ERROR_SCENARIOS = [
		"error_not_eleggibile" => [
			"key" => "error_not_eleggibile",
			"label" => "Non eleggibile",
			"translate_code" => "warning_eleggibilita_*",
			"description" => "Utente non eleggibile per i requisiti tecnici.",
		],
		"error_delibera_closed" => [
			"key" => "error_delibera_closed",
			"label" => "Delibera chiusa",
			"translate_code" => "warning_tiraggio_unavailable",
			"description" => "Eleggibile con delibera chiusa e tiraggio non accepted/requested.",
		],
		"error_incomplete_data" => [
			"key" => "error_incomplete_data",
			"label" => "Dati incompleti",
			"translate_code" => "warning_incomplete_data",
			"description" =>
				"Eleggibile senza tiraggi, oppure con tiraggio open senza importo richiesto e senza tetto ATP sulla delibera.",
		],
	];

	/**
	 * Product-feature scenarios: sub-selections of 'accepted' users filtered
	 * by the bank's product configuration (multi-tiraggio, conguaglio).
	 *
	 * Each entry has a 'filter' callback that receives (User, Prodotto) and
	 * returns true if the user qualifies for that product-feature scenario.
	 *
	 * @var array<string, array{key: string, label: string, status: string, description: string, filter: callable}>
	 */
	public const PRODUCT_SCENARIOS = [
		"accepted_multi" => [
			"key" => "accepted_multi",
			"label" => "Accepted · Multi-Tiraggio",
			"status" => "accepted",
			"description" => "Tiraggio accettato con prodotto multi-tiraggio abilitato.",
			"filter" => "multi_tiraggio",
			"group" => "multi",
		],
		"accepted_single" => [
			"key" => "accepted_single",
			"label" => "Accepted · Singolo",
			"status" => "accepted",
			"description" => "Tiraggio accettato con prodotto multi-tiraggio non abilitato.",
			"filter" => "single_tiraggio",
			"group" => "multi",
		],
		"conguaglio_plafond" => [
			"key" => "conguaglio_plafond",
			"label" => "Conguaglio su Plafond",
			"status" => "accepted",
			"description" => "Tiraggio accettato con conguaglio periodico a metodo plafond.",
			"filter" => "conguaglio_plafond",
			"group" => "durata_parametrica",
		],
		"conguaglio_previsionale" => [
			"key" => "conguaglio_previsionale",
			"label" => "Conguaglio su Previsionale",
			"status" => "accepted",
			"description" => "Tiraggio accettato con conguaglio periodico a metodo previsionale.",
			"filter" => "conguaglio_previsionale",
			"group" => "durata_parametrica",
		],
		"senza_conguaglio" => [
			"key" => "senza_conguaglio",
			"label" => "Senza Conguaglio",
			"status" => "accepted",
			"description" => "Tiraggio accettato con conguaglio periodico non abilitato.",
			"filter" => "senza_conguaglio",
			"group" => "durata_parametrica",
		],
		"accepted_multi_durata_parametrica" => [
			"key" => "accepted_multi_durata_parametrica",
			"label" => "Accepted · Multi-Tiraggio + Durata Parametrica",
			"status" => "accepted",
			"description" =>
				"Tiraggio accettato con prodotto multi-tiraggio e durata parametrica abilitata, senza conguagli.",
			"filter" => "multi_durata_parametrica",
			"group" => "multi_durata_parametrica",
		],
	];

	public const PRODUCT_GROUPS = [
		"multi" => "Tiraggio multiplo",
		"durata_parametrica" => "Tiraggio durata parametrica",
		"multi_durata_parametrica" => "Tiraggio multiplo + Durata parametrica",
	];

	/**
	 * @var array<string, array{key: string, label: string, status: string, description: string}>
	 */
	public const SCENARIOS = [
		"tiraggio_open" => [
			"key" => "tiraggio_open",
			"label" => "Tiraggio open",
			"status" => "open",
			"description" => "Utente in target con almeno un tiraggio in stato open.",
		],
		"tiraggio_requested" => [
			"key" => "tiraggio_requested",
			"label" => "Tiraggio requested",
			"status" => "requested",
			"description" => "Utente in target con tiraggio già richiesto alla filiale.",
		],
		"tiraggio_accepted" => [
			"key" => "tiraggio_accepted",
			"label" => "Tiraggio accepted",
			"status" => "accepted",
			"description" => "Utente in target con tiraggio deliberato e accettato.",
		],
	];

	/**
	 * @return list<array{banca_abi: string, nome: string, multi_tiraggio: bool, conguaglio_periodico: bool, metodo_conguaglio: ?string, durata_parametrica: bool}>
	 */
	public function banksForSelect(): array
	{
		return $this->banksWithSimulationDataQuery()
			->orderBy("nome")
			->with("prodotto")
			->get(["banca_abi", "nome", "tiraggio_durata_parametrica"])
			->map(
				static fn(Banca $banca): array => [
					"banca_abi" => (string) $banca->banca_abi,
					"nome" => (string) $banca->nome,
					"multi_tiraggio" => (bool) ($banca->prodotto?->multi_tiraggio ?? false),
					"conguaglio_periodico" => (bool) ($banca->prodotto?->conguaglio_periodico ?? false),
					"metodo_conguaglio" => $banca->prodotto?->metodo_conguaglio,
					"durata_parametrica" => (bool) $banca->tiraggio_durata_parametrica,
				]
			)
			->values()
			->all();
	}

	public function isKnownBancaAbi(?string $bancaAbi): bool
	{
		$normalized = $this->normalizeBancaAbi($bancaAbi);

		if ($normalized === null) {
			return false;
		}

		return $this->banksWithSimulationDataQuery()->whereKey($normalized)->exists();
	}

	/**
	 * @return array<string, array{key: string, label: string, status: string, description: string, available: bool, count: int}>
	 */
	public function catalog(?string $bancaAbi = null): array
	{
		$catalog = [];

		foreach (self::SCENARIOS as $key => $scenario) {
			$count = $this->candidateCount($key, $bancaAbi);
			$catalog[$key] = [...$scenario, "available" => $count > 0, "count" => $count];
		}

		foreach (self::PRODUCT_SCENARIOS as $key => $scenario) {
			$count = $this->candidateCount($key, $bancaAbi);
			$catalog[$key] = [
				"key" => $scenario["key"],
				"label" => $scenario["label"],
				"status" => $scenario["status"],
				"description" => $scenario["description"],
				"group" => $scenario["group"],
				"available" => $count > 0,
				"count" => $count,
			];
		}

		return $catalog;
	}

	/**
	 * How many candidates a scenario has, counted in SQL.
	 *
	 * This is the pre-filter count, so it is an upper bound: it does not run the
	 * eligibility rules, which live in PHP and would mean loading every user.
	 * Running them for a badge cost 180 seconds and 38.000 queries on a bank
	 * with 17.000 customers — the page simply timed out.
	 *
	 * The number that has to be exact is not this one. What matters is that the
	 * user the operator lands on really matches the scenario, and that is still
	 * verified one candidate at a time in randomUserIdForScenario().
	 */
	public function candidateCount(string $scenario, ?string $bancaAbi = null): int
	{
		return $this->candidateQuery($scenario, $bancaAbi)->count();
	}

	public function labelFor(string $scenario): string
	{
		if ($scenario === self::SCENARIO_MANUAL) {
			return "Inserimento manuale";
		}

		return self::SCENARIOS[$scenario]["label"] ??
			(self::PRODUCT_SCENARIOS[$scenario]["label"] ?? (self::ERROR_SCENARIOS[$scenario]["label"] ?? $scenario));
	}

	public function isKnownScenario(string $scenario): bool
	{
		return $scenario === self::SCENARIO_MANUAL ||
			isset(self::SCENARIOS[$scenario]) ||
			isset(self::PRODUCT_SCENARIOS[$scenario]) ||
			isset(self::ERROR_SCENARIOS[$scenario]);
	}

	public function isProductScenario(string $scenario): bool
	{
		return isset(self::PRODUCT_SCENARIOS[$scenario]);
	}

	/**
	 * A user that really matches the scenario, picked at random.
	 *
	 * Draws a small batch of candidates in random order and checks them with the
	 * very same PHP rules as before — eligibility, product target, priority
	 * tiraggio status, delibera state. Nothing about the answer is approximated:
	 * the user handed back is as valid as when this scanned the whole table.
	 *
	 * What changed is how many are looked at. Finding one match never needed
	 * 17.000 evaluations; a handful is enough, and the batch is only redrawn
	 * when a whole one fails, which happens when the scenario is genuinely rare.
	 */
	public function randomUserIdForScenario(string $scenario, ?string $bancaAbi = null): ?string
	{
		if ($scenario === self::SCENARIO_MANUAL || !$this->isKnownScenario($scenario)) {
			return null;
		}

		$query = $this->candidateQuery($scenario, $bancaAbi);
		$context = app(EleggibilitaListContext::class);

		for ($round = 0; $round < self::SAMPLE_ROUNDS; $round++) {
			$sample = $this->orderForSampling(clone $query, $scenario)
				->with(["tiraggi", "delibere"])
				->limit(self::SAMPLE)
				->get()
				->shuffle();

			if ($sample->isEmpty()) {
				return null;
			}

			$context->reset();
			$match = $this->firstMatching($sample, $scenario);

			if ($match !== null) {
				return $match;
			}
		}

		$context->reset();

		return null;
	}

	/**
	 * How the batch is drawn, which decides whether it is likely to hold a match.
	 *
	 * Drawing at random and filtering afterwards only works when matches are
	 * common. For most scenarios they are, because candidateQuery() has already
	 * removed everyone who cannot qualify and what remains fails only on
	 * eligibility.
	 *
	 * 'error_not_eleggibile' is the exception: it has no SQL half — being
	 * ineligible *is* the PHP rules — so a random draw over every customer of
	 * the bank could miss the few who fail, and the page would wrongly report
	 * that no candidate exists. Ordering by how little history a user has puts
	 * the likely failures first without restating any threshold in SQL, and the
	 * batch is shuffled afterwards so the pick stays varied.
	 *
	 * @param  Builder<User>  $query
	 * @return Builder<User>
	 */
	private function orderForSampling(Builder $query, string $scenario): Builder
	{
		return $scenario === "error_not_eleggibile"
			? $query->withCount("storici")->orderBy("storici_count")
			: $query->inRandomOrder();
	}

	/**
	 * The first user in the batch that satisfies the scenario for real.
	 *
	 * @param  Collection<int, User>  $sample
	 */
	private function firstMatching(Collection $sample, string $scenario): ?string
	{
		$context = app(EleggibilitaListContext::class);
		$context->warm($sample);

		if (isset(self::ERROR_SCENARIOS[$scenario])) {
			foreach ($sample as $user) {
				$tiraggio = $user->priorityTiraggio();

				$matches = $this->matchesErrorScenario(
					$scenario,
					$user->isEleggibile(EleggibilitaContext::Platform),
					$tiraggio,
					$user->deliberaForTiraggio($tiraggio)
				);

				if ($matches) {
					return (string) $user->user_id;
				}
			}

			return null;
		}

		$status = self::SCENARIOS[$scenario]["status"] ?? (self::PRODUCT_SCENARIOS[$scenario]["status"] ?? null);

		// The product configuration and the multi-tiraggio requirement are
		// already settled in SQL by candidateQuery(); what is left is the
		// eligibility and target check, which has to stay here.
		$eligible = $this->filterTechnicallyEligibleUsers($sample, $status);

		return $eligible->isEmpty() ? null : (string) $eligible->first()->user_id;
	}

	/**
	 * The SQL side of a scenario: every user that could possibly match.
	 *
	 * A superset on purpose. Everything cheap to express in SQL is settled here
	 * — tiraggio status, product configuration, delibera state, the
	 * multi-tiraggio requirement — while the eligibility and target rules stay
	 * in PHP, where they are written once and cannot drift into a second
	 * dialect. The batch drawn from this query is then checked properly.
	 *
	 * @return Builder<User>
	 */
	private function candidateQuery(string $scenario, ?string $bancaAbi, bool $conStorico = true): Builder
	{
		$query = $this->platformUsersQuery();
		$this->applyBancaAbiFilter($query, $bancaAbi);

		// Every scenario but 'error_not_eleggibile' needs an eligible user, and
		// eligibility needs the whole storico window. Without this a dataset
		// seeded last month counted thousands of candidates, every one too old,
		// and the batches never reached the few that were current.
		if ($conStorico && $scenario !== "error_not_eleggibile") {
			$this->applyStoricoWindowPrefilter($query);
		}

		if (isset(self::ERROR_SCENARIOS[$scenario])) {
			return $this->applyErrorPrefilter($query, $scenario);
		}

		$status = self::SCENARIOS[$scenario]["status"] ?? (self::PRODUCT_SCENARIOS[$scenario]["status"] ?? null);

		$query->where("sospeso", false);

		if ($status !== null) {
			$query->whereHas("tiraggi", static fn($q) => $q->where("status", $status));
		}

		if (isset(self::PRODUCT_SCENARIOS[$scenario])) {
			$this->applyProductPrefilter($query, $scenario);
		}

		return $query;
	}

	/**
	 * Users holding the last storico month the platform window requires.
	 *
	 * A necessary condition only — the window wants all twelve months, which
	 * the PHP check still verifies — so the query stays a superset.
	 *
	 * @param  Builder<User>  $query
	 */
	private function applyStoricoWindowPrefilter(Builder $query): void
	{
		$ultimo = $this->ultimoMeseStorico();

		$query->whereHas(
			"storici",
			static fn($q) => $q
				->whereRaw("anno + 0 = ?", [(int) $ultimo["anno"]])
				->whereRaw("mese + 0 = ?", [(int) $ultimo["mese"]])
		);
	}

	/**
	 * @return array{anno: string, mese: string}
	 */
	private function ultimoMeseStorico(): array
	{
		$mesi = app(UserMensiliPeriodService::class)->storicoMonths();

		return end($mesi);
	}

	/**
	 * When a scenario has candidates but none with a current storico, the month
	 * they are missing ('YYYY-MM'): the dataset has aged past the window and
	 * app:init has to run again. Null otherwise.
	 */
	public function meseStoricoMancante(string $scenario, ?string $bancaAbi = null): ?string
	{
		if (
			!$this->isKnownScenario($scenario) ||
			$scenario === self::SCENARIO_MANUAL ||
			$scenario === "error_not_eleggibile"
		) {
			return null;
		}

		if (
			$this->candidateQuery($scenario, $bancaAbi)->exists() ||
			$this->candidateQuery($scenario, $bancaAbi, conStorico: false)->doesntExist()
		) {
			return null;
		}

		$ultimo = $this->ultimoMeseStorico();

		return sprintf("%04d-%02d", $ultimo["anno"], $ultimo["mese"]);
	}

	/**
	 * Narrow to the banks whose product matches, plus the multi-tiraggio check.
	 *
	 * Both are bank- or row-level facts, so SQL settles them without loading
	 * anything: the product configuration lives on a handful of rows, and
	 * "more than one accepted tiraggio on the same delibera" is a GROUP BY.
	 *
	 * @param  Builder<User>  $query
	 */
	private function applyProductPrefilter(Builder $query, string $scenarioKey): void
	{
		$filter = self::PRODUCT_SCENARIOS[$scenarioKey]["filter"];

		$query->whereIn("banca_abi", $this->bancaAbisForScenario($scenarioKey));

		if ($filter === "multi_tiraggio" || $filter === "multi_durata_parametrica") {
			$query->whereIn("user_id", static function ($sub): void {
				$sub->from("tiraggi")
					->select("user_id")
					->where("status", "accepted")
					->groupBy("user_id", "delibera_id")
					->havingRaw("COUNT(*) > 1");
			});
		}
	}

	/**
	 * Banks a product scenario can apply to.
	 *
	 * The product configuration alone is not the whole condition. The
	 * 'durata_parametrica' scenarios describe variants of that tiraggio type,
	 * so they only mean anything on a bank that has it switched on — and that
	 * flag lives on Banca, not on Prodotto, which is why matchProductFilter()
	 * cannot see it.
	 *
	 * Left out, the negative filter read as an absolute: "Senza Conguaglio"
	 * counted every tiraggio of every bank without conguaglio periodico,
	 * including banks with no durata parametrica at all, where the scenario
	 * does not exist.
	 *
	 * @return list<string>
	 */
	private function bancaAbisForScenario(string $scenarioKey): array
	{
		$scenario = self::PRODUCT_SCENARIOS[$scenarioKey];

		$prodotti = Prodotto::query()
			->get()
			->filter(fn(Prodotto $prodotto): bool => $this->matchProductFilter($scenario["filter"], $prodotto));

		if ($scenario["group"] === "durata_parametrica" || $scenario["group"] === "multi_durata_parametrica") {
			$conDurataParametrica = Banca::query()
				->where("tiraggio_durata_parametrica", true)
				->pluck("banca_abi")
				->map(static fn($abi): string => (string) $abi)
				->all();

			$prodotti = $prodotti->filter(
				static fn(Prodotto $prodotto): bool => in_array((string) $prodotto->banca_abi, $conDurataParametrica, true)
			);
		}

		return $prodotti->pluck("banca_abi")->map(static fn($abi): string => (string) $abi)->values()->all();
	}

	/**
	 * The SQL half of an error scenario.
	 *
	 * Only what a column can answer. "Not eligible" has no such half — it is the
	 * eligibility rules themselves — so there everyone is a candidate and the
	 * batch check does the work.
	 *
	 * @param  Builder<User>  $query
	 * @return Builder<User>
	 */
	private function applyErrorPrefilter(Builder $query, string $scenario): Builder
	{
		return match ($scenario) {
			"error_delibera_closed" => $query
				->whereHas("delibere", static fn($q) => $q->where("status", "close"))
				->whereDoesntHave("tiraggi", static fn($q) => $q->whereIn("status", ["accepted", "requested"])),
			"error_incomplete_data" => $query->where(static function ($q): void {
				$q->whereDoesntHave("tiraggi")->orWhereHas(
					"tiraggi",
					static fn($t) => $t->where("status", "open")->whereNull("importo_richiesto")
				);
			}),
			default => $query,
		};
	}

	/**
	 * Check if a product matches the named filter for a product scenario.
	 */
	private function matchProductFilter(string $filter, Prodotto $prodotto): bool
	{
		return match ($filter) {
			"multi_tiraggio" => $prodotto->multi_tiraggio === true,
			"single_tiraggio" => $prodotto->multi_tiraggio !== true,
			"conguaglio_plafond" => $prodotto->conguaglio_periodico === true && $prodotto->metodo_conguaglio === "plafond",
			"conguaglio_previsionale" => $prodotto->conguaglio_periodico === true &&
				$prodotto->metodo_conguaglio === "previsionale",
			"senza_conguaglio" => $prodotto->conguaglio_periodico !== true,
			"multi_durata_parametrica" => $prodotto->multi_tiraggio === true && $prodotto->conguaglio_periodico !== true,
			default => false,
		};
	}

	/**
	 * @return array<string, array{key: string, label: string, translate_code: string, description: string, available: bool, count: int}>
	 */
	public function errorCatalog(?string $bancaAbi = null): array
	{
		$catalog = [];

		foreach (self::ERROR_SCENARIOS as $key => $scenario) {
			$count = $this->candidateCount($key, $bancaAbi);

			$catalog[$key] = [...$scenario, "available" => $count > 0, "count" => $count];
		}

		return $catalog;
	}

	/**
	 * The predicate behind each error scenario, in one place.
	 */
	private function matchesErrorScenario(string $key, bool $eleggibile, ?Tiraggio $tiraggio, ?Delibera $delibera): bool
	{
		return match ($key) {
			"error_not_eleggibile" => !$eleggibile,
			"error_delibera_closed" => $eleggibile &&
				$delibera?->status === "close" &&
				!in_array($tiraggio?->status, ["accepted", "requested"], true),
			"error_incomplete_data" => $eleggibile &&
				($tiraggio === null ||
					($tiraggio->status === "open" &&
						empty($tiraggio->importo_richiesto) &&
						empty($delibera?->max_atp_effettivo) &&
						empty($delibera?->max_atp_consigliato))),
			default => false,
		};
	}

	/**
	 * Base query for simulation candidates: platform users only.
	 *
	 * The simulation impersonates a platform customer, so backoffice accounts
	 * (Filiale, Product Owner, backpack admins) must never be selected — e.g.
	 * a Filiale user has no storici and would otherwise surface as a perfectly
	 * valid "error_not_eleggibile" candidate.
	 *
	 * @return Builder<User>
	 */
	private function platformUsersQuery(): Builder
	{
		return User::query()
			->whereHas("role", static fn($roleQuery) => $roleQuery->where("name", "User"))
			->whereNull("backpack_role_id");
	}

	private function applyBancaAbiFilter($query, ?string $bancaAbi): void
	{
		$normalized = $this->normalizeBancaAbi($bancaAbi);

		if ($normalized !== null) {
			$query->where("banca_abi", $normalized);
		}
	}

	private function normalizeBancaAbi(?string $bancaAbi): ?string
	{
		$bancaAbi = trim((string) $bancaAbi);

		if ($bancaAbi === "") {
			return null;
		}

		return str_pad($bancaAbi, 5, "0", STR_PAD_LEFT);
	}

	/**
	 * Banks with at least one technically eligible user linked to a preset-relevant tiraggio.
	 *
	 * @return Builder<Banca>
	 */
	private function banksWithSimulationDataQuery(): Builder
	{
		$bancaAbis = $this->platformUsersQuery()
			->where("sospeso", false)
			->whereHas("tiraggi", static fn($q) => $q->whereIn("status", array_column(self::SCENARIOS, "status")))
			->distinct()
			->pluck("banca_abi")
			->filter()
			->values()
			->all();

		return Banca::query()->whereIn("banca_abi", $bancaAbis);
	}

	/**
	 * @param  Collection<int, User>  $users
	 * @return Collection<int, User>
	 */
	private function filterTechnicallyEligibleUsers(Collection $users, ?string $status): Collection
	{
		if ($users->isEmpty()) {
			return $users;
		}

		// hasMultiTiraggio() reads the product through the bank: once for all.
		EloquentCollection::make($users->all())->loadMissing("banca.prodotto");

		$context = app(EleggibilitaListContext::class);
		$context->warm($users);

		return $users
			->filter(function (User $user) use ($context, $status): bool {
				// Must be platform-eligible (not suspended + technical thresholds).
				if (!$user->isEleggibile(EleggibilitaContext::Platform)) {
					return false;
				}

				// Must also be in target for the bank product, otherwise the
				// simulated user can log in but cannot perform any operation.
				$prodotto = $context->prodottoForBanca($user->banca_abi);
				if ($prodotto === null || !$user->isInTarget($prodotto)) {
					return false;
				}

				// The user's priority tiraggio (the one actually shown after
				// login, see UserCreditoChecker::priorityTiraggio()) must match
				// the requested scenario status. Checking mere presence via
				$tiraggio = $user->priorityTiraggio();

				if ($status === "accepted" && $user->hasMultiTiraggio()) {
					return $user->acceptedTiraggi()->isNotEmpty() && $tiraggio?->status !== "requested";
				}

				if ($status !== null && $tiraggio?->status !== $status) {
					return false;
				}

				// Mirror UserResource::resolvePlatformHomeTranslateCode(): a
				// closed delibera (without an accepted/requested tiraggio)
				// makes the home page show warning_tiraggio_unavailable, so
				// the simulation would not land on the scenario's expected
				// state. Those users belong to the dedicated
				// error_delibera_closed scenario instead.
				$delibera = $user->deliberaForTiraggio($tiraggio);

				if ($delibera?->status === "close" && !in_array($tiraggio?->status, ["accepted", "requested"], true)) {
					return false;
				}

				// An open tiraggio needs a credit ceiling to be actionable:
				// without max_atp_effettivo the slider on management-analysis
				// has no cap, and the user cannot request anything. The
				// simulation would land on a page that cannot proceed, so
				// exclude these users from the tiraggio_open scenario.
				if ($status === "open" && $delibera?->max_atp_effettivo === null) {
					return false;
				}

				return true;
			})
			->values();
	}

	/**
	 * @return array{user_id: string|null, operator_user_id: string|null, operator_email: string|null, operator_role: string|null}
	 */
	public function operatorContext(): array
	{
		$operator = backpack_auth()->user();

		return [
			"user_id" => $operator?->user_id,
			"email" => $operator?->email,
			"role" => $operator?->backpackRole?->name,
		];
	}
=======
    /** The manual entry form: the operator types the username. */
    public const SCENARIO_MANUAL = 'manual';

    /**
     * Login scenarios: key => [label, status badge].
     *
     * @var array<string, array{label: string, status: string}>
     */
    public const SCENARIOS = [
        'verified' => ['label' => 'Utente con email verificata', 'status' => 'ok'],
        'unverified' => ['label' => 'Utente con email non verificata', 'status' => 'pending'],
    ];

    /**
     * Scenarios that must fail, to exercise the error paths of the login:
     * key => [label, translate code shown as badge].
     *
     * @var array<string, array{label: string, translate_code: string}>
     */
    public const ERROR_SCENARIOS = [
        'unknown-user' => ['label' => 'Username inesistente', 'translate_code' => 'user_not_found'],
    ];

    /**
     * Login scenarios with their candidate counts, for the page.
     *
     * @return array<string, array{key: string, label: string, status: string, count: int, available: bool}>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach (self::SCENARIOS as $key => $scenario) {
            $count = $this->candidates($key)->count();
            $catalog[$key] = [
                'key' => $key,
                'label' => $scenario['label'],
                'status' => $scenario['status'],
                'count' => $count,
                'available' => $count > 0,
            ];
        }

        return $catalog;
    }

    /**
     * Error scenarios, always available: they need no existing user.
     *
     * @return array<string, array{key: string, label: string, translate_code: string, count: int, available: bool}>
     */
    public function errorCatalog(): array
    {
        $catalog = [];

        foreach (self::ERROR_SCENARIOS as $key => $scenario) {
            $catalog[$key] = [
                'key' => $key,
                'label' => $scenario['label'],
                'translate_code' => $scenario['translate_code'],
                'count' => 1,
                'available' => true,
            ];
        }

        return $catalog;
    }

    public function labelFor(string $scenario): string
    {
        return self::SCENARIOS[$scenario]['label']
            ?? self::ERROR_SCENARIOS[$scenario]['label']
            ?? 'Inserimento manuale';
    }

    public function isKnownScenario(string $scenario): bool
    {
        return $scenario === self::SCENARIO_MANUAL
            || isset(self::SCENARIOS[$scenario])
            || isset(self::ERROR_SCENARIOS[$scenario]);
    }

    /**
     * Username to log in with for a scenario: a random candidate, or a
     * username guaranteed not to exist for the "unknown-user" error scenario.
     * Null when the scenario has no candidate.
     */
    public function randomUsernameForScenario(string $scenario): ?string
    {
        if ($scenario === 'unknown-user') {
            return 'sim-unknown-'.Str::lower(Str::random(10));
        }

        if (! isset(self::SCENARIOS[$scenario])) {
            return null;
        }

        return $this->candidates($scenario)->inRandomOrder()->value('username');
    }

    /**
     * The Backpack operator running the simulation, for the page header and
     * the audit log.
     *
     * @return array{user_id: string|null, email: string|null, role: string|null}
     */
    public function operatorContext(): array
    {
        $operator = backpack_auth()->user();

        return [
            'user_id' => $operator?->getKey(),
            'email' => $operator?->email,
            'role' => $operator?->backpackRole?->name,
        ];
    }

    /**
     * Frontend users matching a login scenario.
     */
    private function candidates(string $scenario): Builder
    {
        $query = User::query()->whereNull('backpack_role_id');

        return $this->applyScenario($query, $scenario);
    }

    private function applyScenario(Builder $query, string $scenario): Builder
    {
        return match ($scenario) {
            'verified' => $query->whereNotNull('email_verified_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => $query->whereRaw('1 = 0'),
        };
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
