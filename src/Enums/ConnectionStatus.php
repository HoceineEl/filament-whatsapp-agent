<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConnectionStatus: string implements HasColor, HasLabel
{
    case Disconnected = 'disconnected';
    case Connecting = 'connecting';
    case Connected = 'connected';

    public function getLabel(): string
    {
        return __("whatsapp-agent::whatsapp.connection.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Disconnected => 'danger',
            self::Connecting => 'warning',
            self::Connected => 'success',
        };
    }

    public static function fromEvolutionState(string $state): self
    {
        return match ($state) {
            'open' => self::Connected,
            'connecting' => self::Connecting,
            default => self::Disconnected,
        };
    }
}
