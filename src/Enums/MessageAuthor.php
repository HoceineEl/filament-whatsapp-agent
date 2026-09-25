<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasLabel;

enum MessageAuthor: string implements HasLabel
{
    case Customer = 'customer';
    case Bot = 'bot';
    case Staff = 'staff';
    case System = 'system';

    public function getLabel(): string
    {
        return __("whatsapp-agent::conversations.authors.{$this->value}");
    }
}
