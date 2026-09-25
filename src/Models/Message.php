<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Models;

use HoceineEl\WhatsAppAgent\Concerns\IsAgentMessage;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use IsAgentMessage;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('whatsapp-agent.tables.messages', parent::getTable());
    }
}
