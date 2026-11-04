<?php

namespace Tests\Feature\Import;

use App\Http\Controllers\Admin\Action\ImportCsvAction;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * After a CSV import of users, plain-text passwords are hashed with the
 * application hasher, and passwords that are already hashed are left alone
 * (they used to be hashed a second time, making them unusable).
 */
class ImportPasswordHashingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_plain_text_passwords_are_hashed(): void
    {
        $this->seed(RoleSeeder::class);
        $roleId = Role::query()->where('name', 'User')->value('id');
        $alreadyHashed = Hash::make('kept-password');

        // Raw inserts, as the bulk import writes them: no model cast involved.
        DB::table('users')->insert([
            ['username' => 'import-test-plain', 'email' => 'import-test-plain@example.test', 'name' => 'P', 'surname' => 'L', 'role_id' => $roleId, 'password' => 'plain-password'],
            ['username' => 'import-test-hashed', 'email' => 'import-test-hashed@example.test', 'name' => 'H', 'surname' => 'D', 'role_id' => $roleId, 'password' => $alreadyHashed],
        ]);

        ImportCsvAction::hashUserPasswordsAfterImport(true);

        $plain = DB::table('users')->where('username', 'import-test-plain')->value('password');
        $this->assertNotSame('plain-password', $plain);
        $this->assertTrue(Hash::check('plain-password', $plain));

        $this->assertSame($alreadyHashed, DB::table('users')->where('username', 'import-test-hashed')->value('password'));
    }
}
