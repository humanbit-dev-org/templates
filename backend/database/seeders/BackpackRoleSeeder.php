<?php

namespace Database\Seeders;

use App\Models\BackpackRole;
use Illuminate\Database\Seeder;

class BackpackRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BackpackRole::create([
            'name' => 'Admin',
            'description' => 'Administrator',
        ]);
        BackpackRole::create([
            'name' => 'Developer',
            'description' => 'Developer',
        ]);
        BackpackRole::create([
            'name' => 'Guest',
            'description' => 'Guest',
        ]);
    }
}
