<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\AdminPanelProvider;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\Store;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\StoreProfile;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\User;
use HoceineEl\WhatsAppAgent\WhatsAppAgentServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AiServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('locale')->default('en');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        (require __DIR__.'/../database/migrations/create_whatsapp_agent_tables.php.stub')->up();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            AiServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            WidgetsServiceProvider::class,
            WhatsAppAgentServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('whatsapp-agent.models.owner', Store::class);
        $app['config']->set('whatsapp-agent.columns.owner', 'business_id');
        $app['config']->set('whatsapp-agent.models.user', User::class);
        $app['config']->set('whatsapp-agent.profile', StoreProfile::class);
        $app['config']->set('whatsapp-agent.reply_debounce_seconds', 0);
        $app['config']->set('ai.providers.gemini', ['driver' => 'gemini', 'key' => 'test']);
        $app['config']->set('ai.providers.gemini-fallback', ['driver' => 'gemini', 'key' => 'test']);
    }
}
