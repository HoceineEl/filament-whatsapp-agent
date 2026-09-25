<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasLabel;

enum PersonalSource: string implements HasLabel
{
    case Owner = 'owner';
    case Screening = 'screening';
    case ExistingChat = 'existing_chat';

    public function getLabel(): string
    {
        return __("whatsapp-agent::contacts.personal_sources.{$this->value}");
    }
}
