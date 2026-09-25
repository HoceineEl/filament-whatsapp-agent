<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\PersonalSource;
use HoceineEl\WhatsAppAgent\Filament\Concerns\UsesPluginNavigation;
use HoceineEl\WhatsAppAgent\Messaging\HandoffService;
use HoceineEl\WhatsAppAgent\Messaging\MessageSender;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Inbox extends Page
{
    use UsesPluginNavigation;

    protected string $view = 'whatsapp-agent::pages.inbox';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::InboxStack;

    protected static ?int $navigationSort = 10;

    #[Url]
    public ?int $conversation = null;

    #[Url]
    public string $filter = 'all';

    public string $search = '';

    public string $reply = '';

    protected static function pluginKey(): string
    {
        return 'inbox';
    }

    public static function getNavigationLabel(): string
    {
        return __('whatsapp-agent::inbox.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('whatsapp-agent::inbox.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = WhatsAppAgent::tenantQuery(WhatsAppAgent::conversationModel())->waitingForHuman()->notPersonal()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    public function mount(): void
    {
        $this->conversation ??= $this->conversations->first()?->getKey();
        $this->markRead();
    }

    /**
     * @return Collection<int, Model>
     */
    #[Computed]
    public function conversations(): Collection
    {
        return WhatsAppAgent::tenantQuery(WhatsAppAgent::conversationModel())
            ->real()
            ->notPersonal()
            ->with(['contact', 'latestMessage'])
            ->when($this->filter === 'waiting', fn (Builder $query) => $query->withStatus(ConversationStatus::NeedsHuman, ConversationStatus::Human))
            ->when($this->filter === 'unread', fn (Builder $query) => $query->where('unread_count', '>', 0))
            ->when(filled($this->search), fn (Builder $query) => $query->whereHas('contact', fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $this->search).'%')))
            ->recent()
            ->limit(80)
            ->get();
    }

    #[Computed]
    public function active(): ?Model
    {
        return $this->conversation ? WhatsAppAgent::tenantQuery(WhatsAppAgent::conversationModel())->with('contact')->find($this->conversation) : null;
    }

    /**
     * @return Collection<int, Model>
     */
    #[Computed]
    public function messages(): Collection
    {
        if ($this->active === null) {
            return new Collection;
        }

        return $this->active->messages()->with('user')->latest('id')->limit(150)->get()->reverse()->values();
    }

    #[Computed]
    public function contactPanel(): Htmlable|string|null
    {
        return $this->active?->contact ? WhatsAppAgent::contactPanel($this->active->contact) : null;
    }

    public function open(int $conversationId): void
    {
        $this->conversation = $conversationId;
        $this->reply = '';
        unset($this->active, $this->messages, $this->contactPanel);
        $this->markRead();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'waiting', 'unread'], true) ? $filter : 'all';
    }

    public function send(MessageSender $sender, HandoffService $handoff): void
    {
        $text = trim($this->reply);

        if ($text === '' || $this->active === null) {
            return;
        }

        if ($this->active->status->botReplies()) {
            $handoff->takeOver($this->active, auth()->user());
        }

        $sender->send($this->active, $text, MessageAuthor::Staff, user: auth()->user());
        $this->reply = '';
        unset($this->messages, $this->active);
    }

    public function takeOver(HandoffService $handoff): void
    {
        if ($this->active !== null) {
            $handoff->takeOver($this->active, auth()->user());
            unset($this->active);
            Notification::make()->success()->title(__('whatsapp-agent::inbox.took_over'))->send();
        }
    }

    public function handBack(HandoffService $handoff): void
    {
        if ($this->active !== null) {
            $handoff->release($this->active);
            unset($this->active);
            Notification::make()->success()->title(__('whatsapp-agent::inbox.handed_back'))->send();
        }
    }

    public function markPersonal(): void
    {
        if ($this->active === null) {
            return;
        }

        $this->active->contact->markPersonal(PersonalSource::Owner);
        $this->conversation = null;
        unset($this->conversations, $this->active, $this->messages, $this->contactPanel);

        Notification::make()->success()->title(__('whatsapp-agent::inbox.marked_personal'))->send();
    }

    public function refreshThread(): void
    {
        unset($this->conversations, $this->messages, $this->active, $this->contactPanel);
        $this->markRead();
    }

    private function markRead(): void
    {
        if ($this->conversation !== null) {
            WhatsAppAgent::tenantQuery(WhatsAppAgent::conversationModel())->whereKey($this->conversation)->where('unread_count', '>', 0)->update(['unread_count' => 0]);
        }
    }
}
