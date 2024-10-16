<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Country;
use App\Models\Level;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    //
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService)
    {
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
    }


    public function bankList(){
        $paystackSecretKey = env('PAYSTACK_SECRET_KEY');
        $response = Http::withToken($paystackSecretKey)->get('https://api.paystack.co/bank');

        try {
            return $response->json()['data'];
        } catch (\Exception $e) {
            return [];
        }
    }


    public function accountName(Request $request){

        $paystackSecretKey = env('PAYSTACK_SECRET_KEY');
        $response = Http::withToken($paystackSecretKey)->get('https://api.paystack.co/bank/resolve', [
            'account_number' => $request->account_number,
            'bank_code' => $request->bank_code
        ]);

        return $response->json()['data'];
    }


    // public function getRecieverAcct($levelId){
    //     $amount = Level::findOrFail($levelId)->amount;
    //     $receiver = $this->generalService->getReciever($amount);
    //     if(!$receiver){
    //         $receiver = User::where('user_role_id', 1)->first();
    //     }
    //     $receiverDetails = [
    //         'amount' => $amount,
    //         'bank_name' => $receiver->bank_name,
    //         'bank_account_name' => $receiver->bank_account_name,
    //         'bank_account_number' => $receiver->bank_account_number,
    //         'bank_country_id' => Country::findOrFail($receiver->bank_country_id)->name,
    //         'bank_country_code' => Country::findOrFail($receiver->bank_country_id)->alpha_3_code,
    //         'receiver_phone' => $receiver->phone_number,
    //         'receiver_whatsapp' => $receiver->whatsapp_number
    //     ];

    //     return response()->json([
    //         'message' => 'User created successfully',
    //         'data' => $receiverDetails
    //     ], 200);
    // }

    // public function getReceiverAcct($levelId)
    // {
    //     $amount = Level::findOrFail($levelId)->amount;
    //     $user = auth()->user();

    //     // Check for existing valid receiver assignment
    //     $receiverAssignment = $user->assignedReceivers()
    //         ->wherePivot('payment_status', 'pending')
    //         ->wherePivot('expires_at', '>', now())
    //         ->first();

    //     // If assignment exists but is expired, mark it as expired
    //     if ($receiverAssignment && $receiverAssignment->pivot->expires_at <= now()) {
    //         $user->assignedReceivers()->updateExistingPivot($receiverAssignment->id, ['payment_status' => 'expired']);
    //         $receiverAssignment = null; // Clear the expired assignment
    //     }

    //     // If no valid assignment, assign a new receiver
    //     if (!$receiverAssignment) {
    //         $receiver = $this->generalService->getOrAssignReceiver($user->id, $amount);

    //         if ($receiver) {
    //             $user->assignedReceivers()->attach($receiver->id, [
    //                 'expires_at' => now()->addMinutes(30),
    //                 'payment_status' => 'pending'
    //             ]);
    //         } else {
    //             // Fallback to default receiver (e.g., admin)
    //             $receiver = User::where('user_role_id', 1)->first();
    //         }
    //     } else {
    //         // Use the existing receiver assignment
    //         $receiver = $receiverAssignment;
    //     }

    //     // Return receiver details
    //     $receiverDetails = [
    //         'amount' => $amount,
    //         'bank_name' => $receiver->bank_name,
    //         'bank_account_name' => $receiver->bank_account_name,
    //         'bank_account_number' => $receiver->bank_account_number,
    //         'bank_country_id' => Country::findOrFail($receiver->bank_country_id)->name,
    //         'bank_country_code' => Country::findOrFail($receiver->bank_country_id)->alpha_3_code,
    //         'receiver_phone' => $receiver->phone_number,
    //         'receiver_whatsapp' => $receiver->whatsapp_number,
    //     ];

    //     return response()->json([
    //         'message' => 'Receiver details fetched successfully',
    //         'data' => $receiverDetails
    //     ], 200);
    // }



public function getReceiverAcct($levelId)
{
    $amount = Level::findOrFail($levelId)->amount;
    $user = auth()->user();

    // Check for a valid assigned receiver
    $receiverAssignment = $user->assignedReceivers()
        ->wherePivot('payment_status', 'pending')
        ->wherePivot('expires_at', '>', now())
        ->first();

    // If the receiver assignment has expired, update the pivot table status to 'expired'
    if ($receiverAssignment && $receiverAssignment->pivot->expires_at <= now()) {
        $user->assignedReceivers()->updateExistingPivot($receiverAssignment->id, ['payment_status' => 'expired']);
        $receiverAssignment = null; // Invalidate the expired assignment
    }

    // If no valid receiver is found, assign a new one
    if (!$receiverAssignment) {
        $receiver = $this->generalService->getOrAssignReceiver($user->id, $amount);

        if ($receiver) {
            // Attach the new receiver with an expiration time and status 'pending'
            $user->assignedReceivers()->attach($receiver->id, [
                'expires_at' => now()->addMinutes(30),
                'payment_status' => 'pending'
            ]);
        } else {
            // Fallback to a default receiver, e.g., admin
            $receiver = User::where('user_role_id', 1)->first();
        }
    } else {
        $receiver = $receiverAssignment;
    }

    // Prepare the receiver details for response
    $receiverDetails = [
        'amount' => $amount,
        'bank_name' => $receiver->bank_name,
        'bank_account_name' => $receiver->bank_account_name,
        'bank_account_number' => $receiver->bank_account_number,
        'bank_country_id' => Country::findOrFail($receiver->bank_country_id)->name,
        'bank_country_code' => Country::findOrFail($receiver->bank_country_id)->alpha_3_code,
        'receiver_phone' => $receiver->phone_number,
        'receiver_whatsapp' => $receiver->whatsapp_number,
    ];

    return response()->json([
        'message' => 'Receiver details fetched successfully',
        'data' => $receiverDetails
    ], 200);
}



    public function confirmPayment($userId, $receiverId, $otp){
        $user = User::find($userId);
        $receiver = User::find($receiverId);

        $assignment = $user->assignedReceivers()
            ->wherePivot('receiver_id', $receiverId)
            ->wherePivot('payment_status', 'pending')
            ->first();

        if (!$assignment || $this->validateOtp($receiverId, $otp) === false) {
            return response()->json(['message' => 'Invalid OTP or no pending assignment found.'], 400);
        }

        // Update balances
        $amount = $assignment->pivot->amount; // Assuming amount is stored
        $receiver->task_balance -= $amount;
        $receiver->save();

        // Update assignment payment_status to confirmed
        $assignment->pivot->payment_status = 'confirmed';
        $assignment->pivot->save();

        // Send notification to receiver
        $this->notificationService->userNotification($receiver->id, 'Payment', 'Payment received', 'You have received a payment.', 'You have received a payment.', true, [], '', '');
        ActivityLogger::log('Payment', 'Payment received', 'You have received a payment.', $receiver->id);

        // Adjust ref_sort for the receiver
        $this->generalService->adjustRefSort($receiverId);

        // Record transaction
        $this->generalService->recordTransaction($userId, $receiverId, $amount);

        // Share amount with referrals
        $this->generalService->shareAmount($amount, $user->level_id, $userId);

        return response()->json(['message' => 'Payment confirmed successfully.'], 200);
    }




}
