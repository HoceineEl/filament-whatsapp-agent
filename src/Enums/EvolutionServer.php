<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum EvolutionServer: string implements HasDescription, HasIcon, HasLabel
{
    case Platform = 'platform';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return __("whatsapp-agent::connection.servers.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("whatsapp-agent::connection.servers.{$this->value}.description");
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Platform => Heroicon::OutlinedCloud,
            self::Custom => Heroicon::OutlinedServerStack,
        };
    }
}
