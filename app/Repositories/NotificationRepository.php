<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationRepository
{
    /**
     * Create a new notification record.
     */
    public function create(array $data): Notification
    {
        return Notification::create($data);
    }

    /**
     * Find a notification by ID.
     */
    public function find(int $id): ?Notification
    {
        return Notification::find($id);
    }

    /**
     * Mark notification as processed (status: sent, set sent_at).
     */
    public function markAsSent(int $id): void
    {
        Notification::where('id', $id)
            ->update([
                'status'  => 'sent',
                'sent_at' => Carbon::now(),
            ]);
    }

    /**
     * Mark notification as failed (increment attempts).
     */
    public function markAsFailed(int $id): void
    {
        $notification = Notification::find($id);
        if ($notification) {
            $notification->increment('attempts');
            $notification->status = 'failed';
            $notification->save();
        }
    }

    /**
     * Fetch recent notifications for a given user (cached logic lives in service layer).
     */
    public function fetchRecentByUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Notification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Total notification count (all statuses or only sent) over a time window (e.g., last hour).
     */
    public function countByUserAndTimeframe(int $userId, Carbon $from, Carbon $to): int
    {
        return Notification::where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    /**
     * Get summary of total notifications sent overall or per user.
     */
    public function summary(int $userId = null): array
    {
        $query = Notification::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $total = $query->count();
        $sent  = (clone $query)->where('status', 'sent')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $processing = (clone $query)->where('status', 'processing')->count();

        return [
            'total'      => $total,
            'sent'       => $sent,
            'failed'     => $failed,
            'pending'    => $pending,
            'processing' => $processing,
        ];
    }
}
