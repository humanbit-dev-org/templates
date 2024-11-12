<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            EcommerceSeeder::class,
            GroupSeeder::class,
            OrderSeeder::class,
            OrderUserSeeder::class,
            GroupUserSeeder::class,
        ]);
    }
}
