<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use HoceineEl\WhatsAppAgent\Agent\AgentRuntime;
use HoceineEl\WhatsAppAgent\Agent\AssistantAgent;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Filament\Pages\Inbox;
use HoceineEl\WhatsAppAgent\Filament\Pages\Playground;
use HoceineEl\WhatsAppAgent\Models\Conversation;
use HoceineEl\WhatsAppAgent\Models\Message;
use HoceineEl\WhatsAppAgent\Models\WhatsAppAccount;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\User;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $migration = require __DIR__.'/../../database/migrations/create_whatsapp_agent_tables.php.stub';
    $migration->down();

    config(['whatsapp-agent.models.owner' => WhatsAppAccount::class, 'whatsapp-agent.columns.owner' => 'whatsapp_account_id']);
    $migration->up();
});

it('creates the accounts table and a single account on first use', function () {
    expect(Schema::hasTable('whatsapp_accounts'))->toBeTrue()
        ->and(Schema::hasColumn('whatsapp_contacts', 'whatsapp_account_id'))->toBeTrue();

    $account = WhatsAppAgent::currentOwner();

    expect($account)->toBeInstanceOf(WhatsAppAccount::class)
        ->and($account->webhook_token)->toHaveLength(40)
        ->and($account->whatsapp_driver)->toBe(WhatsAppDriver::Simulator)
        ->and(WhatsAppAgent::currentOwner()->is($account))->toBeTrue()
        ->and(WhatsAppAccount::query()->count())->toBe(1);
});

it('answers customers end to end without a tenant model', function () {
    AssistantAgent::fake(['We open at 9am.']);
    $account = WhatsAppAccount::current();

    $reply = app(AgentRuntime::class)->respond(customerSays($account, 'When do you open?'));

    expect($reply->body)->toBe('We open at 9am.')
        ->and($reply->whatsapp_account_id)->toBe($account->getKey())
        ->and(WhatsAppAgent::tenantQuery(Conversation::class)->count())->toBe(1);
});

it('receives webhooks on the account token', function () {
    $account = WhatsAppAccount::current();
    $account->update(['whatsapp_driver' => WhatsAppDriver::Evolution]);

    postEvolution($account, evolutionText('Salam'))->assertOk();

    expect(Message::query()->sole()->whatsapp_account_id)->toBe($account->getKey());
});

it('keeps the settings and defaults on the account', function () {
    $account = WhatsAppAccount::current();
    $account->saveAgentSettings(['assistant_name' => 'Nora']);

    expect($account->refresh()->assistantName())->toBe('Nora')
        ->and($account->agentSetting('max_ai_replies_per_day'))->toBe(60)
        ->and($account->agentName())->toBe(config('app.name'));
});

it('runs the playground and inbox in a panel without tenancy', function () {
    Filament::setCurrentPanel('app');
    AssistantAgent::fake(['Hello from the assistant']);
    $this->actingAs(User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret']));

    Livewire::test(Playground::class)
        ->call('send', 'hi')
        ->assertSee('Hello from the assistant');

    Livewire::test(Inbox::class)->assertOk();

    expect(Message::query()->forOwner(WhatsAppAccount::current())->count())->toBe(2);
});
