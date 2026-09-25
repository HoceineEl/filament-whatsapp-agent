@php
    $owner = filament()->getTenant();
@endphp
<x-filament-panels::page>
    <div class="wa-play">
        <div class="wa-phone" aria-label="{{ __('whatsapp-agent::playground.phone_label') }}">
            <header class="wa-phone__bar">
                <span class="wa-avatar wa-avatar--brand" aria-hidden="true">{{ mb_substr($owner->agentName(), 0, 1) }}</span>
                <div>
                    <p class="wa-phone__name">{{ $owner->agentName() }}</p>
                    <p class="wa-phone__status" wire:loading.remove wire:target="send">{{ __('whatsapp-agent::playground.online') }}</p>
                    <p class="wa-phone__status wa-phone__status--typing" wire:loading wire:target="send">{{ __('whatsapp-agent::playground.typing') }}</p>
                </div>
                <button type="button" wire:click="resetChat" wire:confirm="{{ __('whatsapp-agent::playground.reset_confirm') }}" class="wa-phone__reset" title="{{ __('whatsapp-agent::playground.reset') }}">
                    <x-filament::icon icon="heroicon-m-arrow-path" class="wa-phone__reset-icon" />
                    <span class="wa-sr-only">{{ __('whatsapp-agent::playground.reset') }}</span>
                </button>
            </header>
            <div class="wa-phone__screen wa-thread__messages" x-data x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => $el.scrollTop = $el.scrollHeight).observe($el, { childList: true, subtree: true })">
                @forelse ($this->messages as $message)
                    <div wire:key="play-{{ $message->id }}">@include('whatsapp-agent::chat.message', ['message' => $message, 'timezone' => $owner->agentTimezone()])</div>
                @empty
                    <div class="wa-phone__intro">
                        <p class="wa-phone__intro-title">{{ __('whatsapp-agent::playground.intro_title') }}</p>
                        <p>{{ __('whatsapp-agent::playground.intro_text') }}</p>
                    </div>
                @endforelse
                <div class="wa-msg wa-msg--in wa-typing" wire:loading.flex wire:target="send" aria-hidden="true">
                    <div class="wa-msg__bubble"><span></span><span></span><span></span></div>
                </div>
            </div>
            <div class="wa-phone__suggestions">
                @foreach ($this->suggestions() as $suggestion)
                    <button type="button" class="wa-chip" wire:click="send(@js($suggestion))" wire:loading.attr="disabled" dir="auto">{{ $suggestion }}</button>
                @endforeach
            </div>
            <form wire:submit="send" class="wa-composer wa-composer--phone">
                <textarea wire:model="draft" rows="1" class="wa-composer__input" dir="auto" placeholder="{{ __('whatsapp-agent::playground.placeholder') }}"
                    x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
                <x-filament::icon-button type="submit" icon="heroicon-m-paper-airplane" :label="__('whatsapp-agent::playground.send')" wire:loading.attr="disabled" wire:target="send" size="lg" />
            </form>
        </div>
        <aside class="wa-play__notes">
            <h3>{{ __('whatsapp-agent::playground.how_title') }}</h3>
            <ul>
                <li>{{ __('whatsapp-agent::playground.how.real') }}</li>
                <li>{{ __('whatsapp-agent::playground.how.tools') }}</li>
                <li>{{ __('whatsapp-agent::playground.how.private') }}</li>
                <li>{{ __('whatsapp-agent::playground.how.tune') }}</li>
            </ul>
            @if ($settingsUrl = \HoceineEl\WhatsAppAgent\WhatsAppAgent::settingsUrl())
                <x-filament::link :href="$settingsUrl" icon="heroicon-m-adjustments-horizontal">{{ __('whatsapp-agent::playground.tune_link') }}</x-filament::link>
            @endif
        </aside>
    </div>
</x-filament-panels::page>
