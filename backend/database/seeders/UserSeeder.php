<?php

namespace Database\Seeders;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = config('backpack.base.admin_panel_password');

        User::create([
            'username' => 'aborreca',
            'name' => 'Andrea',
            'surname' => 'Borreca',
            'address' => 'Via Aosta, 2',
            'email' => 'borreca@humanbit.com',
            'phone' => fake()->phoneNumber(),
            'backpack_role_id' => BackpackRole::where('name', 'Developer')->first()->id,
            'role_id' => Role::where('name', 'User')->first()->id,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        User::create([
            'username' => 'gsilveri',
            'name' => 'Gabriel',
            'surname' => 'Silveri',
            'address' => 'Via Aosta, 2',
            'email' => 'silveri@humanbit.com',
            'phone' => fake()->phoneNumber(),
            'backpack_role_id' => BackpackRole::where('name', 'Developer')->first()->id,
            'role_id' => Role::where('name', 'User')->first()->id,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        User::create([
            'username' => 'mbusan',
            'name' => 'Matteo',
            'surname' => 'Busan',
            'address' => 'Via Aosta, 2',
            'email' => 'busan@humanbit.com',
            'phone' => fake()->phoneNumber(),
            'backpack_role_id' => BackpackRole::where('name', 'Developer')->first()->id,
            'role_id' => Role::where('name', 'User')->first()->id,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        User::create([
            'username' => 'mmolinari',
            'name' => 'Marco',
            'surname' => 'Molinari',
            'address' => 'Via Aosta, 2',
            'email' => 'marcom@cashberry.it',
            'phone' => fake()->phoneNumber(),
            'backpack_role_id' => BackpackRole::where('name', 'Developer')->first()->id,
            'role_id' => Role::where('name', 'User')->first()->id,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        User::create([
            'username' => 'csolia',
            'name' => 'Christian',
            'surname' => 'Solia',
            'address' => 'Via Aosta, 2',
            'email' => 'solia@humanbit.com',
            'phone' => fake()->phoneNumber(),
            'backpack_role_id' => BackpackRole::where('name', 'Developer')->first()->id,
            'role_id' => Role::where('name', 'User')->first()->id,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }
}
