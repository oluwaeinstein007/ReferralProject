<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Define the settings to be seeded
        $settings = [
            [
                'name' => 'task_percentage',
                'value' => '0.7',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ref_percentage',
                'value' => '0.3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'default_admin_id',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Create or update settings
        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name']], // Unique field to check
                $setting
            );
        }
    }
}
