<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use HoceineEl\WhatsAppAgent\Agent\AgentRuntime;
use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Filament\Concerns\UsesPluginNavigation;
use HoceineEl\WhatsAppAgent\Messaging\ConversationRecorder;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

/**
 * Chat with your own assistant as a customer would, without WhatsApp. Messages go through the real pipeline.
 */
class Playground extends Page
{
    use UsesPluginNavigation;

    protected string $view = 'whatsapp-agent::pages.playground';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 10;

    public string $draft = '';

    protected static function pluginKey(): string
    {
        return 'playground';
    }

    public static function canAccess(): bool
    {
        return WhatsAppAgent::currentOwner() !== null && parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('whatsapp-agent::playground.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('whatsapp-agent::playground.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('whatsapp-agent::playground.subheading');
    }

    /**
     * @return list<string>
     */
    public function suggestions(): array
    {
        return WhatsAppAgent::suggestionsFor(WhatsAppAgent::currentOwner());
    }

    #[Computed]
    public function conversation(): ?Model
    {
        return WhatsAppAgent::tenantQuery(WhatsAppAgent::conversationModel())->whereHas('contact', fn (Builder $query) => $query->withPhone($this->sandboxPhone()))->first();
    }

    /**
     * @return Collection<int, Model>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->conversation?->messages()->orderBy('id')->get() ?? new Collection;
    }

    public function send(?string $text = null): void
    {
        $text = trim($text ?? $this->draft);

        if ($text === '') {
            return;
        }

        /** @var AgentOwner $owner */
        $owner = WhatsAppAgent::currentOwner();

        $message = app(ConversationRecorder::class)->recordInbound($owner, new InboundMessage(
            providerMessageId: 'play_'.Str::ulid(),
            from: $this->sandboxPhone(),
            name: auth()->user()->name,
            type: MessageType::Text,
            text: $text,
        ));

        $this->draft = '';

        if ($message !== null) {
            app(AgentRuntime::class)->respond($message);
        }

        unset($this->conversation, $this->messages);
    }

    public function resetChat(): void
    {
        $contact = WhatsAppAgent::tenantQuery(WhatsAppAgent::contactModel())->withPhone($this->sandboxPhone())->first();

        if ($contact !== null) {
            WhatsAppAgent::resetSandbox(WhatsAppAgent::currentOwner(), $contact);
            $contact->conversation?->delete();
            method_exists($contact, 'forceDelete') ? $contact->forceDelete() : $contact->delete();
        }

        unset($this->conversation, $this->messages);
    }

    private function sandboxPhone(): string
    {
        return WhatsAppAgent::contactModel()::sandboxPhone((int) WhatsAppAgent::currentOwner()->getKey(), (int) auth()->id());
    }
}
