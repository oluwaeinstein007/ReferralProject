<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    //
        // Create a new Level (Create)
        public function createLevel(Request $request)
        {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'amount' => 'required|numeric',
                'referrer_1_percentage' => 'required|numeric|min:0|max:100',
                'referrer_2_percentage' => 'required|numeric|min:0|max:100',
                'admin_percentage' => 'required|numeric|min:0|max:100',
            ]);

            $totalPercentage = $validated['referrer_1_percentage']
                            + $validated['referrer_2_percentage']
                            + $validated['admin_percentage'];

            if ($totalPercentage !== 100) {
                return response()->json([
                    'message' => 'The sum of referrer_1_percentage, referrer_2_percentage, and admin_percentage must be 100%'
                ], 422);
            }

            $level = Level::create($validated);

            return response()->json(['message' => 'Level created successfully', 'data' => $level], 201);
        }



        public function getLevels($id = null){
            if ($id) {
                $level = Level::find($id);

                if (!$level) {
                    return response()->json(['message' => 'Level not found'], 404);
                }

                return response()->json($level, 200);
            }

            return response()->json(Level::all(), 200);
        }


        // Update an level (Update)
        public function updateLevel(Request $request, $id)
        {
            $level = Level::find($id);

            if (!$level) {
                return response()->json(['message' => 'Level not found'], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'amount' => 'sometimes|numeric',
                'referrer_1_percentage' => 'sometimes|numeric|min:0|max:100',
                'referrer_2_percentage' => 'sometimes|numeric|min:0|max:100',
                'admin_percentage' => 'sometimes|numeric|min:0|max:100',
            ]);

            $referrer_1_percentage = $validated['referrer_1_percentage'] ?? $level->referrer_1_percentage;
            $referrer_2_percentage = $validated['referrer_2_percentage'] ?? $level->referrer_2_percentage;
            $admin_percentage = $validated['admin_percentage'] ?? $level->admin_percentage;
            $totalPercentage = $referrer_1_percentage + $referrer_2_percentage + $admin_percentage;

            if ($totalPercentage !== 100) {
                return response()->json([
                    'message' => 'The sum of referrer_1_percentage, referrer_2_percentage, and admin_percentage must be 100%'
                ], 422);
            }

            $level->update($validated);

            return response()->json(['message' => 'Level updated successfully', 'data' => $level], 200);
        }


        // Delete an Level (Delete)
        public function deleteLevel($id)
        {
            $level = Level::find($id);

            if (!$level) {
                return response()->json(['message' => 'Level not found'], 404);
            }

            $level->delete();

            return response()->json(['message' => 'Level deleted successfully'], 200);
        }
}
