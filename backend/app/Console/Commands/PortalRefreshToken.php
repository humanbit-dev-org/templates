<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Issues the bank's root API credential: a 'portal-refresh:{prefix}' PAT
 * on the bank's Product Owner.
 *
 * The plaintext is printed once, here, for internal hand-out over the
 * agreed channel — it is never stored recoverable and never mailed. The
 * bank exchanges it at POST /api/v1/token for the api_token it then uses
 * on the portal endpoints. The refresh token itself never expires;
 * rotation is manual via --rotate.
 *
 * Usage:
 *   portal:refresh-token PO_USER_ID
 *   portal:refresh-token PO_USER_ID --rotate
 */
class PortalRefreshToken extends Command
{
    protected $signature = 'portal:refresh-token
                            {user : user_id of the bank\'s Product Owner}
                            {--rotate : revoke the existing refresh token and mint a fresh one}';

    protected $description = 'Issue the portal refresh token for a bank (on its Product Owner user)';

    public function handle(): int
    {
        $user = User::where('user_id', $this->argument('user'))->first();

        if (! $user) {
            $this->error("User [{$this->argument('user')}] not found.");

            return self::FAILURE;
        }

        if (! $user->isProductOwner()) {
            $this->error("User [{$user->user_id}] is not a Product Owner — portal credentials live on POs only.");

            return self::FAILURE;
        }

        $prefix = config('portal.refresh_token_prefix');
        $existing = $user->tokens()->where('name', 'like', $prefix.':%');

        if ($existing->exists()) {
            if (! $this->option('rotate')) {
                $this->warn('A refresh token already exists for this user. Use --rotate to replace it.');

                return self::FAILURE;
            }

            $existing->delete();
        }

        // The display prefix rides inside the token name, so mint first and
        // then rename the row using the secret's own first characters.
        $token = $user->createToken($prefix.':pending', ['portal:auth']);
        $token->accessToken->update([
            'name' => $prefix.':'.Str::substr(Str::after($token->plainTextToken, '|'), 0, 8),
        ]);

        $this->info('Portal refresh token for '.$user->user_id.' (bank '.$user->banca_abi.'):');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Shown once — hand it to the bank over the agreed channel. It is not stored recoverable.');

        return self::SUCCESS;
    }
}
