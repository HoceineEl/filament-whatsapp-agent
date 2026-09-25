<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MessageStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __("whatsapp-agent::conversations.message_statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Failed => 'danger',
            self::Read => 'success',
            self::Delivered, self::Sent => 'info',
            default => 'gray',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Received, self::Queued => 0,
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
            self::Failed => 4,
        };
    }
}
