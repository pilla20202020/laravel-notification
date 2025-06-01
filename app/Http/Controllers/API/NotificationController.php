<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublishNotificationRequest;
use App\Http\Requests\FetchRecentNotificationsRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $service;

    public function __construct(NotificationService $service)
    {
        $this->service = $service;
    }

    /**
     * Publish Notification
     * POST /api/notifications/publish
     */
    public function publish(PublishNotificationRequest $request): JsonResponse
    {
        $data = $request->only(['user_id', 'type', 'payload', 'scheduled_at']);
        $result = $this->service->publish($data);

        if (! $result['success']) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'],
            ], 429);
        }

        return response()->json([
            'status'       => 'success',
            'notification' => $result['notification'],
        ], 201);
    }

    /**
     * Fetch recent notifications (paginated)
     * GET /api/notifications/recent?user_id={id}&page={}&per_page={}
     */
    public function recent(FetchRecentNotificationsRequest $request): JsonResponse
    {
        $userId  = (int) $request->input('user_id');
        $page    = (int) ($request->input('page', 1));
        $perPage = (int) ($request->input('per_page', 15));

        $paginated = $this->service->fetchRecent($userId, $page, $perPage);

        return response()->json([
            'status'        => 'success',
            'data'          => $paginated->items(),
            'current_page'  => $paginated->currentPage(),
            'per_page'      => $paginated->perPage(),
            'total'         => $paginated->total(),
            'last_page'     => $paginated->lastPage(),
        ]);
    }


    /**
     * Notification summary: total, sent, failed, pending, processing
     * GET /api/notifications/summary?user_id={optional}
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        if ($userId !== null) {
            $userId = (int) $userId;
        }

        $summary = $this->service->getSummary($userId);

        return response()->json([
            'status'  => 'success',
            'summary' => $summary,
        ]);
    }

    /**
     * Update notification status to 'sent' or 'failed' (used by microservice callback).
     * PUT /api/notifications/{id}/status
     *
     * Body: { "status": "sent" } or { "status": "failed" }
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:sent,failed'],
        ]);

        $status = $request->input('status');

        if ($status === 'sent') {
            $this->service->repo->markAsSent($id);
        } else {
            $this->service->repo->markAsFailed($id);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Notification #{$id} marked as {$status}.",
        ]);
    }
}
