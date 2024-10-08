<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Level;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use GuzzleHttp\Client;
use App\Models\Post;

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
        return 'TVA'.$timeNow;
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


    //Upload Files to AWS
    public function uploadFile($file, $type, $subtype, $identifier): JsonResponse|string
    {
        try {
            // Determine the resource type based on the file extension
            $resourceType = $this->getResourceType($file);

            // Retrieve AWS credentials from .env file
            $awsAccessKeyId = env('AWS_ACCESS_KEY_ID');
            $awsSecretAccessKey = env('AWS_SECRET_ACCESS_KEY');
            $awsRegion = env('AWS_DEFAULT_REGION', 'us-east-1');

            // Upload file to AWS S3
            $s3 = new S3Client([
                'credentials' => [
                    'key' => $awsAccessKeyId,
                    'secret' => $awsSecretAccessKey,
                ],
                'region' => $awsRegion,
                'version' => 'latest',
            ]);

            $bucketName = env('AWS_BUCKET');
            $filePath = $type . '/' . $subtype . '/' . $type . "-" . $identifier . '.' . $file->getClientOriginalExtension();

            $s3->putObject([
                'Bucket' => $bucketName,
                'Key' => $filePath,
                'SourceFile' => $file->getRealPath(),
                'ContentType' => $file->getMimeType(),
                'ACL' => 'public-read',
            ]);

            // Get the URL of the uploaded file
            $fileUrl = Storage::disk('s3')->url($filePath);

            return $fileUrl;
        } catch (AwsException $e) {
            return response()->json(['error' => 'File upload failed. Please try again.', 'message' => $e->getMessage()], 500);
        }
    }


    private function getResourceType($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // Map common file extensions to AWS S3 content types
        $contentTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        // Default to "application/octet-stream" if the extension is not explicitly mapped
        return $contentTypes[$extension] ?? 'application/octet-stream';
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

        // Update admin balance
        // Assuming you have a method to get the admin user, replace `1` with your admin user ID
        $admin = User::find(1); // Change this to your actual admin ID
        if ($admin) {
            $admin->balance += $adminShare;
            $admin->save();
        }
    }


}
