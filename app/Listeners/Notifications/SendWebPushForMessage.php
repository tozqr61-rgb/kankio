<?php

namespace App\Listeners\Notifications;

use App\Events\MessageSent;
use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendWebPushForMessage implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(MessageSent $event): void
    {
        $message  = $event->message;
        $senderId = $message['sender']['id'] ?? null;

        if (! $senderId) {
            return;
        }

        $vapidPublicKey  = config('services.webpush.public_key');
        $vapidPrivateKey = config('services.webpush.private_key');
        $vapidSubject    = config('services.webpush.subject');

        if (blank($vapidPublicKey) || blank($vapidPrivateKey)) {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject'    => $vapidSubject,
                'publicKey'  => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ];

        $webPush = new WebPush($auth);

        $payload = json_encode([
            'title' => $message['sender']['username'] ?? 'Kankio',
            'body'  => mb_substr($message['content'] ?? 'Yeni mesaj', 0, 160),
            'icon'  => '/icons/icon.svg',
            'badge' => '/icons/icon.svg',
            'tag'   => "room-{$event->roomId}",
            'data'  => ['url' => "/chat/{$event->roomId}"],
        ]);

        $subscriptions = PushSubscription::whereHas('user', function ($q) use ($senderId) {
            $q->where('notifications_enabled', true)
              ->where('id', '!=', $senderId);
        })->get();

        foreach ($subscriptions as $sub) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint'        => $sub->endpoint,
                        'publicKey'       => $sub->public_key,
                        'authToken'       => $sub->auth_token,
                        'contentEncoding' => 'aesgcm',
                    ]),
                    $payload,
                );
            } catch (\Throwable $e) {
                Log::warning('webpush.queue_failed', ['sub_id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $endpoint)->delete();
                } else {
                    Log::warning('webpush.send_failed', [
                        'reason' => $report->getReason(),
                        'endpoint' => mb_substr($endpoint, 0, 80),
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('webpush.listener_failed', ['error' => $exception->getMessage()]);
    }
}
