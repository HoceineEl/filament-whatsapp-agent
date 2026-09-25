<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Models;

use HoceineEl\WhatsAppAgent\Concerns\IsAgentContact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use IsAgentContact;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('whatsapp-agent.tables.contacts', parent::getTable());
    }
}
