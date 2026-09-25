<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels;

use HoceineEl\WhatsAppAgent\Channels\Contracts\WhatsAppGateway;
use HoceineEl\WhatsAppAgent\Channels\Drivers\CloudApiGateway;
use HoceineEl\WhatsAppAgent\Channels\Drivers\EvolutionGateway;
use HoceineEl\WhatsAppAgent\Channels\Drivers\SimulatorGateway;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;

class WhatsAppManager
{
    public function for(AgentOwner $owner): WhatsAppGateway
    {
        return $this->driver($owner->whatsapp_driver);
    }

    public function driver(WhatsAppDriver $driver): WhatsAppGateway
    {
        return match ($driver) {
            WhatsAppDriver::Cloud => app(CloudApiGateway::class),
            WhatsAppDriver::Evolution => app(EvolutionGateway::class),
            WhatsAppDriver::Simulator => app(SimulatorGateway::class),
        };
    }
}
