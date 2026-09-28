<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Models;

use HoceineEl\WhatsAppAgent\Concerns\HasWhatsAppAgent;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The owner for apps without Filament tenancy: one WhatsApp number, one assistant.
 */
class WhatsAppAccount extends Model implements AgentOwner
{
    use HasWhatsAppAgent;

    protected $table = 'whatsapp_accounts';

    protected $guarded = ['id'];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public static function current(): static
    {
        return static::query()->oldest('id')->first() ?? static::create([
            'name' => (string) config('app.name'),
            'slug' => Str::slug((string) config('app.name')) ?: 'app',
        ]);
    }

    public function agentNotifiables(): iterable
    {
        return WhatsAppAgent::userModel()::query()->lazy()->filter(fn (Model $user): bool => WhatsAppAgent::canManage($this, $user));
    }
}
