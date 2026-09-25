<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Tests\Fixtures;

use HoceineEl\WhatsAppAgent\Concerns\HasWhatsAppAgent;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Illuminate\Database\Eloquent\Model;

class Store extends Model implements AgentOwner
{
    use HasWhatsAppAgent;

    protected $guarded = [];

    protected $casts = ['settings' => 'array'];
}
