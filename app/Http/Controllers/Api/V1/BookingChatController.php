<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BookingMessage;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * In-booking chat (customer <-> assigned partner).
 *
 * Participants are strictly the order owner and the partner(s) assigned to
 * any service request of that order - everyone else gets 404. Transport is
 * polling (after-cursor + read receipts); there is no websocket layer.
 */
class BookingChatController extends Controller
{
    private const MAX_BODY = 1000;
    private const PAGE_SIZE = 200;
    /**
     * How long "this reader is in the thread" stays true. The open screen
     * polls every 5s, so this only has to outlive one poll - when the reader
     * leaves (or the app is backgrounded) notifications resume within a
     * minute instead of a whole session.
     */
    private const PRESENCE_TTL_SECONDS = 45;
    /** Burst guard: one notification per thread per window, not per message. */
    private const NOTIFY_THROTTLE_SECONDS = 60;

    private function presenceKey(string $orderId, int $userId): string
    {
        return "chat:open:{$orderId}:{$userId}";
    }

    private function markPresent(string $orderId, int $userId): void
    {
        Cache::put(
            $this->presenceKey($orderId, $userId),
            true,
            now()->addSeconds(self::PRESENCE_TTL_SECONDS)
        );
    }

    /** Resolve the order if the current user may participate, else null. */
    private function findOrderFor(Request $request, $orderId): ?Order
    {
        $order = Order::with('serviceRequests.partner.user', 'user')
            ->where('order_id', $orderId)
            ->first();

        if (!$order) {
            return null;
        }

        $userId = $request->user()->user_id;

        if ($order->user_id === $userId) {
            return $order;
        }

        // Partner assigned to any service request of this order.
        if ($order->serviceRequests->contains('partner_id', $userId)) {
            return $order;
        }

        return null;
    }

    /**
     * Shape a message row for the client, resolving the sender's display name.
     * The name matters once an order has had more than one partner: without it
     * every partner bubble looks like the same person.
     */
    private function present(BookingMessage $m, int $viewerId, array $names): array
    {
        return [
            'id' => $m->message_id,
            'sender_id' => $m->sender_id,
            'sender_name' => $names[$m->sender_id] ?? '',
            'is_mine' => $m->sender_id === $viewerId,
            'body' => $m->body,
            'created_at' => $m->created_at?->toIso8601String(),
            'read_at' => $m->read_at?->toIso8601String(),
        ];
    }

    /** Display names for every user who appears in a set of messages. */
    private function senderNames($messages): array
    {
        $ids = $messages->pluck('sender_id')->unique()->filter()->all();

        if (empty($ids)) {
            return [];
        }

        return User::whereIn('user_id', $ids)->get()
            ->mapWithKeys(fn ($u) => [$u->user_id => BookingMessage::displayName($u)])
            ->all();
    }

    /**
     * GET /bookings/{booking}/messages
     *   ?after={id}   - polling: only messages newer than the cursor
     *   ?before={id}  - pagination: the page of messages older than the cursor
     */
    public function index(Request $request, $orderId): JsonResponse
    {
        $order = $this->findOrderFor($request, $orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        $viewerId = $request->user()->user_id;
        // Every poll while focused refreshes presence. Polling stops when the
        // screen loses focus or the app is backgrounded, so the key lapses.
        $this->markPresent($order->order_id, (int) $viewerId);
        $after = $request->query('after');
        $before = $request->query('before');

        $base = BookingMessage::where('order_id', $order->order_id);

        if ($after !== null && ctype_digit((string) $after)) {
            // Newest N since the cursor, returned in ascending order.
            $rows = (clone $base)
                ->where('message_id', '>', (int) $after)
                ->orderBy('message_id')
                ->limit(self::PAGE_SIZE)
                ->get();

            $hasMore = false;
        } elseif ($before !== null && ctype_digit((string) $before)) {
            // Older page: take the last N before the cursor, then reverse so
            // the client always receives ascending order.
            $rows = (clone $base)
                ->where('message_id', '<', (int) $before)
                ->orderByDesc('message_id')
                ->limit(self::PAGE_SIZE)
                ->get()
                ->reverse()
                ->values();

            $hasMore = (clone $base)
                ->where('message_id', '<', $rows->first()?->message_id ?? 0)
                ->exists();
        } else {
            // Initial load: the most recent page, ascending.
            $rows = (clone $base)
                ->orderByDesc('message_id')
                ->limit(self::PAGE_SIZE)
                ->get()
                ->reverse()
                ->values();

            $hasMore = (clone $base)
                ->where('message_id', '<', $rows->first()?->message_id ?? 0)
                ->exists();
        }

        $names = $this->senderNames($rows);

        $messages = $rows->map(fn ($m) => $this->present($m, $viewerId, $names))->values();

        $unread = BookingMessage::where('order_id', $order->order_id)
            ->where('sender_id', '!=', $viewerId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $messages,
                'unread' => $unread,
                'has_more' => $hasMore,
                'order_status' => $order->status,
            ],
        ]);
    }

    /** POST /bookings/{booking}/messages */
    public function store(Request $request, $orderId): JsonResponse
    {
        $order = $this->findOrderFor($request, $orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:1|max:' . self::MAX_BODY,
            // Optional idempotency key supplied by the client so a retried or
            // double-tapped send resolves to the same message.
            'client_message_id' => 'nullable|string|max:64',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $senderId = $request->user()->user_id;
        $clientMessageId = $request->input('client_message_id');
        $body = trim($request->input('body'));

        // Idempotency: a message with this key from this sender already exists.
        if ($clientMessageId) {
            $existing = BookingMessage::where('order_id', $order->order_id)
                ->where('sender_id', $senderId)
                ->where('client_message_id', $clientMessageId)
                ->first();

            if ($existing) {
                $names = $this->senderNames(collect([$existing]));

                return response()->json([
                    'success' => true,
                    'data' => $this->present($existing, $senderId, $names),
                ], 200);
            }
        }

        // created_at is not managed by Eloquent here ($timestamps = false), so
        // set it explicitly - otherwise the message's timestamp renders null.
        $message = BookingMessage::create([
            'order_id' => $order->order_id,
            'sender_id' => $senderId,
            'client_message_id' => $clientMessageId,
            'body' => $body,
            'created_at' => now(),
        ]);

        $this->notifyRecipient($order, $request->user(), $message->body);

        $names = $this->senderNames(collect([$message]));

        return response()->json([
            'success' => true,
            'data' => $this->present($message, $senderId, $names),
        ], 201);
    }

    /** POST /bookings/{booking}/messages/read - mark the other side's messages read. */
    public function markRead(Request $request, $orderId): JsonResponse
    {
        $order = $this->findOrderFor($request, $orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        $updated = BookingMessage::where('order_id', $order->order_id)
            ->where('sender_id', '!=', $request->user()->user_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->markPresent($order->order_id, (int) $request->user()->user_id);

        return response()->json([
            'success' => true,
            'data' => ['marked' => $updated],
        ]);
    }

    /**
     * GET /bookings/chat/unread
     *
     * Unread chat counts per order for the current user, so a job/schedule list
     * can show a new-message badge without opening every thread.
     */
    public function unreadCounts(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        $orderIds = Order::where('user_id', $userId)
            ->pluck('order_id')
            ->merge(ServiceRequest::where('partner_id', $userId)->pluck('order_id'))
            ->unique()
            ->values();

        $counts = BookingMessage::whereIn('order_id', $orderIds)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->selectRaw('order_id, count(*) as total')
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        return response()->json([
            'success' => true,
            'data' => ['counts' => $counts],
        ]);
    }

    /** Best-effort notification (in-app row + Expo push) to the other participant. */
    private function notifyRecipient(Order $order, User $sender, string $body): void
    {
        $isCustomer = $sender->user_id === $order->user_id;

        if ($isCustomer) {
            // Customer wrote -> notify every partner assigned to this order.
            $partnerIds = $order->serviceRequests->pluck('partner_id')->filter()->unique();
            $recipients = User::whereIn('user_id', $partnerIds)->get();
            $senderName = BookingMessage::displayName($sender) ?: 'Customer';
        } else {
            // Partner wrote -> notify the customer.
            $recipients = User::where('user_id', $order->user_id)->get();
            $senderName = BookingMessage::displayName($sender) ?: 'Partner';
        }

        $snippet = mb_substr($body, 0, 80);

        foreach ($recipients as $recipient) {
            // Never notify the sender about their own message.
            if ((int) $recipient->user_id === (int) $sender->user_id) {
                continue;
            }

            // The reader is looking at this thread right now: an in-app row and
            // an Expo push for a message already on screen is pure noise (and
            // a push costs money per call).
            if (Cache::has($this->presenceKey($order->order_id, (int) $recipient->user_id))) {
                continue;
            }

            // Burst guard: a rapid exchange must not fan out one push per
            // message. One notification per thread per window is enough to
            // pull the recipient back into the conversation.
            $throttleKey = "chat:notified:{$order->order_id}:{$recipient->user_id}";
            if (Cache::has($throttleKey)) {
                continue;
            }
            Cache::put($throttleKey, true, now()->addSeconds(self::NOTIFY_THROTTLE_SECONDS));

            NotificationService::sendWithPush(
                (int) $recipient->user_id,
                'chat_message',
                'New message · Order #' . strtoupper(substr($order->order_id, -8)),
                $senderName . ': ' . $snippet,
                [
                    'type' => 'chat_message',
                    'order_id' => $order->order_id,
                    'sender_name' => $senderName,
                    'body_preview' => $snippet,
                    'to_role' => $isCustomer ? 'partner' : 'customer',
                ]
            );
        }
    }
}
