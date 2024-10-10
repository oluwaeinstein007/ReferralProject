<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Notification;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GeneralService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    protected $generalService;
    public function __construct(GeneralService $generalService)
    {
        $this->generalService = $generalService;
        // $this->middleware('auth');
    }


    public function editProfile(Request $request)
    {
        $user = auth()->user();
        $user_id = $user->id;

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
            'username' => 'sometimes|required|string',
            'gender' => 'sometimes|required|string',
            'date_of_birth' => 'sometimes|required|string',
            'address' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($user_id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'errors' => 'User not found',
            ], 404);
        }

        $user->update($request->only(['first_name', 'last_name','gender', 'date_of_birth','country', 'state', 'username']));

        return response()->json([
            'message' => 'success',
            'data' => $user,
        ], 200);
    }


    public function getNotification(Request $request){
        $user = auth()->user();
        $perPage = $request->input('perPage', 100);
        $page = $request->input('page', 1);
        $notifications = Notification::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
        $notifications = $notifications->forPage($page, $perPage);

        // ActivityLogger::log('User', 'User Notification', 'A new visa application has been made', $user->user_uuid);

        return response()->json([
            'message' => 'success',
            'data' => $notifications,
        ], 200);
    }

    //change notification status
    public function changeNotificationStatus($id, Request $request){
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)->where('id', $id)->first();
        if(!$notification){
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }
        $notification->status = $request->status;
        $notification->save();
        return response()->json([
            'message' => 'Notification status changed successfully',
        ], 200);
    }



    // Store user with bank details
    public function createBankDetails(Request $request)
    {
        $validatedData = $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:11',
            'bank_country_code' => 'nullable|string|max:3',
        ]);

        $country_id = Country::where('alpha_3_code', $validatedData['bank_country_code'])->first()->id;
        $validatedData['bank_country_id'] = $country_id;
        $user = auth()->user();
        $user->update($validatedData);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }


    public function showBankDetails($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }


    public function updateBankDetails(Request $request, $id)
    {
        $validatedData = $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:11',
            'bank_country_code' => 'nullable|string|max:3',
        ]);

        $country_id = Country::where('alpha_3_code', $validatedData['bank_country_code'])->first()->id;
        $validatedData['bank_country_id'] = $country_id;
        $user = User::findOrFail($id);
        $user->update($validatedData);

        return response()->json([
            'message' => 'User bank details updated successfully',
            'data' => $user
        ]);
    }


    public function deleteBankDetails($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'bank_country_id' => null,
        ]);

        return response()->json([
            'message' => 'User deleted successfully',
            'data' => $user
        ]);
    }


    //user
    public function checkBal(){
        $amount = 500.00;
        // Query for the highest ranked user based on total balance
        $user1 = $this->generalService->getUserWithHighestRankOrProportion($amount, false);

        // Query for a user with the 30/70 proportion
        $user2 = $this->generalService->getUserWithHighestRankOrProportion($amount, true);

        return response()->json([
            'message' => 'User deleted successfully',
            'data1' => $user1,
            'data2' => $user1,
        ]);
    }


}
