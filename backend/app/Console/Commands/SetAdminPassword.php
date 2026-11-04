<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Set an Argon2id password on Backpack admin accounts.
 *
 * auth:rehash-passwords skips admins on purpose: their passwords are set by
 * hand, not HKDF-derived from the username. After a legacy migration the migrated
 * admins therefore land with an empty password and need one from here.
 *
 * The plain password is never a command default: pass --password only for
 * automation, otherwise it is prompted and not echoed.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'auth:set-admin-password
                            {--email-contains= : Only admins whose email contains this string}
                            {--password= : Plain password (prompted if omitted)}
                            {--only-empty : Skip admins that already have a password}
                            {--dry-run : List the matching admins without writing}';

    protected $description = 'Set an Argon2id password on Backpack admin accounts';

    public function handle(): int
    {
        $query = User::query()->whereNotNull('backpack_role_id');

        $needle = (string) $this->option('email-contains');
        if ($needle !== '') {
            $query->where('email', 'like', '%'.$needle.'%');
        }

        if ($this->option('only-empty')) {
            $query->where(function ($q) {
                $q->whereNull('password')->orWhere('password', '');
            });
        }

        $users = $query->orderBy('username')->get(['username', 'email']);

        if ($users->isEmpty()) {
            $this->info('No matching admin accounts.');

            return self::SUCCESS;
        }

        $this->table(
            ['username', 'email'],
            $users->map(fn (User $u) => [$u->username, $u->email])->all()
        );

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] '.$users->count().' admin(s) would be updated.');

            return self::SUCCESS;
        }

        $plain = (string) ($this->option('password') ?: $this->secret('Plain password'));

        if (trim($plain) === '') {
            $this->error('Empty password refused.');

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        // One Hash::make per user: the plain password is shared by design, but
        // reusing a single hash would also leak that fact to anyone reading the
        // table. A per-row Argon2id salt costs a few seconds on this many rows.
        // Query-builder update bypasses the model's "hashed" cast, so the value
        // written here must already be hashed.
        foreach ($users as $user) {
            User::query()
                ->where('username', $user->username)
                ->update(['password' => Hash::make($plain)]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Done. '.$users->count().' admin(s) updated.');

        return self::SUCCESS;
    }
}
