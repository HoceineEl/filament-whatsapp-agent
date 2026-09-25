<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent;

use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AgentContext
{
    /** @var list<string> */
    public array $toolsUsed = [];

    /** @var array<string, list<int|string>> */
    public array $touchedRecords = [];

    public bool $handedOff = false;

    public bool $replyByVoice = false;

    public bool $mayBePersonal = false;

    public function __construct(
        public readonly AgentOwner&Model $owner,
        public readonly Model $contact,
        public readonly Model $conversation,
    ) {}

    public static function for(Model $conversation): self
    {
        $conversation->loadMissing('owner', 'contact');

        return new self($conversation->owner, $conversation->contact, $conversation);
    }

    public function locale(): string
    {
        return $this->contact->locale ?? $this->owner->agentLocale();
    }

    /**
     * Records a model a tool created or changed (a booking, an order) so the reply keeps a link to it.
     */
    public function touched(Model $record): void
    {
        $this->touchedRecords[Str::plural(Str::snake(class_basename($record)))][] = $record->getKey();
    }
}
