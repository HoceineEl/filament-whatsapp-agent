<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReplyLanguage: string implements HasLabel
{
    case Auto = 'auto';
    case Arabic = 'ar';
    case English = 'en';
    case French = 'fr';
    case Urdu = 'ur';
    case Hindi = 'hi';
    case Filipino = 'tl';
    case Bengali = 'bn';

    public function getLabel(): string
    {
        return __("whatsapp-agent::assistant.reply_languages.{$this->value}");
    }

    public function promptName(): string
    {
        return match ($this) {
            self::Auto => 'the customer\'s language',
            self::Arabic => 'Arabic',
            self::English => 'English',
            self::French => 'French',
            self::Urdu => 'Urdu',
            self::Hindi => 'Hindi',
            self::Filipino => 'Filipino (Tagalog)',
            self::Bengali => 'Bengali',
        };
    }
}
