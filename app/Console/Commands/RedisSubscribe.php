<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;

class RedisSubscribe extends Command
{
    protected $signature = 'redis:subscribe {channel=notifications}';
    protected $description = 'Subscribe to a Redis channel and listen for messages';

    public function handle()
    {
        // Get Redis configuration
        $config = config('database.redis.default');

        // Display Redis configuration
        $this->info('▶️ [ENV] REDIS_HOST=' . ($config['host'] ?? '127.0.0.1'));
        $this->info('▶️ [ENV] REDIS_PORT=' . ($config['port'] ?? '6379'));
        $this->info('▶️ [ENV] REDIS_DB=' . ($config['database'] ?? '0'));
        $this->info('▶️ [ENV] REDIS_CHANNEL=' . $this->argument('channel'));

        $channel = $this->argument('channel');

        $this->info("▶️ Attempting to subscribe to Redis channel: {$channel}");

        try {
            Redis::connection()->subscribe([$channel], function ($message) use ($channel) {
                $this->info("📨 [{$channel}] received: {$message}");

                // Optional: Add processing logic here
                // $this->processMessage($message);
            });

            $this->info("✅ Successfully subscribed to channel: {$channel}");

        } catch (\Exception $e) {
            $this->error("❌ Error while subscribing: " . $e->getMessage());
        }
    }

    // Optional: Add message processing method
    protected function processMessage($message)
    {
        try {
            $data = json_decode($message, true);
            $this->info("📩 Processing notification #{$data['notification_id']} for user {$data['user_id']}");
            $this->info("    Type: {$data['type']}, Payload: " . json_encode($data['payload']));
        } catch (\Exception $e) {
            $this->error("❌ Failed to process message: " . $e->getMessage());
        }
    }
}
