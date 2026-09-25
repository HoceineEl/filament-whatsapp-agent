<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Agent\AgentContext;
use HoceineEl\WhatsAppAgent\AgentProfile;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\StoreProfile;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Support\Facades\File;

it('resolves the configured profile', function () {
    expect(WhatsAppAgent::profile())->toBeInstanceOf(StoreProfile::class);
});

it('falls back to the base profile when none is configured', function () {
    config(['whatsapp-agent.profile' => null]);
    app()->forgetScopedInstances();

    expect(WhatsAppAgent::profile())->toBeInstanceOf(AgentProfile::class)
        ->and(WhatsAppAgent::resolveTools(customerContext()))->toBe([]);
});

it('gives new owners a webhook token and hides their credentials', function () {
    $store = store(['whatsapp_credentials' => ['api_key' => 'secret']]);

    expect($store->webhook_token)->toHaveLength(40)
        ->and($store->toArray())->not->toHaveKey('whatsapp_credentials')
        ->and($store->webhookUrl())->toContain('/webhooks/whatsapp/simulator/'.$store->webhook_token);
});

it('generates a profile class', function () {
    $path = app_path('Ai/ShopProfile.php');
    File::delete($path);

    $this->artisan('make:whatsapp-agent-profile', ['name' => 'ShopProfile'])->assertSuccessful();

    expect(File::get($path))->toContain('class ShopProfile extends AgentProfile');
    File::delete($path);
});

function customerContext(): AgentContext
{
    return AgentContext::for(customerSays(store(), 'hi')->conversation);
}

it('installs with a profile registered in the config', function () {
    $config = config_path('whatsapp-agent.php');
    $profile = app_path('Ai/ShopAgentProfile.php');
    $this->beforeApplicationDestroyed(fn () => File::delete([$config, $profile, ...File::glob(database_path('migrations/*_create_whatsapp_agent_tables.php'))]));

    $this->artisan('whatsapp-agent:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsConfirmation('Create your agent profile class (tools, prompt knowledge, reply gate)?', 'yes')
        ->expectsQuestion('Class name', 'ShopAgentProfile')
        ->assertSuccessful();

    expect(File::get($config))->toContain("'profile' => App\\Ai\\ShopAgentProfile::class,")
        ->and(File::exists($profile))->toBeTrue();

});
