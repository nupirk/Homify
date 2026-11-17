<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers the push half of a notification off-request.
 *
 * Only the outbound Expo call is queued — the in-app Notification row is
 * written synchronously by NotificationService so the feed is never delayed
 * by (or lost to) a queue backlog.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public array $data = [],
        public ?int $notificationId = null,
    ) {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        $notification = $this->notificationId
            ? Notification::find($this->notificationId)
            : null;

        try {
            app(PushNotificationService::class)->sendToUser(
                $user,
                $this->title,
                $this->message,
                $this->data,
                $notification
            );
        } catch (\Throwable $e) {
            Log::warning('Queued push notification failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
