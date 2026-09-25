<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ConversationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Bot = 'bot';
    case NeedsHuman = 'needs_human';
    case Human = 'human';

    public function getLabel(): string
    {
        return __("whatsapp-agent::conversations.statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bot => 'success',
            self::NeedsHuman => 'danger',
            self::Human => 'info',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Bot => Heroicon::OutlinedSparkles,
            self::NeedsHuman => Heroicon::OutlinedHandRaised,
            self::Human => Heroicon::OutlinedUser,
        };
    }

    public function botReplies(): bool
    {
        return $this === self::Bot;
    }
}
