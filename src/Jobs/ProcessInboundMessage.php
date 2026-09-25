<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Jobs;

use HoceineEl\WhatsAppAgent\Agent\AgentRuntime;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInboundMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [20, 60, 180];

    public int $timeout = 120;

    public function __construct(public Model $message) {}

    public function uniqueId(): string
    {
        return (string) $this->message->getKey();
    }

    public function handle(AgentRuntime $runtime): void
    {
        WhatsAppAgent::within(
            $this->message->owner,
            fn () => $runtime->respond($this->message, finalAttempt: $this->attempts() >= $this->tries),
        );
    }
}
