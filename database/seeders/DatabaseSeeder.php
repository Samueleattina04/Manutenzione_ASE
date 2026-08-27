<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Amministratore', 'username' => 'admin', 'password' => 'admin123', 'role' => 'admin'],
            ['name' => 'Operatore Demo', 'username' => 'operatore', 'password' => 'operatore123', 'role' => 'operatore'],
            ['name' => 'Manutentore Demo', 'username' => 'manutentore', 'password' => 'manutentore123', 'role' => 'manutentore'],
        ];

        foreach ($defaults as $u) {
            User::firstOrCreate(
                ['username' => $u['username']],
                [
                    'name' => $u['name'],
                    'password' => $u['password'], // hashed via cast
                    'role' => $u['role'],
                    'active' => true,
                ]
            );
        }
    }
}
