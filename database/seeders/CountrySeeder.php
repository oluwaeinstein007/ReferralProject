<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Path to the JSON file
        $file = storage_path('countries/country_codes.json');

        // Read the JSON file
        $country_json = json_decode(file_get_contents($file), true);

        // Loop through each country in the JSON file and insert into the database
        foreach ($country_json as $country) {
            DB::table('countries')->insert([
                'name' => $country['Country'],
                'alpha_3_code' => $country['Alpha-3 code'],
                'numeric_code' => $country['Numeric'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
