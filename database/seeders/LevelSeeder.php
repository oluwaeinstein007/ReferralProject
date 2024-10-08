<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Level;

class LevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $levels = [
            [
                'name' => 'Chicken',
                'amount' => 0,
                'referrer_1_percentage' => 0,
                'referrer_2_percentage' => 0,
                'admin_percentage' => 100,
            ],
            [
                'name' => 'Hen',
                'amount' => 20000,
                'referrer_1_percentage' => 50,
                'referrer_2_percentage' => 30,
                'admin_percentage' => 20,
            ],
            [
                'name' => 'Turkey',
                'amount' => 50000,
                'referrer_1_percentage' => 50,
                'referrer_2_percentage' => 30,
                'admin_percentage' => 20,
            ],
        ];

        foreach ($levels as $levelData) {
            Level::updateOrCreate(
                ['name' => $levelData['name']],
                $levelData
            );
        }
    }
}
