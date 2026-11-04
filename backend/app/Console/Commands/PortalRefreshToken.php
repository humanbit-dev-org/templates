<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Issues an API client's root credential: a 'portal-refresh:{prefix}' PAT
 * on the user the client acts as.
 *
 * The plaintext is printed once, here, for internal hand-out over the
 * agreed channel — it is never stored recoverable and never mailed. The
 * client exchanges it at POST /api/v1/token for the api_token it then uses
 * on the portal endpoints. The refresh token itself never expires;
 * rotation is manual via --rotate.
 *
 * Usage:
 *   portal:refresh-token USERNAME
 *   portal:refresh-token USERNAME --rotate
 */
class PortalRefreshToken extends Command
{
<<<<<<< HEAD
	protected $signature = 'portal:refresh-token
                            {user : user_id of the bank\'s Product Owner}
                            {--rotate : revoke the existing refresh token and mint a fresh one}';

	protected $description = "Issue the portal refresh token for a bank (on its Product Owner user)";

	public function handle(): int
	{
		$user = User::where("user_id", $this->argument("user"))->first();
=======
    protected $signature = 'portal:refresh-token
                            {user : username of the user the API client acts as}
                            {--rotate : revoke the existing refresh token and mint a fresh one}';

    protected $description = 'Issue the portal refresh token for an API client (on the user it acts as)';

    public function handle(): int
    {
        $user = User::query()->find($this->argument('user'));
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		if (!$user) {
			$this->error("User [{$this->argument("user")}] not found.");

			return self::FAILURE;
		}

<<<<<<< HEAD
		if (!$user->isProductOwner()) {
			$this->error("User [{$user->user_id}] is not a Product Owner — portal credentials live on POs only.");
=======
        if (! $user->canUsePortal()) {
            $this->error("User [{$user->getKey()}] has no role allowed on the portal (config/portal.php 'roles').");
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

			return self::FAILURE;
		}

		$prefix = config("portal.refresh_token_prefix");
		$existing = $user->tokens()->where("name", "like", $prefix . ":%");

		if ($existing->exists()) {
			if (!$this->option("rotate")) {
				$this->warn("A refresh token already exists for this user. Use --rotate to replace it.");

				return self::FAILURE;
			}

			$existing->delete();
		}

		// The display prefix rides inside the token name, so mint first and
		// then rename the row using the secret's own first characters.
		$token = $user->createToken($prefix . ":pending", ["portal:auth"]);
		$token->accessToken->update([
			"name" => $prefix . ":" . Str::substr(Str::after($token->plainTextToken, "|"), 0, 8),
		]);

<<<<<<< HEAD
		$this->info("Portal refresh token for " . $user->user_id . " (bank " . $user->banca_abi . "):");
		$this->newLine();
		$this->line($token->plainTextToken);
		$this->newLine();
		$this->comment("Shown once — hand it to the bank over the agreed channel. It is not stored recoverable.");
=======
        $this->info('Portal refresh token for '.$user->getKey().':');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Shown once — hand it to the client over the agreed channel. It is not stored recoverable.');
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		return self::SUCCESS;
	}
}
