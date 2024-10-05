<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Mail\OTPMail;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GeneralService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    //this method adds new users
    /**
     * Create an Account
     */
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService)
    {
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth');
    }

    public function generateMaldoID()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $maldo_id = '';

        for ($i = 0; $i < 6; $i++) {
            $index = rand(0, strlen($chars) - 1);
            $maldo_id .= $chars[$index];
        }

        return "maldo" . $maldo_id;
    }


    public function verifyReferralCode(Request $request)
    {
        $referralCode = $request->input('referral_code');

        $userExists = User::where('referral_code', $referralCode)->exists();
        if (!$userExists) {
            return response()->json([
                'message' => 'Referral code does not exist'
            ], 404);
        }

        return response()->json([
            'message' => 'Referral code exists'
        ], 200);
    }

    //create User account
    public function createAccount(Request $request)
    {
        $attr = Validator::make($request->all(), [
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'username' => 'nullable|string|unique:users,username',
            'phone_number' => 'nullable|string',
            'country' => 'nullable|string',
            'referral_by' => 'nullable|string|exists:users,referral_code',
        ]);

        // if there is errors  with the validation, return the errors
        if ($attr->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $attr->errors()
            ], 422);
        }

        if ($request->referral_by) {
            $referral = User::where('referral_code', $request->referral_by)->first();
            if ($referral) {
                $request->merge(['referral_by' => $referral->id]);
                //call function to reward user
                $this->generalService->rewardRefUser($referral->id, 20);
            }
        }

        $maldo_id = $this->generateMaldoID();

        $user = User::create([
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'country' => $request->country,
            'maldorini_id' => $maldo_id,
            // 'referral_code' => $this->generalService->generateReferralCode($request->first_name),
            'referral_by' => $request->referral_by ?? null,
            'referral_code' => $maldo_id,
            'username' => $request->username ?? $request->first_name . $request->last_name . rand(1000, 9999),
            'user_role_id' => 2,
        ]);

        $token = $user->createToken('authToken')->plainTextToken;
        $this->generateOtp($request->email);

        if ($request->maldoId) {
            $this->generalService->rewardRefUser($user->id, 30);
        }

        ActivityLogger::log('User', 'User Registration', 'User has successfully registered', $user->id);


        return response()->json([
            'message' => 'success',
            'token' => $token,
            'data' => $user
        ], 201);
    }


    public function checkSuspendServed($user){
        $suspensionDate = Carbon::parse($user->suspension_date);
        $suspensionDuration = $user->suspension_duration;
        $suspensionEndDate = $suspensionDate->addWeeks($suspensionDuration);


        if (now()->gt($suspensionEndDate)) {
            $user->is_suspended = false;
            $user->suspension_reason = null;
            $user->suspension_date = null;
            $user->suspension_duration = null;
            $user->update();

            ActivityLogger::log('User', 'User Suspension Served', 'User suspension has been served', $user->id);

            // return response()->json([
            //     'message' => 'success',
            //     'user' => $user
            // ], 200);
        }

        return response()->json([
            'message' => 'Account is suspended',
            'suspension_end_date' => $suspensionEndDate
        ], 401);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!auth()->attempt($credentials, $request->remember)) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!auth()->user()->email_verified_at) {
            return response()->json(['error' => 'Email not verified.'], 401);
        }

        //check if suspended then if so write code to check if suspension has expired
        if (auth()->user()->is_suspended) {
            // return response()->json(['error' => 'Account is suspended.'], 401);
            return $this->checkSuspendServed(auth()->user());
        }

        ActivityLogger::log('User', 'User Login', 'User has successfully logged in', auth()->user()->id);

        return response()->json([
            'token' => auth()->user()->createToken('authToken')->plainTextToken,
            'user' => auth()->user()
        ]);
    }


    public function verifyEmail(Request $request)
    {
        $email = $request->input('email');

        $userExists = User::where('email', $email)->exists();
        if ($userExists) {
            return response()->json([
                'message' => 'Email already exists'
            ], 409); // Conflict status code
        }

        return response()->json([
            'message' => 'Email is available'
        ], 200);
    }

    public function verifyUsername(Request $request)
    {
        $username = $request->input('username');

        $userExists = User::where('username', $username)->exists();
        if ($userExists) {
            return response()->json([
                'message' => 'Username already exists'
            ], 409); // Conflict status code
        }

        return response()->json([
            'message' => 'Username is available'
        ], 200);
    }


    public function socialAuth(Request $request)
    {
        // Get user by email
        $user = User::where('email', $request->input('email'))->first();

        //Check if user has an account
        if ($user) {
            $token = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'message' => 'success',
                'token' => $token,
                'user' => $user
            ], 200);
        }

        //make password
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';

        for ($i = 0; $i < 6; $i++) {
            $index = rand(0, strlen($chars) - 1);
            $password .= $chars[$index];
        }

        $maldoId = $this->generateMaldoID();

        $user = User::create([
            'password' => Hash::make($password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'user_role_id' => 2,
            'is_social' => true,
            'social_type' => $request->social_type,
            'maldorini_id' => $maldoId,
            'username' => $request->username ?? $request->first_name . $request->last_name . rand(1000, 9999) . $maldoId,
        ]);

        // Create token for the new user
        $token = $user->createToken('authToken')->plainTextToken;


        return response()->json([
            'message' => 'success',
            'token' => $token,
            'data' => $user
        ], 200);
    }


    public function logout()
    {
        auth()->user()->tokens()->delete();

        ActivityLogger::log('User', 'User Logout', 'User has successfully logged out', auth()->user()->id);

        return response()->json(['message' => 'success'], 200);
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed'
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'failed', 'error' => $validator->errors()], 400);
        }

        if (!password_verify($request->old_password, $user->password)) {
            return response()->json(['message' => 'failed', 'error' => 'Old password is incorrect'], 400);
        }
        $user->password = Hash::make($request->new_password);
        $user->update();

        ActivityLogger::log('User', 'User Password Change', 'User has successfully changed password', $user->id);

        return response()->json(['message' => 'success'], 200);
    }


    public function sendOtp(Request $request)
    {
        $email = $request->email;
        $checkUser = User::where('email', $email)->first();
        if ($checkUser) {
            // user exist
            $this->generateOtp($request->email);
            ActivityLogger::log('User', 'User OTP', 'User has successfully requested for OTP', $checkUser->id);
            return response()->json(['message' => 'success'], 200);
        } else {
            ActivityLogger::log('User', 'User OTP', 'User does not exist', $checkUser->id);
            return response()->json(['message' => 'failed', 'error' => 'User does not exist'], 422);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $this->validateOTP($request->email, $request->otp);

            // Clear OTP and expiration timestamp as it is no longer needed
            $user->otp = null;
            $user->otp_expires_at = null;

            // Set email verification timestamp
            $user->email_verified_at = now();
            $user->save();

            $token = $user->createToken('authToken')->plainTextToken;

            ActivityLogger::log('User', 'User OTP Verification', 'User has successfully verified OTP', $user->id);

            return response()->json([
                'message' => 'success',
                'token' => $token,
                // 'data' => $user
            ], 200);
        } catch (Exception $e) {
            ActivityLogger::log('User', 'User OTP Verification', $e->getMessage(), null, $request->email);
            return response()->json([
                'status' => 'error',
                'errors' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    public function updatePassword(Request $request)
    {
        $attr = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed', //confirmed means password_confirmation
        ]);

        if ($attr->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $attr->errors()
            ], 422);
        }

        $user = auth()->user();
        $user->password = Hash::make($request->password);
        $user->update();

        ActivityLogger::log('User', 'User Password Update', 'User has successfully updated password', $user->id);

        return response()->json(['message' => 'success'], 200);
    }

    //soft delete user account
    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        $user->delete();

        ActivityLogger::log('User', 'User Account Deletion', 'User has successfully deleted account', $user->id);

        return response()->json(['message' => 'success'], 200);
    }


    private function generateOtp($email)
    {
        // Generate 6 random digits
        $randomDigits = mt_rand(100000, 999999);

        // Find the user by email
        $user = User::where('email', $email)->first();

        if ($user) {
            // Set the OTP and expiration timestamp
            $user->otp = $randomDigits;
            $user->otp_expires_at = Carbon::now()->addMinutes(5);
            $user->save();
        }

        try {
            // Send the OTP via email
            //   Mail::to($email)->send(new OTPMail($randomDigits));
            //  $user = $user;
            $notification = ['title' => 'OTP Code from Maldorini', 'otp' => $randomDigits];
            $this->notificationService->sendOTPNotification($user, $notification);
        } catch (Exception $e) {
            return ($e);
        }

        return $randomDigits;
    }

    private function validateOTP($email, $otp)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new Exception('Email does not exist', 404);
        }

        if ($user->otp !== $otp) {
            throw new Exception('OTP is not correct', 400);
        }

        // Check if OTP has expired
        if ($user->otp_expires_at && now()->gt($user->otp_expires_at)) {
            throw new Exception('OTP has expired', 422);
        }

        return $user;
    }
}
