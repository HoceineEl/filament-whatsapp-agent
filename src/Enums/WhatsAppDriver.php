<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum WhatsAppDriver: string implements HasDescription, HasIcon, HasLabel
{
    case Cloud = 'cloud';
    case Evolution = 'evolution';
    case Simulator = 'simulator';

    public function getLabel(): string
    {
        return __("whatsapp-agent::whatsapp.drivers.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("whatsapp-agent::whatsapp.drivers.{$this->value}.description");
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Cloud => Heroicon::OutlinedShieldCheck,
            self::Evolution => Heroicon::OutlinedQrCode,
            self::Simulator => Heroicon::OutlinedBeaker,
        };
    }

    public function supportsNativeButtons(): bool
    {
        return $this === self::Cloud;
    }

    public function requiresTemplatesOutsideWindow(): bool
    {
        return $this === self::Cloud;
    }
}
