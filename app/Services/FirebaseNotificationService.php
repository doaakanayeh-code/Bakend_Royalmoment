<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\UserDevice;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $firebase = (new Factory)->withServiceAccount(
            base_path(config('firebase.projects.app.credentials'))
        );

        $this->messaging = $firebase->createMessaging();
    }
    public function sendSystemNotification(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ) {
        $tokens = UserDevice::where('user_id', $userId)
            ->pluck('device_token')
            ->map(fn($t) => trim($t))
            ->toArray();

        if (empty($tokens)) {
            Log::info("No device tokens for user {$userId}");
            return;
        }

        Notification::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
            'is_read' => false,
        ]);

        $firebaseNotification = FirebaseNotification::create($title, $body);

        $message = CloudMessage::new()
            ->withAndroidConfig(
                AndroidConfig::fromArray(['priority' => 'high'])
            )
            ->withNotification($firebaseNotification)
            ->withData($data);

        $this->messaging->sendMulticast($message, $tokens);
    }

    // public function sendChatMessage(
    //     int $userId,
    //     array $data
    // ) {
    //     $tokens = UserDevice::where('user_id', $userId)
    //         ->pluck('device_token')
    //         ->map(fn ($t) => trim($t))
    //         ->toArray();

    //     if (empty($tokens)) {
    //         Log::info("No device tokens for user {$userId}");
    //         return;
    //     }

    //     $message = CloudMessage::new()
    //         ->withData(array_merge($data, [
    //             'type' => 'chat',
    //         ]));

    //     $this->messaging->sendMulticast($message, $tokens);
    // }
}
