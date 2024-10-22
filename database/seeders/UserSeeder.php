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
        // Define the users to be seeded
        $users = [
            [
                'full_name' => 'John Doe',
                'email' => 'admin@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08012345678',
                'status' => 'active',
                'bank_name' => 'Bank A',
                'bank_account_name' => 'John Doe',
                'bank_account_number' => '12345678901',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'full_name' => 'Jane Doe',
                'email' => 'moderator@example.com',
                'user_role_id' => 2,  // Moderator
                'phone_number' => '08098765432',
                'status' => 'active',
                'bank_name' => 'Bank B',
                'bank_account_name' => 'Jane Doe',
                'bank_account_number' => '23456789012',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'full_name' => 'Sam Smith',
                'email' => 'user@example.com',
                'user_role_id' => 3,  // Regular User
                'phone_number' => '08056789123',
                'status' => 'active',
                'bank_name' => 'Bank C',
                'bank_account_name' => 'Sam Smith',
                'bank_account_number' => '34567890123',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ],
            // Add more users for a total of 8 admins
            [
                'full_name' => 'Admin One',
                'email' => 'admin1@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08011112222',
                'status' => 'active',
                'bank_name' => 'Bank D',
                'bank_account_name' => 'Admin One',
                'bank_account_number' => '45678901234',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'full_name' => 'Admin Two',
                'email' => 'admin2@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08022223333',
                'status' => 'active',
                'bank_name' => 'Bank E',
                'bank_account_name' => 'Admin Two',
                'bank_account_number' => '56789012345',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'full_name' => 'Admin Three',
                'email' => 'admin3@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08033334444',
                'status' => 'active',
                'bank_name' => 'Bank F',
                'bank_account_name' => 'Admin Three',
                'bank_account_number' => '67890123456',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'full_name' => 'Admin Four',
                'email' => 'admin4@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08044445555',
                'status' => 'active',
                'bank_name' => 'Bank G',
                'bank_account_name' => 'Admin Four',
                'bank_account_number' => '78901234567',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'full_name' => 'Admin Five',
                'email' => 'admin5@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08055556666',
                'status' => 'active',
                'bank_name' => 'Bank H',
                'bank_account_name' => 'Admin Five',
                'bank_account_number' => '89012345678',
                'bank_country_id' => 1, // Adjust as necessary
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Create or update users
        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']], // Unique field to check
                $user
            );
        }
    }
}
