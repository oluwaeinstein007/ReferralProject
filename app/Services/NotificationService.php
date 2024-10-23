<?php

namespace App\Services;

use App\Models\Notification as ModelsNotification;
use Exception;
use GuzzleHttp\Client;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Str;
use App\Notifications\SlackNotification;
use App\Notifications\EmailNotification;
use App\Notifications\OTPNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Mockery\Matcher\Not;

class NotificationService
{
    /**
     * Perform some general service logic.
     *
     * @param mixed $data
     * @return mixed
     */

    // public function sendNotification($user, $type, $subType, $title, $body, $link = null, $tvaId = null){
    //     $notification = new Notification();
    //     $notification->user_uuid = $user->user_uuid;
    //     $notification->type = $type;
    //     $notification->sub_type = $subType;
    //     $notification->title = $title;
    //     $notification->body = $body;
    //     $notification->link = $link;
    //     $notification->tva_id = $tvaId;
    //     $notification->save();
    //     return $notification;
    // }


    // public function sendNotificationToAll($type, $subType, $title, $body, $link = null, $tvaId = null){
    //     $users = User::all();
    //     foreach($users as $user){
    //         $notification = new Notification();
    //         $notification->user_uuid = $user->user_uuid;
    //         $notification->type = $type;
    //         $notification->sub_type = $subType;
    //         $notification->title = $title;
    //         $notification->body = $body;
    //         $notification->link = $link;
    //         $notification->tva_id = $tvaId;
    //         $notification->save();
    //     }
    // }
    // public function getMessage($message, $type, $subType){
    //     //subtype can be, booking, update, cancel, meeting setup, etc, type can be error, general, notification, visa

    // }

    public function notifyAdminSlack($type, $subtype, $link = null){
        $link = $link ?? '/';
        $url = env('ADMIN_FRONTEND_URL') . $link;
        $message = $this->getMessage($type, $subtype, $url);
        $this->sendSlackNotification($message, $type, $url);
    }


    public function getMessage($type, $subType, $link = null){
        $messages = [
            'error' => [
                'booking' => "An error occurred while processing a booking.",
                'update' => "An error occurred while updating information.",
                'cancel' => "An error occurred while canceling a booking.",
                'meeting setup' => "An error occurred while setting up a meeting.",
                'mail' => "An error occurred while sending an email.",
                'default' => "An error occurred."
            ],
            'general' => [
                'booking' => "A new booking has been made.",
                'update' => "Information has been updated.",
                'cancel' => "A booking has been canceled.",
                'meeting setup' => "A meeting has been set up.",
                'default' => "A general message."
            ],
            'hotel' => [
                'approval' => "Hotel approval notification.",
                'rejection' => "Hotel rejection notification.",
                'booking' => "Hotel: A new booking has been made.",
                'update' => "Hotel: Information has been updated.",
                'cancel' => "Hotel: A booking has been canceled.",
                'meeting setup' => "Notification: A meeting has been set up.",
                'default' => "A hotel-related message."
            ],
            'visa' => [
                'approval' => "Visa approval notification.",
                'rejection' => "Visa rejection notification.",
                'booking' => "Visa: A new booking has been made.",
                'update' => "Visa: Information has been updated.",
                'cancel' => "Visa: A booking has been canceled.",
                'meeting setup' => "Notification: A meeting has been set up.",
                'default' => "A visa-related message."
            ],
            'default' => "A default message."
        ];

        // Retrieve the message based on type and subtype, or use the default message if not found
        $message = $messages[$type][$subType] ?? $messages[$type]['default'] ?? $messages['default'];
        // $message .= " url: $link";
        return $message;
    }


    public function getSlackChannel($type){
        switch($type){
            case 'error':
                return env('SLACK_ERROR_WEBHOOK_URL');
            case 'hotel':
                return env('SLACK_HOTEL_WEBHOOK_URL');
            case 'visa':
                return env('SLACK_VISA_WEBHOOK_URL');
            default:
                return env('SLACK_GENERAL_WEBHOOK_URL');
        }
    }


    public function sendSlackNotification($message, $type, $url = null){
        $slackChannel = $this->getSlackChannel($type);
        Notification::route('slack', $slackChannel)
            ->notify(new SlackNotification($message, $url));
    }


    public function userNotification($user, $type, $subType, $title, $body, $is_email = true, $link = null, $actionText = null){
        if($link != null){
            $link = env('USER_FRONTEND_URL') . $link;
        }
        $notification = $this->storeNotification($user, $type, $subType, $title, $body, $link, $actionText);
        if($is_email){
            $this->sendEmailNotification($user,$notification);
        }
        // $this->sendEmailNotification($user,$notification);
    }


    public function storeNotification($user, $type, $subType, $title, $body, $link = null, $actionText = null){
        $notification = ModelsNotification::create([
            'user_id' => $user['id'],
            'type' => $type,
            'sub_type' => $subType,
            'title' => $title,
            'body' => $body,
            'link' => $link,
        ]);
        //merge actionText
        $notification['actionText'] = $actionText;

        return $notification;
    }


    public function sendEmailNotification($user,$notification) {

        $mailDetails = [
            'greeting' => 'Hello!',
            'recipientName' => $user['full_name'] ?? 'There',
            'subject' => $notification['title'],
            'recipientEmail' => $user['email'],
            'intro' => $notification['body'],
            'actionText' => $notification['actionText'] ?? 'Notification Action',
            'actionUrl' => $notification['link'],
            'outro' => 'Thank you for choosing Maldorini!'
        ];

        Notification::route('mail', $mailDetails['recipientEmail'])
            ->notify(new EmailNotification($mailDetails));
    }

    public function sendOTPNotification($user,$notification) {

        $mailDetails = [
            'greeting' => 'Hello!',
            'recipientName' => $user['full_name'] ?? 'There',
            'subject' => $notification['title'],
            'recipientEmail' => $user['email'],
            'otp' => $notification['otp'],
            // 'intro' => $notification['body'],
            // 'actionText' => $notification['actionText'] ?? 'Notification Action',
            // 'actionUrl' => $notification['link'],
            'outro' => 'Thank you for choosing Maldorini!'
        ];

        Notification::route('mail', $mailDetails['recipientEmail'])
            ->notify(new OTPNotification($mailDetails));
    }


}
