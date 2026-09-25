<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Screening;

use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0)]
#[Timeout(20)]
class MessageScreenerAgent implements Agent, HasProviderOptions
{
    use Promptable;

    public function __construct(public readonly AgentOwner $owner, public readonly bool $knownContact = false) {}

    /**
     * @return array<string, string>
     */
    public function provider(): array
    {
        return ['gemini' => (string) config('whatsapp-agent.screening.model')];
    }

    public function providerOptions(Lab|string $provider): array
    {
        return ['thinking_level' => 'minimal'];
    }

    public function instructions(): Stringable|string
    {
        $intro = "The WhatsApp number of \"{$this->owner->name}\" ({$this->owner->type->getLabel()}) is also the owner's personal phone. ";

        return $intro.($this->knownContact
            ? 'This sender is one of the owner\'s personal contacts (family or friend). Answer CUSTOMER only if the message is about the business: '
                .'its products, services, prices, orders, bookings, location or opening hours. Greetings, small talk and anything private are PERSONAL.'
            : 'Classify the first message from an unknown sender. Answer CUSTOMER if it could be about the business '
                .'(greeting, question, price, booking, location, service, complaint) and PERSONAL only if it is clearly private '
                .'(family, friends, personal plans, jokes, forwarded chain messages). When unsure answer CUSTOMER.')
            .' Reply with one word.';
    }

    public function isPersonal(string $message): bool
    {
        return str_contains(strtoupper((string) $this->prompt(mb_substr($message, 0, 500))->text), 'PERSONAL');
    }
}
