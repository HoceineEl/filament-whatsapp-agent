@php
    use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
    $owner = filament()->getTenant();
    $active = $this->active;
@endphp
<x-filament-panels::page>
    <div class="wa-inbox" wire:poll.5s="refreshThread">
        <aside class="wa-inbox__list" aria-label="{{ __('whatsapp-agent::inbox.conversations') }}">
            <div class="wa-inbox__search">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__('whatsapp-agent::inbox.search')" />
                </x-filament::input.wrapper>
                <div class="wa-inbox__filters" role="tablist">
                    @foreach (['all', 'waiting', 'unread'] as $key)
                        <button type="button" role="tab" wire:click="setFilter('{{ $key }}')" aria-selected="{{ $filter === $key ? 'true' : 'false' }}" @class(['wa-chip', 'is-active' => $filter === $key])>
                            {{ __("whatsapp-agent::inbox.filters.{$key}") }}
                        </button>
                    @endforeach
                </div>
            </div>
            <ul class="wa-inbox__items">
                @forelse ($this->conversations as $item)
                    <li wire:key="conversation-{{ $item->id }}">
                        <button type="button" wire:click="open({{ $item->id }})" @class(['wa-convo', 'is-active' => $item->id === $conversation])>
                            <span class="wa-avatar" aria-hidden="true">{{ mb_substr($item->contact->name ?: '#', 0, 1) }}</span>
                            <span class="wa-convo__body">
                                <span class="wa-convo__top">
                                    <span class="wa-convo__name" dir="auto">{{ $item->contact->displayName() }}</span>
                                    <time class="wa-convo__time">{{ $item->last_message_at?->setTimezone($owner->agentTimezone())->isToday() ? $item->last_message_at->setTimezone($owner->agentTimezone())->format('g:i A') : $item->last_message_at?->setTimezone($owner->agentTimezone())->format('d/m') }}</time>
                                </span>
                                <span class="wa-convo__bottom">
                                    <span class="wa-convo__preview" dir="auto">{{ \Illuminate\Support\Str::limit(str_replace('*', '', $item->latestMessage?->body ?? __('whatsapp-agent::inbox.voice_note')), 60) }}</span>
                                    @if ($item->status !== ConversationStatus::Bot)
                                        <span class="wa-convo__flag" title="{{ $item->status->getLabel() }}"><x-filament::icon :icon="$item->status->getIcon()" class="wa-convo__flag-icon" /><span class="wa-sr-only">{{ $item->status->getLabel() }}</span></span>
                                    @elseif ($item->unread_count > 0)
                                        <span class="wa-convo__unread">{{ $item->unread_count }}</span>
                                    @endif
                                </span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="wa-inbox__empty">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="wa-inbox__empty-icon" />
                        <p class="wa-inbox__empty-title">{{ __('whatsapp-agent::inbox.empty_title') }}</p>
                        <p>{{ __('whatsapp-agent::inbox.empty_text') }}</p>
                        <x-filament::link :href="\HoceineEl\WhatsAppAgent\Filament\Pages\Playground::getUrl()" icon="heroicon-m-beaker">{{ __('whatsapp-agent::inbox.try_playground') }}</x-filament::link>
                    </li>
                @endforelse
            </ul>
        </aside>

        <section class="wa-thread" aria-label="{{ __('whatsapp-agent::inbox.thread') }}">
            @if ($active)
                <header class="wa-thread__head">
                    <span class="wa-avatar wa-avatar--lg" aria-hidden="true">{{ mb_substr($active->contact->name ?: '#', 0, 1) }}</span>
                    <div class="wa-thread__who">
                        <p class="wa-thread__name" dir="auto">{{ $active->contact->displayName() }}</p>
                        <p class="wa-thread__phone" dir="ltr">+{{ $active->contact->phone }}</p>
                    </div>
                    <x-filament::badge :color="$active->status->getColor()" :icon="$active->status->getIcon()">{{ $active->status->getLabel() }}</x-filament::badge>
                    @if ($active->status === ConversationStatus::Bot)
                        <x-filament::button wire:click="takeOver" color="gray" icon="heroicon-m-hand-raised" size="sm">{{ __('whatsapp-agent::inbox.take_over') }}</x-filament::button>
                    @else
                        <x-filament::button wire:click="handBack" icon="heroicon-m-sparkles" size="sm">{{ __('whatsapp-agent::inbox.hand_back') }}</x-filament::button>
                    @endif
                    <x-filament::icon-button wire:click="markPersonal" wire:confirm="{{ __('whatsapp-agent::inbox.mark_personal_confirm') }}" icon="heroicon-m-user-minus" color="gray" :label="__('whatsapp-agent::inbox.mark_personal')" :tooltip="__('whatsapp-agent::inbox.mark_personal')" />
                </header>
                @if ($active->status === ConversationStatus::NeedsHuman && $active->handoff_reason)
                    <div class="wa-thread__alert" role="status">
                        <x-filament::icon icon="heroicon-m-hand-raised" class="wa-thread__alert-icon" />
                        <span><strong>{{ __('whatsapp-agent::inbox.needs_you') }}</strong> {{ $active->handoff_reason }}</span>
                    </div>
                @endif
                <div class="wa-thread__messages" x-data x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => $el.scrollTop = $el.scrollHeight).observe($el, { childList: true, subtree: true })">
                    @foreach ($this->messages as $message)
                        <div wire:key="message-{{ $message->id }}">@include('whatsapp-agent::chat.message', ['message' => $message, 'timezone' => $owner->agentTimezone()])</div>
                    @endforeach
                </div>
                <form wire:submit="send" class="wa-composer">
                    <textarea wire:model="reply" rows="1" class="wa-composer__input" dir="auto" placeholder="{{ $active->status->botReplies() ? __('whatsapp-agent::inbox.reply_placeholder_bot') : __('whatsapp-agent::inbox.reply_placeholder') }}"
                        x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled" wire:target="send">{{ __('whatsapp-agent::inbox.send') }}</x-filament::button>
                </form>
            @else
                <div class="wa-thread__placeholder">
                    <x-filament::icon icon="heroicon-o-chat-bubble-oval-left-ellipsis" class="wa-inbox__empty-icon" />
                    <p>{{ __('whatsapp-agent::inbox.pick') }}</p>
                </div>
            @endif
        </section>

        @if ($active)
            <aside class="wa-profile" aria-label="{{ __('whatsapp-agent::inbox.customer') }}">
                <h3 class="wa-profile__title">{{ __('whatsapp-agent::inbox.customer') }}</h3>
                <dl class="wa-profile__facts">
                    <div><dt>{{ __('whatsapp-agent::inbox.language') }}</dt><dd>{{ $active->contact->locale === 'en' ? 'English' : 'العربية' }}</dd></div>
                    <div><dt>{{ __('whatsapp-agent::inbox.first_contact') }}</dt><dd>{{ $active->created_at->setTimezone($owner->agentTimezone())->format('d/m/Y') }}</dd></div>
                    @if ($active->contact->isOptedOut())
                        <div><dt>{{ __('whatsapp-agent::inbox.reminders') }}</dt><dd>{{ __('whatsapp-agent::inbox.opted_out') }}</dd></div>
                    @endif
                </dl>
                @if ($panel = $this->contactPanel)
                    {!! $panel instanceof \Illuminate\Contracts\Support\Htmlable ? $panel->toHtml() : e($panel) !!}
                @endif
            </aside>
        @endif
    </div>
</x-filament-panels::page>
