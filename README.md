1. Overview
This repository contains two projects:

Laravel Notification API (located in laravel-notification-api/):

Exposes RESTful endpoints for publishing notifications, fetching recent notifications, and retrieving a summary.

Stores notification requests in a MySQL (or other) database.

Publishes messages to Redis Pub/Sub for downstream consumption.

Implements per‐user rate limiting (10 notifications/hour) and in‐memory caching for read endpoints.

Fastify + TypeScript Notification Microservice (located in node-notification-microservice/):

Subscribes to the Redis Pub/Sub channel for newly published notifications.

Simulates sending notifications by logging to console.

Updates Laravel API with status=sent or status=failed via HTTP callback, using exponential backoff retry logic.

2. Setup Instructions
2.1 Common Prerequisites
Docker (for Redis) or a standalone Redis instance accessible at REDIS_HOST and REDIS_PORT.

MySQL or any supported DB for Laravel. In this example, we’ll use MySQL.

PHP 8.1+, Composer, and Node.js 16+.

2.2 Laravel Notification API Setup
Navigate to the Laravel folder
cd laravel-notification-api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

php artisan serve --port=8000
The API will run at http://localhost:8000.

I m direclty using redis-server.exe

2.3 Node Notification Microservice Setup
Open a new terminal and navigate to the Node service

cd node-notification-microservice


cp .env.example .env
Set LARAVEL_API_BASE_URL=http://localhost:8000/api (or wherever Laravel is running).

Ensure REDIS_HOST/REDIS_PORT match your Redis instance.

Adjust MAX_RETRY_ATTEMPTS (default 3).

Install dependencies


npm install


npx tsc
npm start


You should see:
pgsql



npm run start
3. Folder Structure & Architecture
3.1 Laravel API (laravel-notification-api/)
app/Models/Notification.php
Eloquent model for notifications table.

app/Repositories/NotificationRepository.php
Encapsulates all DB queries. Implements methods for create, fetch, update status, and summary.
Pattern: Repository Pattern.

app/Services/NotificationService.php

Enforces business logic (rate limiting, caching).

Publishes to Redis (Redis::publish(…)).

Implements publish(), fetchRecent(), and getSummary().
Pattern: Service Layer, Dependency Injection (injects Repository).

app/Http/Controllers/NotificationController.php

Exposes these endpoints:

POST /api/notifications/publish → calls NotificationService::publish().

GET /api/notifications/recent → calls NotificationService::fetchRecent().

GET /api/notifications/summary → calls NotificationService::getSummary().

PUT /api/notifications/{id}/status → triggered by microservice callback.

config/notifications.php
Configuration for rate limit and Redis channel.

routes/api.php
Defines the API routes under /api/notifications.

database/migrations/2025_05_31_000000_create_notifications_table.php
Defines the notifications table schema.

Rate Limiting:

Done in NotificationService::publish():

$countLastHour = $this->repo->countByUserAndTimeframe(...);
If countLastHour >= 10, return error.

You can also apply a middleware (rate.limit.user) if you want to short‐circuit before service. In our final design, the service handles it.

Caching:

fetchRecent() caches paginated results per user page key, TTL 2 minutes.

getSummary() caches summary for 5 minutes.

3.2 Node Microservice (node-notification-microservice/)
src/index.ts

Bootstraps Fastify (only has a health‐check route).

Calls subscribeToNotificationsChannel() on startup.

src/services/notificationConsumerService.ts

Creates a Redis client (ioredis) and subscribes to channel notifications.

On each message:

Parse JSON.

Simulate “send” → console.log().

Simulate random failure (20%) to illustrate retry/failure handling.

If failure, update Laravel with status=failed.

If success, update Laravel with status=sent.

All Laravel callbacks use retryWithExponentialBackoff (max 3 attempts by default).

src/utils/retryHelper.ts

Exponential backoff: base 1 second, doubling each retry, up to maxAttempts.

4. Message Flow (End-to-End)
Client (any client/Front-end)

Sends POST /api/notifications/publish with JSON body:

{
  "user_id": 123,
  "type": "email",
  "payload": {
    "subject": "Hello!",
    "message": "Your order has shipped."
  },
  "scheduled_at": null
}
Laravel Controller

Validates request via PublishNotificationRequest.

Calls NotificationService::publish($data).

Service checks:
a) Rate limit (DB count last hour < 10).
b) Inserts row in notifications table (status=pending).
c) Publishes a message to Redis channel notifications:

{
  "notification_id": 42,
  "user_id": 123,
  "type": "email",
  "payload": { "subject": "Hello!", "message": "Your order has shipped." },
  "scheduled_at": null
}
Service returns the newly created notification → Controller returns HTTP 201 JSON to client.

Redis Pub/Sub

Laravel “Redis::publish('notifications', <message>)” pushes the message onto channel notifications.

Node.js Microservice

Subscribed to channel notifications. Immediately receives the message.

processNotification() is called with that JSON string.

Parse JSON.

Simulate sending (console‐log).

Simulate random failure (20% chance).

If random failure:
a) Attempts retryWithExponentialBackoff → calls Laravel:
PUT /api/notifications/42/status { "status": "failed" }.
b) If Laravel callback succeeds, done. If it fails, it retries with backoff.

If simulated success:
a) Calls Laravel: PUT /api/notifications/42/status { "status": "sent" }, with retry up to 3 attempts.

If even after retries the callback fails, logs an error. Optionally, you could push to a dead‐letter queue.

Laravel API

Receives the PUT /api/notifications/42/status call.

Controller’s updateStatus() validates status field (sent or failed).

Service (via NotificationRepository) updates the record in DB:

If sent, sets status='sent' and sent_at=now().

If failed, increments attempts and sets status='failed'.

5. Failure Handling & Retries
Publish Rate Limit:

If a user already created 10 notifications in the last hour, the publish() call returns an error. The controller returns HTTP 429.

Redis Publish Failure:

If Redis is down, Redis::publish() throws an exception. We let that bubble up so Laravel returns 500. In production, you could wrap in a try/catch and store the message in a “backup queue” or database for later re‐publishing.

Microservice “Send” Simulation Failure:

We simulate random failure. If “send” fails, we update Laravel with status=failed.

We wrap the PUT request in retryWithExponentialBackoff(…), so if Laravel is temporarily unreachable or returns 5xx, we retry up to 3 times (delays: 1 s, 2 s, 4 s). If all attempts fail:

We log an error: “Failed to update Laravel for notification #42 after 3 attempts.”

In a production system, we might push this to a dead‐letter queue or send an alert.

Microservice Callback Failure:

Similar retry logic. If PUT /api/notifications/{id}/status keeps failing, we log and optionally fallback to manual intervention.

6. Caching & Rate Limiting (Laravel)
Rate Limiting (10 notifications/user/hour):

Enforced in NotificationService::publish():

php
Copy
Edit
$countLastHour = $this->repo->countByUserAndTimeframe(...);
if ($countLastHour >= 10) {
    return [ 'success' => false, 'message' => 'Rate limit exceeded…' ];
}
You could also move this into a custom middleware, but centralizing in the service ensures any caller (even if not HTTP) is subject to the same limit.

Caching:

Recent Notifications:

Key: user:{userId}:recent_notifications:page:{page}

TTL: 120 seconds

Cached by NotificationService::fetchRecent().

Summary:

Key (if user specified): user:{userId}:notification_summary.

Key (global): global:notification_summary.

TTL: 300 seconds.

Cached by NotificationService::getSummary().

7. Design Decisions & Extensibility
Layered Architecture:

Controllers: Accept HTTP requests, validate input (FormRequest), call Service, return JSON.

Services: Contain business rules (rate limiting, caching, queueing).

Repositories: Encapsulate all Eloquent queries. If you want to swap out Eloquent for another ORM or raw queries, you only modify the repository.

Models: Standard Eloquent models.

Redis Pub/Sub:

Chosen for its simplicity and built-in support in both Laravel and Node.js.

Alternative: RabbitMQ. If you prefer RabbitMQ, you would:

In Laravel, use a library like php-amqplib or a Laravel RabbitMQ package to publish to an exchange/queue.

In Node.js, use amqplib to consume from that queue.

Redis is sufficient for a mock system and is easier to spin up via Docker.

Retry Logic:

Implemented in microservice with exponential backoff (1s, 2s, 4s).

MAX_RETRY_ATTEMPTS=3 is configurable via .env.

If final retry fails, we log an ERROR. In production, you’d push to a “dead-letter” or Monitoring system.

Extensibility:

New Message Types:

The type field in the notifications table and request can be extended (e.g., in_app, webhook).

Service & Consumer simply treat payload as JSON; you can branch logic based on type.

Additional Consumers:

You could run multiple Node.js instances for horizontal scaling—Redis Pub/Sub will broadcast to all subscribers. To ensure only one consumer processes each notification, switch to Redis Streams or RabbitMQ work queues.

Switch to RabbitMQ:

Minimal changes:

In Laravel, swap Redis::publish(...) with a RabbitMQ publisher to a queue named notifications.

In Node.js, swap ioredis subscription with amqplib consumer from the same queue.

Monitoring & Logging:

Integrate a logging library (e.g., Winston, or push logs to CloudWatch).

Use a dead-letter queue for permanently failed messages.

Rate Limiting Scheme:

Counting rows in DB each time (countByUserAndTimeframe) works at our scale (50-60 req/sec). If DB becomes too large, consider a “sliding window” counter in Redis:

On publish: INCR user:{id}:hourly_count:{YYYYMMDDHH} (expires in 1 hour).

If count > 10, reject.

This moves rate‐limit tracking out of MySQL, reducing load.

Caching:

Using Laravel’s default file cache driver for simplicity. In production, switch to Redis or Memcached for faster performance.

TTLs were chosen conservatively (2 min for paginated fetch, 5 min for summary). You can adjust based on read volume.

8. Sample HTTP Requests
Publish Notification


POST http://localhost:8000/api/notifications/publish
Content-Type: application/json

{
  "user_id": 1,
  "type": "email",
  "payload": { "subject": "Welcome!", "message": "Thanks for signing up." },
  "scheduled_at": null
}
Possible Responses:

201 (Success):

{
  "status": "success",
  "notification": {
    "id": 5,
    "user_id": 1,
    "type": "email",
    "payload": { "subject": "Welcome!", "message": "Thanks for signing up." },
    "scheduled_at": null,
    "status": "pending",
    "attempts": 0,
    "created_at": "2025-05-31T10:00:00Z",
    "updated_at": "2025-05-31T10:00:00Z"
  }
}
429 (Rate limit exceeded):

{
  "status": "error",
  "message": "Rate limit exceeded: max 10 notifications per hour."
}
Fetch Recent Notifications


GET http://localhost:8000/api/notifications/recent?user_id=1&page=1&per_page=10
200:

{
  "status": "success",
  "data": [
    {
      "id": 5,
      "user_id": 1,
      "type": "email",
      "payload": { "subject": "Welcome!", "message": "Thanks for signing up." },
      "scheduled_at": null,
      "status": "sent",
      "attempts": 0,
      "sent_at": "2025-05-31T10:05:00Z",
      "created_at": "2025-05-31T10:00:00Z",
      "updated_at": "2025-05-31T10:05:00Z"
    },
    // …
  ],
  "current_page": 1,
  "per_page": 10,
  "total": 25,
  "last_page": 3
}
Summary (Global or Per User)

Global: GET http://localhost:8000/api/notifications/summary

Per‐User: GET http://localhost:8000/api/notifications/summary?user_id=1

200:

{
  "status": "success",
  "summary": {
    "total": 25,
    "sent": 20,
    "failed": 3,
    "pending": 2,
    "processing": 0
  }
}
Update Status (Called by Microservice)


PUT http://localhost:8000/api/notifications/5/status
Content-Type: application/json

{
  "status": "sent"
}
200:

{
  "status": "success",
  "message": "Notification #5 marked as sent."
}
9. Assumptions
User Authentication:

For this mock task, we assume user_id is passed in the request body or query param. In a real system, you’d authenticate (e.g., Passport, Sanctum) and derive user_id from the JWT/session instead of trusting a passed ID.

Notification Types:

We’ve restricted type to email, sms, or push via validation. Extendable to other types by modifying the rule in PublishNotificationRequest.

Redis vs. RabbitMQ:

We chose Redis Pub/Sub for simplicity. MongoDB or other message brokers are not used.

DB Load for Rate Limit:

Counting rows each time is acceptable for moderate volume. At higher scale, move to a Redis‐based counter with expiry.

Failure Handling:

On microservice callback failure, after MAX_RETRY_ATTEMPTS we log and give up. In prod, you’d add a dead‐letter queue or alert.

Scheduled Notifications:

We store scheduled_at, but we do not enforce “delay” in this mock. If you wanted scheduled sending, you’d queue messages to a delayed queue and have a scheduler or worker pick them up at the right time.

