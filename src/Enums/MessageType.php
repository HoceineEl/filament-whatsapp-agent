<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Audio = 'audio';
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';
    case Location = 'location';
    case Button = 'button';
    case Template = 'template';
    case Unsupported = 'unsupported';

    public function isMedia(): bool
    {
        return in_array($this, [self::Audio, self::Image, self::Document], true);
    }
}
