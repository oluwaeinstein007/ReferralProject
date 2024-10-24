<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Country;
use App\Models\Level;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Http;
//log
use Illuminate\Support\Facades\Log;

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


    public function getReceiverAcct($levelId){
        $amount = Level::findOrFail($levelId)->amount;
        $user = auth()->user();

        // Check for a valid assigned receiver
        $receiverAssignment = $user->assignedReceivers()
            ->wherePivot('payment_status', 'pending')
            // ->wherePivot('expires_at', '>', now())
            ->first();

        // If the receiver assignment has expired, update the pivot table status to 'expired'
        if ($receiverAssignment && $receiverAssignment->pivot->expires_at <= now()) {
            $user->assignedReceivers()->updateExistingPivot($receiverAssignment->id, ['payment_status' => 'expired']);
            //detach the expired receiver
            $user->assignedReceivers()->detach($receiverAssignment->id);
            $receiverAssignment = null;
        }

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
                $defaultAdminId = Setting::where('name', 'default_admin_id')->first()->value ?? 1;
                $receiver = User::find($defaultAdminId);
                // $user = $receiver;
                $user->assignedReceivers()->attach($receiver->id, [
                    'expires_at' => now()->addMinutes(30),
                    'payment_status' => 'pending'
                ]);
            }
        } else {
            $receiver = $receiverAssignment;
        }
        // Prepare the receiver details for response
        $receiverDetails = [
            'amount' => $amount,
            'receiver_id' => $receiver->id,
            'bank_name' => $receiver->bank_name,
            'bank_account_name' => $receiver->bank_account_name,
            'bank_account_number' => $receiver->bank_account_number,
            'bank_country_id' => Country::find($receiver->bank_country_id)->name ?? '',
            'bank_country_code' => Country::find($receiver->bank_country_id)->alpha_3_code ?? '',
            'receiver_phone' => $receiver->phone_number,
            'receiver_whatsapp' => $receiver->whatsapp_number,
        ];

        return response()->json([
            'message' => 'Receiver details fetched successfully',
            'data' => $receiverDetails
        ], 200);
    }


    public function initiateTransaction(Request $request){
        $user = auth()->user();
        $senderId = $user->id;
        $receiver = User::find($request->receiverId);
        $amount = Level::find($request->levelId)->amount ?? $request->amount;
        $transactionId = 'TRX' . time();
        $otp = mt_rand(100000, 999999);
        $baseUrlFE = env('FRONTEND_BASE_URL');
        $link = $baseUrlFE . '/confirm-payment/' . $transactionId . '/' . $otp;

        Transaction::create([
            'sender_user_id' => $senderId,
            'receiver_user_id' => $receiver->id,
            'amount' => $amount,
            'status' => 'initiated',
            'level_id' => $request->levelId,
            'otp' => $otp,
            'link' => $link,
            'transaction_id' => $transactionId
        ]);

        $data = [
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'bank_name' => $receiver->bank_name,
            'bank_account_name' => $receiver->bank_account_name,
            'bank_account_number' => $receiver->bank_account_number,
            'bank_country_id' => Country::findOrFail($receiver->bank_country_id)->name,
            'bank_country_code' => Country::findOrFail($receiver->bank_country_id)->alpha_3_code,
            'receiver_phone' => $receiver->phone_number,
            'receiver_whatsapp' => $receiver->whatsapp_number,
        ];

        $user->ongoing_transaction = true;
        $user->save();

        $receiver->ongoing_transaction = true;
        $receiver->save();

        try{
            // $this->notificationService->sendOTPNotification($receiver, 'Payment', 'Payment request', 'Payment request', 'You have received a payment request.', 'You have received a payment request from ' . $user['full_name'], true, $otp);
            $this->notificationService->userNotification($receiver, 'Payment', 'Payment request', 'You have received a payment request.', 'You have received a payment request from ' . $user['full_name'], true, $link, 'Confirm Payment');
        }catch(\Exception $e){
            // Log::error($e->getMessage());
        }
        $this->notificationService->userNotification($user, 'Payment', 'Payment request', 'Payment request sent', 'Payment request sent', false);
        ActivityLogger::log('Payment', 'Payment request', 'Payment request sent from ' . $user['full_name'] . ' to ' . $receiver['full_name'] . ' with transaction ID: ' . $transactionId, $senderId);

        return response()->json([
            'message' => 'OTP and Confirmation link has been generated and sent to receiver.',
            'data' => $data
        ], 200);
    }


    public function confirmPayment(Request $request){
        $transaction = Transaction::where('transaction_id', $request->transactionId)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        if ($transaction->otp != $request->otp) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }

        $transaction->status = 'completed';
        $transaction->save();

        $receiverId = $transaction->receiver_user_id;
        $receiver = User::find($receiverId);
        $amount = $transaction['amount'];

        $this->generalService->adjustRefSort($receiverId);
        $this->generalService->adjustBalance($receiverId, $amount);

        $user = User::find($transaction->sender_user_id);
        try {
            $assignment = $user->assignedReceivers()
                ->wherePivot('receiver_id', $receiverId)
                ->wherePivot('payment_status', 'pending')
                ->first();
            // Update assignment payment_status to confirmed
            $assignment->pivot->payment_status = 'confirmed';
            $assignment->pivot->save();
            //delete the assignment
            $user->assignedReceivers()->detach($receiverId);
        } catch (\Exception $e) {
        }

        $this->notificationService->userNotification($receiver, 'Payment', 'Payment received', 'Transaction Complete.', 'You have received a transaction with ID: ' . $transaction->transaction_id. ' from ' . $user['full_name'], false);
        $this->notificationService->userNotification($user, 'Payment', 'Payment sent', 'Transaction Complete.', 'You have sent a transaction with ID: ' . $transaction->transaction_id. ' to ' . $receiver['full_name'], false);
        ActivityLogger::log('Payment', 'Transaction Complete', 'The transaction with ID: ' . $transaction->transaction_id . ' has been completed, initiated by ' . $user['full_name'] . ' and received by ' . $receiver['full_name'], $receiverId);

        // update user level
        $user->level_id = $transaction->level_id;
        $user->ongoing_transaction = false;
        $user->save();
        $receiver->ongoing_transaction = false;
        $receiver->save();
        $this->notificationService->userNotification($user, 'Level', 'Upranking', 'You are now in next level', 'Congratulations! You have successfully completed a transaction and you are now in the next level.', false);
        ActivityLogger::log('Level', 'Upranking', 'User ' . $user['full_name'] . ' has been upranked to the next level' . Level::find($transaction->level_id)->name, $user->id);

        $this->generalService->shareAmount($transaction->amount, $transaction->sender->level_id, $transaction->sender_user_id);
        return response()->json(['message' => 'Payment confirmed successfully.'], 200);
    }


    public function getTransactionHistory($type = null) {
        $userId = auth()->user()->id;

        if ($type) {
            $sendOrReceive = $type == 'incoming' ? 'sender_user_id' : 'receiver_user_id';
            $transactions = Transaction::where($sendOrReceive, $userId)
                ->with([
                    'sender:id,full_name,email',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number',
                ])->get();
        } else {
            $transactions = Transaction::where('sender_user_id', $userId)
                ->orWhere('receiver_user_id', $userId)
                ->with([
                    'sender:id,full_name,email',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number',
                ])->get();
        }

        return response()->json(['message' => 'Transactions fetched successfully.', 'data' => $transactions], 200);
    }



}
