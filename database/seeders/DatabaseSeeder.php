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
            ['name' => 'Operatore', 'username' => 'operatore', 'password' => 'operatore123', 'role' => 'operatore'],
            ['name' => 'Manutentore Interno Demo', 'username' => 'manutentore', 'password' => 'manutentore123', 'role' => 'manutentore'],
            ['name' => 'Manutentore Esterno Demo', 'username' => 'esterno', 'password' => 'esterno123', 'role' => 'manutentore_esterno'],
            ['name' => 'Manutentore Straordinario Demo', 'username' => 'straordinario', 'password' => 'straordinario123', 'role' => 'manutentore_straordinario'],
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
