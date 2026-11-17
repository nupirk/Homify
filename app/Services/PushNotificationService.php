<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationCampaign;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\PartnerProfile;
use App\Models\PartnerBalance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PushNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';
    private const FCM_URL = 'https://fcm.googleapis.com/fcm/send';

    public function __construct()
    {
        // Initialize with environment configurations
    }

    /**
     * Send notification to specific user.
     *
     * @param Notification|null $inAppNotification When provided, the existing
     *        in-app notification record is reused (avoids creating a duplicate
     *        row for the same event). When null (e.g. campaigns), a new record
     *        is created.
     */
    public function sendToUser(User $user, string $title, string $message, array $data = [], ?Notification $inAppNotification = null): bool
    {
        // The in-app record is created first so users without an active
        // device still see the notification inside the app. Push delivery is
        // reported separately below (return false when no device can take it).
        $notification = $inAppNotification ?? $this->createNotification($user, $title, $message, $data);

        $devices = $user->devices()->active()->get()
            ->unique('device_token');

        if ($devices->isEmpty()) {
            Log::warning("No active devices found for user {$user->user_id}");
            return false;
        }

        $success = true;

        foreach ($devices as $device) {
            $result = $this->sendToDevice($device, $title, $message, $data, $notification);
            if (!$result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Send bulk notification using campaign
     */
    public function sendCampaign(NotificationCampaign $campaign, array $templateVariables = []): bool
    {
        if (!$campaign->canBeSent()) {
            Log::warning("Campaign {$campaign->campaign_id} cannot be sent");
            return false;
        }

        $campaign->update(['status' => 'sending']);

        try {
            $recipients = $this->getTargetUsers($campaign->target_type, $campaign->target_criteria);
            $campaign->update(['total_recipients' => $recipients->count()]);

            $successCount = 0;
            $failCount = 0;

            foreach ($recipients as $user) {
                // Generate user-specific template variables
                $userSpecificVariables = array_merge($templateVariables, [
                    'customer_name' => $user->first_name . ' ' . $user->last_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'user_id' => $user->user_id,
                ]);

                $title = $campaign->template->renderTitle($userSpecificVariables);
                $message = $campaign->template->renderMessage($userSpecificVariables);

                if ($this->sendToUser($user, $title, $message, ['campaign_id' => $campaign->campaign_id])) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            $campaign->update([
                'status' => 'completed',
                'sent_at' => now(),
                'successful_sends' => $successCount,
                'failed_sends' => $failCount
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Campaign sending failed: " . $e->getMessage());
            $campaign->update(['status' => 'failed']);
            return false;
        }
    }

    /**
     * Send notification to device
     */
    private function sendToDevice(UserDevice $device, string $title, string $message, array $data, Notification $notification): bool
    {
        $log = NotificationLog::create([
            'user_id' => $device->user_id,
            'device_id' => $device->device_id,
            'notification_id' => $notification->notification_id,
            'platform' => $device->platform,
            'device_token' => $device->device_token,
            'status' => 'pending'
        ]);

        try {
            if ($device->isIOS() || $device->isAndroid()) {
                $result = $this->sendExpoPushNotification($device->device_token, $title, $message, $data);
            } else {
                // Web push notifications could be implemented here
                $result = false;
            }

            if ($result) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now()
                ]);
                $device->markAsUsed();
                return true;
            } else {
                $log->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to send push notification'
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Push notification failed: " . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send Expo push notification
     */
    private function sendExpoPushNotification(string $token, string $title, string $message, array $data): bool
    {
        try {
            // Bound the call: without a timeout a hung Expo request blocks the
            // whole (synchronous) booking/webhook request that triggered it.
            // Retry only when the request never reached Expo (connection
            // error), so a delivered push is never sent twice.
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->retry(2, 300, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException)
                ->post(self::EXPO_PUSH_URL, [
                    'to' => $token,
                    'title' => $title,
                    'body' => $message,
                    'data' => $data,
                    'sound' => 'default',
                    'priority' => 'high'
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return isset($responseData['data']) && !isset($responseData['data'][0]['details']);
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Expo push notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get target users based on campaign criteria
     */
    private function getTargetUsers(string $targetType, ?array $criteria): Collection
    {
        switch ($targetType) {
            case 'all_users':
                return User::where('is_active', true)->get();

            case 'all_customers':
                return User::where('user_type', 'customer')
                    ->where('is_active', true)
                    ->get();

            case 'all_partners':
                return User::where('user_type', 'partner')
                    ->where('is_active', true)
                    ->get();

            case 'partners_with_negative_balance':
                return User::where('user_type', 'partner')
                    ->where('is_active', true)
                    ->whereHas('partnerProfile.balance', function ($query) {
                        $query->where('available_balance', '<', 0);
                    })
                    ->get();

            case 'inactive_users':
                return User::where('is_active', true)
                    ->where('last_login_at', '<', now()->subDays(30))
                    ->get();

            case 'new_users':
                return User::where('is_active', true)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->get();

            case 'specific_users':
                if (isset($criteria['user_ids'])) {
                    return User::whereIn('user_id', $criteria['user_ids'])
                        ->where('is_active', true)
                        ->get();
                }
                return collect();

            case 'custom_criteria':
                return $this->getCustomTargetUsers($criteria);

            default:
                return collect();
        }
    }

    /**
     * Get users based on custom criteria
     */
    private function getCustomTargetUsers(?array $criteria): Collection
    {
        if (!$criteria) {
            return collect();
        }

        $query = User::where('is_active', true);

        if (isset($criteria['user_type'])) {
            $query->where('user_type', $criteria['user_type']);
        }

        if (isset($criteria['city'])) {
            $query->where('city', $criteria['city']);
        }

        if (isset($criteria['created_after'])) {
            $query->where('created_at', '>=', $criteria['created_after']);
        }

        if (isset($criteria['created_before'])) {
            $query->where('created_at', '<=', $criteria['created_before']);
        }

        if (isset($criteria['last_login_after'])) {
            $query->where('last_login_at', '>=', $criteria['last_login_after']);
        }

        if (isset($criteria['last_login_before'])) {
            $query->where('last_login_at', '<=', $criteria['last_login_before']);
        }

        return $query->get();
    }

    /**
     * Create notification record
     */
    private function createNotification(User $user, string $title, string $message, array $data): Notification
    {
        return Notification::create([
            'user_id' => $user->user_id,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'notification_type' => NotificationService::mapType($data['type'] ?? 'info'),
            'is_read' => false
        ]);
    }

    /**
     * Quick notification methods for common scenarios
     */
    public function notifyBookingConfirmed(User $customer, $bookingId): bool
    {
        return $this->sendToUser(
            $customer,
            'Booking Confirmed',
            'Your service booking has been confirmed!',
            ['type' => 'booking_confirmed', 'booking_id' => $bookingId]
        );
    }

    public function notifyPartnerAssigned(User $customer, $partnerName, $bookingId): bool
    {
        return $this->sendToUser(
            $customer,
            'Partner Assigned',
            "Your service partner {$partnerName} has been assigned to your booking.",
            ['type' => 'partner_assigned', 'booking_id' => $bookingId]
        );
    }

    public function notifyPartnerOnWay(User $customer, $partnerName, $estimatedArrival): bool
    {
        return $this->sendToUser(
            $customer,
            'Partner On The Way',
            "{$partnerName} is on the way! Estimated arrival: {$estimatedArrival}",
            ['type' => 'partner_on_way']
        );
    }

    public function notifyServiceCompleted(User $customer, $bookingId): bool
    {
        return $this->sendToUser(
            $customer,
            'Service Completed',
            'Your service has been completed. Please rate your experience!',
            ['type' => 'service_completed', 'booking_id' => $bookingId]
        );
    }

    public function notifyNewJobAssigned(User $partner, $jobId): bool
    {
        return $this->sendToUser(
            $partner,
            'New Job Available',
            'You have been assigned a new service job. Check your schedule!',
            ['type' => 'new_job', 'job_id' => $jobId]
        );
    }

    public function notifyPaymentReceived(User $partner, $amount): bool
    {
        return $this->sendToUser(
            $partner,
            'Payment Received',
            "You have received Rs. {$amount} in your account.",
            ['type' => 'payment_received', 'amount' => $amount]
        );
    }
}
