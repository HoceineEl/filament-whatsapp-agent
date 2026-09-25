<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

enum MessageDirection: string
{
    case Inbound = 'in';
    case Outbound = 'out';
}
