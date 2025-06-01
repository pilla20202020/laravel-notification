<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class PublishNotificationJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    private array $messagePayload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $messagePayload)
    {
        $this->messagePayload = $messagePayload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $channel = config('notifications.redis_channel', 'notifications');
        Redis::publish($channel, json_encode($this->messagePayload));
    }
}
