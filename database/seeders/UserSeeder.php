<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();
        $password = Hash::make('password');

        $users = [
            ['external_id' => 91, 'name' => '管理者', 'email' => 'admin@example.com'],
            ['external_id' => 22, 'name' => '設備保全USER', 'email' => 'user22@example.com'],
            ['external_id' => 143, 'name' => '設備保全USER', 'email' => 'user143@example.com'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['external_id' => $user['external_id']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => $password,
                    'email_verified_at' => $now,
                ]
            );
        }
    }
}
