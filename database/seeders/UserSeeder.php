<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
        DB::table('users')->insert([
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'user_role_id' => 1,  // Admin
                'phone_number' => '08012345678',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'moderator@example.com',
                'password' => Hash::make('password'),
                'user_role_id' => 2,  // Moderator
                'phone_number' => '08098765432',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'Sam',
                'last_name' => 'Smith',
                'email' => 'user@example.com',
                'password' => Hash::make('password'),
                'user_role_id' => 3,  // Regular User
                'phone_number' => '08056789123',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
