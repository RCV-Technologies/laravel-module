<?php

namespace RCV\Core\Services\Messaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $event;

    public array $payload;

    public array $options;

    public int $tries;

    public int|array $backoff;

    public function __construct(string $event, array $payload = [], array $options = [])
    {
        $this->event = $event;
        $this->payload = $payload;
        $this->options = $options;
        $this->tries = max(1, (int) ($options['tries'] ?? 5));
        $backoff = $options['backoff'] ?? config('core.messaging.backoff', 5);
        $this->backoff = is_int($backoff) || is_array($backoff) ? $backoff : 5;

        if (($options['after_commit'] ?? null) === true) {
            $this->afterCommit();
        } elseif (($options['after_commit'] ?? null) === false) {
            $this->beforeCommit();
        }
    }

    public function handle(): void
    {
        try {
            event($this->event, $this->payload);
        } catch (\Throwable $e) {
            Log::error('MessageBus dispatch failed.', [
                'event' => $this->event,
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
}
