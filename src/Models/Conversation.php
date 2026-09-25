<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Models;

use HoceineEl\WhatsAppAgent\Concerns\IsAgentConversation;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use IsAgentConversation;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('whatsapp-agent.tables.conversations', parent::getTable());
    }
}
