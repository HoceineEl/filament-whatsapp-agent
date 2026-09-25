<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\ConnectionStatus;
use HoceineEl\WhatsAppAgent\Enums\EvolutionServer;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Filament\Concerns\UsesPluginNavigation;
use HoceineEl\WhatsAppAgent\Messaging\EvolutionConnection;
use HoceineEl\WhatsAppAgent\Messaging\OwnerNotifier;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Throwable;

class WhatsAppConnection extends Page
{
    use UsesPluginNavigation;

    private const array SECRET_CREDENTIALS = ['access_token', 'app_secret', 'api_key'];

    private const int QR_LIFETIME_SECONDS = 25;

    protected string $view = 'whatsapp-agent::pages.connection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'whatsapp';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $qrCode = null;

    public ?string $pairingCode = null;

    public ?string $pairingNumber = null;

    public ?int $codeIssuedAt = null;

    protected static function pluginKey(): string
    {
        return 'connection';
    }

    public static function getNavigationLabel(): string
    {
        return __('whatsapp-agent::connection.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('whatsapp-agent::connection.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('whatsapp-agent::connection.subheading');
    }

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant instanceof AgentOwner && WhatsAppAgent::canManage($tenant, auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $owner = filament()->getTenant();

        return $owner instanceof AgentOwner && $owner->whatsapp_driver !== WhatsAppDriver::Simulator && $owner->whatsapp_status !== ConnectionStatus::Connected
            ? '!'
            : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        $owner = $this->owner();

        $this->form->fill([
            'whatsapp_driver' => $owner->whatsapp_driver,
            'server' => $owner->evolutionServer(),
            'credentials' => Arr::except($owner->whatsapp_credentials ?? [], [...self::SECRET_CREDENTIALS, 'instance_token', 'instance', 'server']),
            'reminder_template' => $owner->agentSetting('reminder_template'),
            'template_language' => $owner->agentSetting('template_language'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('whatsapp-agent::connection.sections.method'))
                    ->description(__('whatsapp-agent::connection.sections.method_hint'))
                    ->compact()
                    ->schema([
                        Radio::make('whatsapp_driver')
                            ->hiddenLabel()
                            ->options(WhatsAppDriver::class)
                            ->required()
                            ->live(),
                    ]),
                Section::make(__('whatsapp-agent::connection.sections.server'))
                    ->description(__('whatsapp-agent::connection.sections.server_hint'))
                    ->compact()
                    ->visible(fn (Get $get): bool => $this->selectedDriver($get) === WhatsAppDriver::Evolution)
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Radio::make('server')
                            ->hiddenLabel()
                            ->options(EvolutionServer::class)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('credentials.api_url')
                            ->url()
                            ->placeholder('https://evolution.example.com')
                            ->required(fn (Get $get): bool => $this->isCustomServer($get))
                            ->visible(fn (Get $get): bool => $this->isCustomServer($get)),
                        TextInput::make('credentials.api_key')
                            ->password()
                            ->revealable()
                            ->helperText(__('whatsapp-agent::connection.api_key_hint'))
                            ->placeholder(fn (): ?string => $this->secretPlaceholder('api_key'))
                            ->required(fn (Get $get): bool => $this->isCustomServer($get) && blank($this->owner()->credential('api_key')))
                            ->visible(fn (Get $get): bool => $this->isCustomServer($get)),
                    ]),
                Section::make(__('whatsapp-agent::connection.sections.cloud'))
                    ->description(__('whatsapp-agent::connection.sections.cloud_hint'))
                    ->compact()
                    ->visible(fn (Get $get): bool => $this->selectedDriver($get) === WhatsAppDriver::Cloud)
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('credentials.phone_number_id')->required()->maxLength(40),
                        TextInput::make('credentials.waba_id')->maxLength(40),
                        TextInput::make('credentials.access_token')
                            ->password()
                            ->revealable()
                            ->required(fn (): bool => blank($this->owner()->credential('access_token')))
                            ->placeholder(fn (): ?string => $this->secretPlaceholder('access_token'))
                            ->columnSpanFull(),
                        TextInput::make('credentials.app_secret')
                            ->password()
                            ->revealable()
                            ->placeholder(fn (): ?string => $this->secretPlaceholder('app_secret')),
                        TextInput::make('credentials.verify_token')->maxLength(80),
                        TextEntry::make('webhook_url')
                            ->state(fn (): string => $this->owner()->webhookUrl(WhatsAppDriver::Cloud))
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                        TextInput::make('reminder_template')->maxLength(60)->helperText(__('whatsapp-agent::connection.template_hint')),
                        Select::make('template_language')->options(['ar' => 'ar', 'en' => 'en', 'en_US' => 'en_US']),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $owner = $this->owner();

        $submitted = array_filter($data['credentials'] ?? [], fn (mixed $value): bool => filled($value));
        $credentials = array_merge($owner->whatsapp_credentials ?? [], $submitted);
        $driver = WhatsAppDriver::from($this->enumValue($data['whatsapp_driver']));

        if ($driver === WhatsAppDriver::Evolution) {
            $credentials['server'] = $this->enumValue($data['server'] ?? EvolutionServer::Platform);
        }

        $serverChanged = ($owner->whatsapp_credentials['server'] ?? EvolutionServer::Platform->value) !== ($credentials['server'] ?? EvolutionServer::Platform->value)
            || ($owner->whatsapp_credentials['api_url'] ?? null) !== ($credentials['api_url'] ?? null);

        $owner->update([
            'whatsapp_driver' => $driver,
            'whatsapp_credentials' => $credentials,
            'whatsapp_status' => $driver !== $owner->whatsapp_driver || $serverChanged ? ConnectionStatus::Disconnected : $owner->whatsapp_status,
        ]);
        $owner->saveAgentSettings(Arr::only($data, ['reminder_template', 'template_language']));

        $this->resetCode();

        Notification::make()->success()->title(__('whatsapp-agent::connection.saved'))->send();
    }

    public function generateQr(EvolutionConnection $connection): void
    {
        $this->startConnection($connection);
    }

    public function requestPairingCode(EvolutionConnection $connection): void
    {
        $number = (string) preg_replace('/\D/', '', (string) $this->pairingNumber);

        if (strlen($number) < 8) {
            $this->addError('pairingNumber', __('whatsapp-agent::connection.errors.pairing_number'));

            return;
        }

        $this->startConnection($connection, $number);
    }

    public function poll(EvolutionConnection $connection): void
    {
        if ($this->qrCode === null && $this->pairingCode === null) {
            return;
        }

        $owner = $this->owner();

        if ($connection->refresh($owner) === ConnectionStatus::Connected) {
            $this->resetCode();

            Notification::make()->success()->title(__('whatsapp-agent::connection.connected_title'))
                ->body($owner->whatsapp_number ? '+'.$owner->whatsapp_number : null)
                ->send();

            return;
        }

        if ($this->qrCode !== null && now()->timestamp - (int) $this->codeIssuedAt >= self::QR_LIFETIME_SECONDS) {
            $this->qrCode = rescue(fn (): ?string => $connection->freshCode($owner)['base64'], $this->qrCode, report: false);
            $this->codeIssuedAt = now()->timestamp;
        }
    }

    public function cancelCode(): void
    {
        $this->resetCode();
    }

    public function checkAction(): Action
    {
        return Action::make('check')
            ->label(__('whatsapp-agent::connection.actions.check'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => $this->usesEvolution())
            ->action(function (EvolutionConnection $connection): void {
                $status = $connection->refresh($this->owner());

                Notification::make()->title($status->getLabel())->color($status->getColor())->send();
            });
    }

    public function testServerAction(): Action
    {
        return Action::make('testServer')
            ->label(__('whatsapp-agent::connection.actions.test_server'))
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->visible(fn (): bool => $this->usesEvolution())
            ->action(function (EvolutionConnection $connection): void {
                try {
                    $version = $connection->serverVersion($this->owner());

                    Notification::make()->success()->title(__('whatsapp-agent::connection.server_ok', ['version' => $version]))->send();
                } catch (Throwable $exception) {
                    Notification::make()->danger()->title(__('whatsapp-agent::connection.errors.server'))->body($exception->getMessage())->send();
                }
            });
    }

    public function sendTestAction(): Action
    {
        return Action::make('sendTest')
            ->label(__('whatsapp-agent::connection.actions.send_test'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->disabled(fn (): bool => blank($this->owner()->agentOwnerWhatsApp()))
            ->tooltip(fn (): ?string => blank($this->owner()->agentOwnerWhatsApp()) ? __('whatsapp-agent::connection.owner_whatsapp_required') : null)
            ->action(function (OwnerNotifier $notifier): void {
                $sent = $notifier->notify($this->owner(), __('whatsapp-agent::connection.test_message', ['business' => $this->owner()->agentName()]));

                Notification::make()
                    ->title($sent ? __('whatsapp-agent::connection.test_sent') : __('whatsapp-agent::connection.test_failed'))
                    ->{$sent ? 'success' : 'danger'}()
                    ->send();
            });
    }

    public function importPersonalAction(): Action
    {
        return Action::make('importPersonal')
            ->label(__('whatsapp-agent::connection.actions.import_personal'))
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('whatsapp-agent::connection.import_personal_confirm'))
            ->visible(fn (): bool => $this->isEvolutionConnected())
            ->action(function (EvolutionConnection $connection): void {
                $count = rescue(fn (): int => $connection->importPersonalChats($this->owner()), report: false);

                if ($count === null) {
                    Notification::make()->danger()->title(__('whatsapp-agent::connection.import_personal_failed'))->send();

                    return;
                }

                Notification::make()->success()->title(trans_choice('whatsapp-agent::connection.imported_personal', $count, ['count' => $count]))->send();
            });
    }

    public function disconnectAction(): Action
    {
        return Action::make('disconnect')
            ->label(__('whatsapp-agent::connection.actions.disconnect'))
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('whatsapp-agent::connection.disconnect_confirm'))
            ->visible(fn (): bool => $this->isEvolutionConnected())
            ->action(function (EvolutionConnection $connection): void {
                $connection->disconnect($this->owner());
                $this->resetCode();

                Notification::make()->success()->title(__('whatsapp-agent::connection.disconnected'))->send();
            });
    }

    public function owner(): AgentOwner&Model
    {
        /** @var AgentOwner $owner */
        $owner = filament()->getTenant();

        return $owner;
    }

    private function startConnection(EvolutionConnection $connection, ?string $pairingNumber = null): void
    {
        $this->resetCode();

        try {
            $code = $connection->start($this->owner(), $pairingNumber);
        } catch (Throwable $exception) {
            Notification::make()->danger()->title(__('whatsapp-agent::connection.errors.server'))->body($exception->getMessage())->send();

            return;
        }

        $this->qrCode = $pairingNumber === null ? $code['base64'] : null;
        $this->pairingCode = $pairingNumber !== null ? $code['pairing_code'] : null;
        $this->codeIssuedAt = now()->timestamp;

        if ($this->qrCode === null && $this->pairingCode === null) {
            $connection->refresh($this->owner());
        }
    }

    private function resetCode(): void
    {
        $this->qrCode = null;
        $this->pairingCode = null;
        $this->codeIssuedAt = null;
    }

    private function usesEvolution(): bool
    {
        return $this->owner()->whatsapp_driver === WhatsAppDriver::Evolution;
    }

    private function isEvolutionConnected(): bool
    {
        return $this->usesEvolution() && $this->owner()->whatsapp_status === ConnectionStatus::Connected;
    }

    private function selectedDriver(Get $get): ?WhatsAppDriver
    {
        return WhatsAppDriver::tryFrom($this->enumValue($get('whatsapp_driver')));
    }

    private function isCustomServer(Get $get): bool
    {
        return EvolutionServer::tryFrom($this->enumValue($get('server'))) === EvolutionServer::Custom;
    }

    private function secretPlaceholder(string $key): ?string
    {
        return filled($this->owner()->credential($key)) ? '••••••••' : null;
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
