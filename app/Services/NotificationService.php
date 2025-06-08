<?php

namespace App\Services;

use App\Repositories\NotificationRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public NotificationRepository $repo;
    protected int $rateLimitPerHour;
    protected string $redisChannel;

    public function __construct(NotificationRepository $repo)
    {
        $this->repo = $repo;
        $this->rateLimitPerHour = Config::get('notifications.rate_limit_per_hour', 10);
        $this->redisChannel = Config::get('notifications.redis_channel', 'notifications');
    }

    public function publish(array $payload): array
    {
        $userId = $payload['user_id'];

        $now = Carbon::now();
        $oneHourAgo = $now->copy()->subHour();
        $countLastHour = $this->repo->countByUserAndTimeframe($userId, $oneHourAgo, $now);

        if ($countLastHour >= $this->rateLimitPerHour) {
            return [
                'success' => false,
                'message' => 'Rate limit exceeded: max '
                              . $this->rateLimitPerHour
                              . ' notifications per hour.',
            ];
        }


        $notification = $this->repo->create([
            'user_id'      => $userId,
            'type'         => $payload['type'],
            'payload'      => $payload['payload'],
            'scheduled_at' => $payload['scheduled_at'] ?? null,
            'status'       => 'pending',
            'attempts'     => 0,
        ]);


        $message = json_encode([
            'notification_id' => $notification->id,
            'user_id'         => $notification->user_id,
            'type'            => $notification->type,
            'payload'         => $notification->payload,
            'scheduled_at'    => $notification->scheduled_at?->toDateTimeString(),
        ]);

        Redis::publish($this->redisChannel, $message);

        return [
            'success'       => true,
            'notification'  => $notification,
        ];
    }

    /**
     * Fetch recent notifications for a user, with caching (cache key per user, 2 minutes).
     */
    public function fetchRecent(int $userId, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $cacheKey = "user:{$userId}:recent_notifications:page:{$page}";
        $ttlSeconds = 120;

        return Cache::remember(
            $cacheKey,
            $ttlSeconds,
            fn() => $this->repo->fetchRecentByUser($userId, $perPage)
        );
    }

    /**
     * Get summary—cached for 5 minutes
     */
    public function getSummary(int $userId = null): array
    {
        $cacheKey = $userId
            ? "user:{$userId}:notification_summary"
            : "global:notification_summary";
        $ttlSeconds = 300;

        return Cache::remember(
            $cacheKey,
            $ttlSeconds,
            fn() => $this->repo->summary($userId)
        );
    }
}
