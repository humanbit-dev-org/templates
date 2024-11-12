<?php

namespace Database\Seeders;

use App\Models\OrderUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OrderUser::factory()->count(23)->create();
    }
}
