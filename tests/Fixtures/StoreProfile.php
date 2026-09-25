<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Tests\Fixtures;

use HoceineEl\WhatsAppAgent\Agent\AgentContext;
use HoceineEl\WhatsAppAgent\AgentProfile;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Illuminate\Database\Eloquent\Model;

class StoreProfile extends AgentProfile
{
    public static ?string $blocked = null;

    public function tools(AgentContext $context): iterable
    {
        return [CheckStockTool::class];
    }

    public function knowledge(AgentContext $context): iterable
    {
        return ["## Store facts\n- Opening hours: 9am to 9pm", null];
    }

    public function customerFacts(AgentContext $context): iterable
    {
        return ['Loyalty points: 120'];
    }

    public function blockedReason(AgentOwner $owner, Model $contact): ?string
    {
        return self::$blocked;
    }

    public function intercept(Model $inbound, AgentContext $context): bool
    {
        return $inbound->body === 'PING';
    }

    public function toolLabels(): array
    {
        return ['check_stock' => 'Checked stock'];
    }
}
