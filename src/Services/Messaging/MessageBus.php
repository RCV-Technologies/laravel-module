<?php

namespace RCV\Core\Services\Messaging;

use Illuminate\Support\Facades\Bus;
use RCV\Core\Services\Messaging\Jobs\DispatchEventJob;

class MessageBus
{
    public function publish(string $event, array $payload = [], array $options = []): void
    {
        $queue = $options['queue'] ?? config('core.messaging.queue');
        $options['tries'] ??= config('core.messaging.tries', 5);
        $options['backoff'] ??= config('core.messaging.backoff', 5);
        $options['after_commit'] ??= config('core.messaging.after_commit', false);
        $job = new DispatchEventJob($event, $payload, $options);

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        Bus::dispatch($job);
    }
}
