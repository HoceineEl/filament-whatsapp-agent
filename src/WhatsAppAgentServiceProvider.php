<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use HoceineEl\WhatsAppAgent\Channels\Drivers\SimulatorGateway;
use HoceineEl\WhatsAppAgent\Commands\MakeAgentProfileCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WhatsAppAgentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('whatsapp-agent')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasRoute('webhooks')
            ->hasMigration('create_whatsapp_agent_tables')
            ->hasCommand(MakeAgentProfileCommand::class)
            ->hasInstallCommand(fn (InstallCommand $command) => $command
                ->publishConfigFile()
                ->publishMigrations()
                ->askToRunMigrations()
                ->endWith(fn (InstallCommand $command) => $this->finishInstall($command)));
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(AgentProfile::class, fn ($app): AgentProfile => filled($profile = config('whatsapp-agent.profile')) ? $app->make($profile) : new AgentProfile);
        $this->app->singleton(SimulatorGateway::class);
    }

    private function finishInstall(InstallCommand $command): void
    {
        $config = config_path('whatsapp-agent.php');

        if ($command->confirm('Create your agent profile class (tools, prompt knowledge, reply gate)?', true)) {
            $name = (string) $command->ask('Class name', 'AgentProfile');
            $command->call('make:whatsapp-agent-profile', ['name' => $name]);

            if (is_file($config)) {
                $class = trim($this->app->getNamespace(), '\\').'\\Ai\\'.$name;
                file_put_contents($config, str_replace("'profile' => null,", "'profile' => {$class}::class,", (string) file_get_contents($config)));
            }
        }

        $command->call('filament:assets');

        $command->newLine();
        $command->line('Next steps:');
        $command->line('  1. Owner model: implements AgentOwner, use HasWhatsAppAgent;');
        $command->line('  2. Panel: ->plugin(WhatsAppAgentPlugin::make())');
        $command->line('  3. .env: GEMINI_API_KEY, EVOLUTION_API_URL, EVOLUTION_API_KEY');
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('whatsapp-agent', __DIR__.'/../resources/css/whatsapp-agent.css'),
        ], 'hoceineel/filament-whatsapp-agent');
    }
}
