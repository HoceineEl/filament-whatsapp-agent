<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use HoceineEl\WhatsAppAgent\Channels\Drivers\SimulatorGateway;
use HoceineEl\WhatsAppAgent\Commands\MakeAgentProfileCommand;
use HoceineEl\WhatsAppAgent\Models\WhatsAppAccount;
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
        $multiTenant = $command->confirm('Does your panel use Filament multi-tenancy (one WhatsApp number per team)?', false);

        if (! $multiTenant) {
            $this->editConfig($config, [
                "'owner' => null," => "'owner' => ".WhatsAppAccount::class.'::class,',
                "'owner' => 'business_id'," => "'owner' => 'whatsapp_account_id',",
            ]);
            config(['whatsapp-agent.models.owner' => WhatsAppAccount::class, 'whatsapp-agent.columns.owner' => 'whatsapp_account_id']);
        }

        if ($command->confirm('Would you like to run the migrations now?', true)) {
            $command->call('migrate');
        }

        if ($command->confirm('Create your agent profile class (tools, prompt knowledge, reply gate)?', true)) {
            $name = (string) $command->ask('Class name', 'AgentProfile');
            $command->call('make:whatsapp-agent-profile', ['name' => $name]);
            $this->editConfig($config, ["'profile' => null," => "'profile' => ".trim($this->app->getNamespace(), '\\').'\\Ai\\'.$name.'::class,']);
        }

        $command->call('filament:assets');

        $command->newLine();
        $command->line('Next steps:');

        $steps = array_filter([
            $multiTenant ? 'Owner (tenant) model: implements AgentOwner, use HasWhatsAppAgent; set models.owner in config/whatsapp-agent.php' : null,
            'Panel: ->plugin(WhatsAppAgentPlugin::make())',
            '.env: GEMINI_API_KEY, EVOLUTION_API_URL, EVOLUTION_API_KEY',
        ]);

        foreach (array_values($steps) as $index => $step) {
            $command->line('  '.($index + 1).'. '.$step);
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function editConfig(string $path, array $replacements): void
    {
        if (is_file($path)) {
            file_put_contents($path, strtr((string) file_get_contents($path), $replacements));
        }
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('whatsapp-agent', __DIR__.'/../resources/css/whatsapp-agent.css'),
        ], 'hoceineel/filament-whatsapp-agent');
    }
}
