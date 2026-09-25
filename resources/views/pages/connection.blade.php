@php
    $owner = $this->owner();
    $status = $owner->whatsapp_status;
    $driver = $owner->whatsapp_driver;
    $isEvolution = $driver === \HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver::Evolution;
    $isConnected = $status === \HoceineEl\WhatsAppAgent\Enums\ConnectionStatus::Connected;
    $hasCode = $qrCode || $pairingCode;
@endphp
<x-filament-panels::page>
    <section @class(['wa-status', 'wa-status--'.$status->value]) @if ($hasCode) wire:poll.3s="poll" @endif>
        <span class="wa-status__icon" aria-hidden="true">
            <x-filament::icon :icon="$driver->getIcon()" class="wa-status__glyph" />
        </span>
        <div class="wa-status__body">
            <p class="wa-status__eyebrow">{{ $driver->getLabel() }}@if ($isEvolution) · {{ $owner->evolutionServer()->getLabel() }}@endif</p>
            <p class="wa-status__title">
                @if ($driver === \HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver::Simulator)
                    {{ __('whatsapp-agent::connection.status.simulator') }}
                @elseif ($isConnected)
                    {{ __('whatsapp-agent::connection.status.connected') }}
                @elseif ($status === \HoceineEl\WhatsAppAgent\Enums\ConnectionStatus::Connecting)
                    {{ __('whatsapp-agent::connection.status.connecting') }}
                @else
                    {{ __('whatsapp-agent::connection.status.disconnected') }}
                @endif
            </p>
            @if ($owner->whatsapp_number && $isConnected)
                <p class="wa-status__number" dir="ltr">+{{ $owner->whatsapp_number }}</p>
            @endif
        </div>
        <x-filament::badge :color="$status->getColor()" class="wa-status__badge">{{ $status->getLabel() }}</x-filament::badge>
        <div class="wa-status__actions">
            {{ $this->checkAction }}
            {{ $this->testServerAction }}
            {{ $this->sendTestAction }}
            {{ $this->importPersonalAction }}
            {{ $this->disconnectAction }}
        </div>
    </section>

    <div class="wa-grid">
        <form wire:submit="save" class="wa-grid__form">
            {{ $this->form }}
            <div class="wa-grid__save">
                <x-filament::button type="submit" icon="heroicon-m-check">{{ __('whatsapp-agent::connection.actions.save') }}</x-filament::button>
            </div>
        </form>

        <aside class="wa-connect">
            @if ($isEvolution && ! $isConnected)
                <header class="wa-connect__head">
                    <h2 class="wa-connect__title">{{ __('whatsapp-agent::connection.connect.title') }}</h2>
                    <p class="wa-connect__hint">{{ __('whatsapp-agent::connection.connect.hint') }}</p>
                </header>

                <div x-data="{ mode: 'qr' }" class="wa-connect__body">
                    <div class="wa-switch" role="tablist">
                        <button type="button" role="tab" x-on:click="mode = 'qr'" :aria-selected="mode === 'qr'" class="wa-switch__tab">{{ __('whatsapp-agent::connection.connect.by_qr') }}</button>
                        <button type="button" role="tab" x-on:click="mode = 'code'" :aria-selected="mode === 'code'" class="wa-switch__tab">{{ __('whatsapp-agent::connection.connect.by_code') }}</button>
                    </div>

                    <div x-show="mode === 'qr'" class="wa-connect__pane">
                        @if ($qrCode)
                            <div class="wa-qr">
                                <img src="{{ str_starts_with($qrCode, 'data:') ? $qrCode : 'data:image/png;base64,'.$qrCode }}" alt="{{ __('whatsapp-agent::connection.connect.qr_alt') }}" class="wa-qr__image" width="272" height="272">
                                <p class="wa-qr__live"><span class="wa-qr__pulse" aria-hidden="true"></span>{{ __('whatsapp-agent::connection.connect.waiting_scan') }}</p>
                            </div>
                        @else
                            <div class="wa-qr wa-qr--empty">
                                <x-filament::icon icon="heroicon-o-qr-code" class="wa-qr__placeholder" />
                            </div>
                        @endif
                        <ol class="wa-steps">
                            <li>{{ __('whatsapp-agent::connection.connect.steps.open') }}</li>
                            <li>{{ __('whatsapp-agent::connection.connect.steps.linked') }}</li>
                            <li>{{ __('whatsapp-agent::connection.connect.steps.scan') }}</li>
                        </ol>
                        <div class="wa-connect__actions">
                            <x-filament::button wire:click="generateQr" wire:loading.attr="disabled" wire:target="generateQr" icon="heroicon-m-qr-code">
                                {{ $qrCode ? __('whatsapp-agent::connection.connect.new_qr') : __('whatsapp-agent::connection.connect.show_qr') }}
                            </x-filament::button>
                            @if ($hasCode)
                                <x-filament::button wire:click="cancelCode" color="gray">{{ __('whatsapp-agent::connection.connect.cancel') }}</x-filament::button>
                            @endif
                        </div>
                    </div>

                    <div x-show="mode === 'code'" x-cloak class="wa-connect__pane">
                        @if ($pairingCode)
                            <div class="wa-code" dir="ltr">{{ $pairingCode }}</div>
                            <p class="wa-qr__live"><span class="wa-qr__pulse" aria-hidden="true"></span>{{ __('whatsapp-agent::connection.connect.waiting_code') }}</p>
                        @endif
                        <ol class="wa-steps">
                            <li>{{ __('whatsapp-agent::connection.connect.code_steps.open') }}</li>
                            <li>{{ __('whatsapp-agent::connection.connect.code_steps.phone') }}</li>
                            <li>{{ __('whatsapp-agent::connection.connect.code_steps.enter') }}</li>
                        </ol>
                        <div class="wa-connect__field">
                            <x-filament::input.wrapper :valid="! $errors->has('pairingNumber')">
                                <x-filament::input type="tel" wire:model="pairingNumber" dir="ltr" placeholder="9665XXXXXXXX" />
                            </x-filament::input.wrapper>
                            @error('pairingNumber')<p class="wa-connect__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="wa-connect__actions">
                            <x-filament::button wire:click="requestPairingCode" wire:loading.attr="disabled" wire:target="requestPairingCode" icon="heroicon-m-key">{{ __('whatsapp-agent::connection.connect.get_code') }}</x-filament::button>
                        </div>
                    </div>
                </div>
                <p class="wa-connect__note">{{ __('whatsapp-agent::connection.connect.unofficial_note') }}</p>
            @elseif ($isEvolution && $isConnected)
                <div class="wa-done">
                    <x-filament::icon icon="heroicon-o-check-badge" class="wa-done__icon" />
                    <h2 class="wa-connect__title">{{ __('whatsapp-agent::connection.status.connected') }}</h2>
                    <p class="wa-connect__hint">{{ __('whatsapp-agent::connection.connect.done_hint') }}</p>
                </div>
            @elseif ($driver === \HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver::Cloud)
                <header class="wa-connect__head">
                    <h2 class="wa-connect__title">{{ __('whatsapp-agent::connection.cloud_guide.title') }}</h2>
                </header>
                <ol class="wa-steps">
                    <li>{{ __('whatsapp-agent::connection.cloud_guide.app') }}</li>
                    <li>{{ __('whatsapp-agent::connection.cloud_guide.token') }}</li>
                    <li>{{ __('whatsapp-agent::connection.cloud_guide.webhook') }}</li>
                    <li>{{ __('whatsapp-agent::connection.cloud_guide.template') }}</li>
                </ol>
            @else
                <div class="wa-done">
                    <x-filament::icon icon="heroicon-o-beaker" class="wa-done__icon" />
                    <h2 class="wa-connect__title">{{ __('whatsapp-agent::connection.status.simulator') }}</h2>
                    <p class="wa-connect__hint">{{ __('whatsapp-agent::connection.simulator_hint') }}</p>
                    <x-filament::link :href="\HoceineEl\WhatsAppAgent\Filament\Pages\Playground::getUrl()" icon="heroicon-m-arrow-top-right-on-square">{{ __('whatsapp-agent::playground.title') }}</x-filament::link>
                </div>
            @endif
        </aside>
    </div>
</x-filament-panels::page>
