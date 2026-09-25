<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Messaging;

use HoceineEl\WhatsAppAgent\Channels\Drivers\EvolutionGateway;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\ConnectionStatus;
use HoceineEl\WhatsAppAgent\Enums\PersonalSource;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Model;

class EvolutionConnection
{
    public function __construct(private readonly EvolutionGateway $gateway) {}

    /**
     * @return array{base64: ?string, pairing_code: ?string}
     */
    public function start(AgentOwner $owner, ?string $pairingNumber = null): array
    {
        $this->gateway->ensureInstance($owner);

        $code = $this->gateway->connect($owner->refresh(), $pairingNumber);

        $owner->update(['whatsapp_status' => ConnectionStatus::Connecting]);

        return $code;
    }

    /**
     * @return array{base64: ?string, pairing_code: ?string}
     */
    public function freshCode(AgentOwner $owner): array
    {
        return $this->gateway->connect($owner);
    }

    public function refresh(AgentOwner $owner): ConnectionStatus
    {
        $status = rescue(fn (): ConnectionStatus => $this->gateway->connectionState($owner), ConnectionStatus::Disconnected, report: false);

        $number = $owner->whatsapp_number;

        if ($status === ConnectionStatus::Connected) {
            $number = rescue(fn (): ?string => $this->gateway->linkedNumber($owner), null, report: false) ?? $number;
        }

        $owner->update(['whatsapp_status' => $status, 'whatsapp_number' => $number]);

        return $status;
    }

    public function disconnect(AgentOwner $owner): void
    {
        rescue(fn () => $this->gateway->logout($owner), report: false);

        $owner->update(['whatsapp_status' => ConnectionStatus::Disconnected, 'whatsapp_number' => null]);
    }

    /**
     * Marks everyone already chatting with this number as personal, except contacts who already did business with you.
     */
    public function importPersonalChats(AgentOwner $owner): int
    {
        return collect($this->gateway->chatPhones($owner))
            ->map(fn (string $phone): Model => WhatsAppAgent::contactModel()::query()->forOwner($owner)->withPhone($phone)->first()
                ?? new (WhatsAppAgent::contactModel())([WhatsAppAgent::ownerKey() => $owner->getKey(), 'phone' => WhatsAppAgent::contactModel()::normalizePhone($phone)]))
            ->reject(fn (Model $customer): bool => $customer->isPersonal() || ($customer->exists && WhatsAppAgent::hasHistory($customer)))
            ->each(fn (Model $customer) => $customer->markPersonal(PersonalSource::ExistingChat))
            ->count();
    }

    public function serverVersion(AgentOwner $owner): string
    {
        return $this->gateway->serverVersion($owner);
    }
}
