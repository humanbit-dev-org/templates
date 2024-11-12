<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Aggiungi ogni utente a un gruppo casuale
        $users = User::all();
        $groups = Group::all();

        foreach ($users as $user) {
            $randomGroups = $groups->random(rand(1, 3));
            foreach ($randomGroups as $group) {
                $user->groups()->attach($group->id);
            }
        }
    }
}
