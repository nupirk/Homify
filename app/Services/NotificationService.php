<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Map semantic notification types to the notification_type_enum values.
     */
    public static function mapType(string $semanticType): string
    {
        return match ($semanticType) {
            'booking_confirmed',
            'booking_updated',
            'booking_cancelled',
            'partner_assigned',
            'partner_on_way',
            'service_started',
            'service_completed',
            'new_job',
            'assignment_expired',
            'chat_message',
            'reassignment' => 'order_update',
            'payment_received',
            'payment_due' => 'payment',
            'admin' => 'warning',
            'promotional' => 'promotion',
            default => 'info',
        };
    }

    /**
     * Create an in-app notification record for a user.
     * Best-effort: failures are logged, never thrown.
     */
    public static function send(int $userId, string $type, string $title, string $message, array $data = []): ?Notification
    {
        try {
            $notificationType = self::mapType($type);

            // Suppress spam: identical notifications (same user, type, title and
            // message) within 10 minutes are duplicates - e.g. a partner being
            // auto-assigned the same job after an assignment timeout cycle.
            $duplicate = Notification::where('user_id', $userId)
                ->where('notification_type', $notificationType)
                ->where('title', $title)
                ->where('message', $message)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->exists();

            if ($duplicate) {
                Log::info('Duplicate notification suppressed', [
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                ]);
                return null;
            }

            $notification = Notification::create([
                'user_id' => $userId,
                'notification_type' => $notificationType,
                'title' => $title,
                'message' => $message,
                'data' => array_merge($data, ['type' => $type]),
                'is_read' => false,
            ]);

            Log::info('Notification created', [
                'user_id' => $userId,
                'type' => $type,
                'notification_id' => $notification->notification_id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::warning('Failed to create notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create in-app notification and attempt a push notification (best-effort).
     */
    public static function sendWithPush(int $userId, string $type, string $title, string $message, array $data = []): ?Notification
    {
        $notification = self::send($userId, $type, $title, $message, $data);

        // `send()` returns null when the 10-minute duplicate-suppression rule
        // fires (or the in-app insert failed). Bail out without pushing and
        // without calling sendToUser(null) - which would re-create a second
        // in-app row, turning the dedupe rule into duplicate spam.
        if ($notification === null) {
            return null;
        }

        // Opt-in: move the outbound Expo call to a queue worker. Requires a
        // running worker; disabled by default so behaviour is unchanged.
        if (config('notifications.queue_push', false)) {
            \App\Jobs\SendPushNotification::dispatch(
                $userId,
                $title,
                $message,
                $data,
                $notification->notification_id
            );

            return $notification;
        }

        try {
            $user = User::find($userId);
            if ($user) {
                app(PushNotificationService::class)->sendToUser($user, $title, $message, $data, $notification);
            }
        } catch (\Exception $e) {
            Log::warning('Push notification attempt failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    /**
     * Notify all admin users.
     */
    public static function sendToAdmins(string $type, string $title, string $message, array $data = []): void
    {
        $adminIds = User::where('user_type', 'admin')->where('is_active', true)->pluck('user_id');

        foreach ($adminIds as $adminId) {
            self::send((int) $adminId, $type, $title, $message, $data);
        }
    }
}
