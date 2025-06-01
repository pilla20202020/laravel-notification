<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RedisSubscribe extends Command
{
    protected $signature = 'redis:subscribe {channel=notifications_channel}';
    protected $description = 'Subscribe to a Redis channel and listen for messages';

    public function handle()
    {
        $channel = $this->argument('channel') ?? 'notifications_channel';

        $this->info("Subscribing to Redis channel: {$channel}");

        try {
            Redis::connection()->subscribe([$channel], function ($message) use ($channel) {
                $this->info("Received message on channel [{$channel}]: {$message}");
            });
        } catch (\Exception $e) {
            $this->error("Error while subscribing: " . $e->getMessage());
        }
    }

}
