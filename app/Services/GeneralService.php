<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;

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

    public function rewardRefUser($id, $prize){
        $user = User::find($id);
        $user->mdx_balance += $prize;
        $user->referral_code_used += 1;
        $user->save();
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


    public function rewardPoster(Post $post, string $interactionType)
    {
        $user = $post->user;
        $rewardAmount = 0;

        switch ($interactionType) {
            case 'comment':
                $rewardAmount = 0.0010;
                break;
            case 'like':
                $rewardAmount = 0.0005;
                break;
            case 'dislike':
                $rewardAmount = -0.0002;
                break;
            default:
                return;
        }

        // Update the user's MDX balance
        $user->mdx_balance += $rewardAmount;
        $user->save();

        ActivityLogger::log('reward', 'reward', 'User rewarded '.$rewardAmount .'MDX for ' . $interactionType, $user->id, ['amount' => $rewardAmount]);
    }

}
