<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Level;
use App\Models\Setting;
use App\Models\Transaction;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use GuzzleHttp\Client;

class GeneralService
{
    /**
     * Perform some general service logic.
     *
     * @param mixed $data
     * @return mixed
     */

    public function generateReferralCode($name){
        $code = Str::random(5); // You can adjust the length as needed
        $referalCode = $name. '-' . $code;
        return $referalCode;
    }


    public function generateTVAId(){
        $timeNow = (microtime(true)*10000);
        return 'REF'.$timeNow;
    }


    public function roundNum($num) {
        return number_format($num, 2, '.', '');
    }


    public function decryptService ($code){
        $decrypted = Crypt::decryptString($code);
        return $decrypted;
    }


    public function encryptService ($value){
        $encrypt = Crypt::encryptString($value);
        return $encrypt;
    }


    /**
     * Shares the amount based on the level's percentages.
     *
     * @param float $amount
     * @param int $levelId
     * @param int $userId
     * @return void
     */
    public function shareAmount($amount, $levelId, $userId)
    {
        // Retrieve the level and the user
        $level = Level::find($levelId);
        $user = User::find($userId);

        // Check if level exists
        if (!$level) {
            throw new \Exception('Level not found');
        }

        // Calculate percentages
        $referrer1Share = $amount * ($level->referrer_1_percentage / 100);
        $referrer2Share = $amount * ($level->referrer_2_percentage / 100);
        $adminShare = $amount * ($level->admin_percentage / 100);

        // Determine referrers
        $referrer1Id = $user->referred_by_user_id_1; // Assuming the user's referrer relationship is established
        $referrer2Id = $user->referred_by_user_id_2;

        // Update balances
        if ($referrer1Id) {
            // Add share to referrer 1's balance
            $referrer1 = User::find($referrer1Id);
            if ($referrer1) {
                $referrer1->balance += $referrer1Share;
                $referrer1->save();
            } else {
                // If referrer 1 doesn't exist, add to admin balance
                $adminShare += $referrer1Share;
            }
        } else {
            // If there's no referrer 1, add their share to admin balance
            $adminShare += $referrer1Share;
        }

        if ($referrer2Id) {
            // Add share to referrer 2's balance
            $referrer2 = User::find($referrer2Id);
            if ($referrer2) {
                $referrer2->balance += $referrer2Share;
                $referrer2->save();
            } else {
                // If referrer 2 doesn't exist, add to admin balance
                $adminShare += $referrer2Share;
            }
        } else {
            // If there's no referrer 2, add their share to admin balance
            $adminShare += $referrer2Share;
        }

        $defaultAdminId = Setting::where('name', 'default_admin_id')->first()->value ?? 1;
        $admin = User::find($defaultAdminId);
        if ($admin) {
            $admin->balance += $adminShare;
            $admin->save();
        }
    }


    public function adjustRefSort($receiverId)
    {
        $nextSort = User::max('ref_sort') + 1;
        User::where('id', $receiverId)->update(['ref_sort' => $nextSort]);
    }


    public function getOrAssignReceiver($userId, $amount)
    {
        $user = User::find($userId);

        // $existingAssignment = $user->assignedReceivers()
        //     ->wherePivot('payment_status', 'pending')
        //     ->wherePivot('expires_at', '>', now())
        //     ->first();

        // if ($existingAssignment) {
        //     return $existingAssignment;
        // }

        $taskRequired = Setting::where('name', 'task_percentage')->first()->value ?? 0.70;
        $refRequired = Setting::where('name', 'ref_percentage')->first()->value ?? 0.30;

        $receiver = User::where('ref_balance', '>=', $refRequired)
                        ->where('task_balance', '>=', $taskRequired)
                        ->whereDoesntHave('receiverAssignments', function ($query) {
                            $query->where('payment_status', 'pending');
                        })
                        ->orderBy('ref_sort', 'asc')
                        ->first();

        if ($receiver) {
            // $user->assignedReceivers()->attach($receiver->id, [
            //     'expires_at' => now()->addMinutes(30),
            //     'payment_status' => 'pending',
            // ]);

            return $receiver;
        }

        return null;
    }


    public function adjustBalance($userId, $amount)
    {

        //if admin ie user id is 1
        if($userId == 1){
            $admin = User::find(1);
            $admin->task_balance += $amount;
            $admin->save();
            return;
        }else{
            $taskPer = Setting::where('name', 'task_percentage')->first()->value ?? 0.70;
            $refPer = Setting::where('name', 'ref_percentage')->first()->value ?? 0.30;
            $user = User::find($userId);
            $user->task_balance -= $amount * $taskPer;
            $user->ref_balance -= $amount * $refPer;
            $user->save();
        }
    }





}
