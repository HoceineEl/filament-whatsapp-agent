<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Messaging;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Filament\Pages\Inbox;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Model;

class HandoffService
{
    public function __construct(private readonly OwnerNotifier $owner) {}

    public function handOff(Model $conversation, string $reason): void
    {
        if ($conversation->status !== ConversationStatus::Bot) {
            return;
        }

        $conversation->forceFill([
            'status' => ConversationStatus::NeedsHuman,
            'handoff_reason' => mb_substr($reason, 0, 250),
            'handed_off_at' => now(),
        ])->save();

        $owner = $conversation->owner;
        $customer = $conversation->contact;
        $inboxUrl = rescue(fn (): string => Inbox::getUrl(['conversation' => $conversation->getKey()], panel: config('whatsapp-agent.panel'), tenant: WhatsAppAgent::isSingleTenant() ? null : $owner), report: false);

        Notification::make()
            ->title(__('whatsapp-agent::conversations.handoff.notification_title', ['name' => $customer->displayName()], $owner->agentLocale()))
            ->body($reason)
            ->icon(Heroicon::OutlinedHandRaised)
            ->danger()
            ->actions(array_filter([
                $inboxUrl ? Action::make('open')
                    ->label(__('whatsapp-agent::conversations.handoff.open', locale: $owner->agentLocale()))
                    ->url($inboxUrl) : null,
            ]))
            ->sendToDatabase(collect($owner->agentNotifiables()));

        $this->owner->notify($owner, __('whatsapp-agent::conversations.handoff.owner_whatsapp', [
            'name' => $customer->displayName(),
            'phone' => '+'.$customer->phone,
            'reason' => $reason,
        ], $owner->agentLocale()));
    }

    public function takeOver(Model $conversation, Model $user): void
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Human,
            'assigned_user_id' => $user->getKey(),
            'handed_off_at' => $conversation->handed_off_at ?? now(),
        ])->save();
    }

    public function pauseForOwnerPhone(Model $conversation): void
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Human,
            'handoff_reason' => __('whatsapp-agent::conversations.handoff.owner_phone', locale: $conversation->owner->agentLocale()),
            'handed_off_at' => $conversation->handed_off_at ?? now(),
            'unread_count' => 0,
        ])->save();
    }

    /**
     * A handoff caused only by the AI being unavailable ends on the customer's next message, unless the team already replied.
     */
    public function resumeAfterAiFailure(Model $conversation): void
    {
        $reason = __('whatsapp-agent::conversations.handoff.ai_failed', locale: $conversation->owner->agentLocale());

        if ($conversation->status !== ConversationStatus::NeedsHuman || $conversation->handoff_reason !== $reason) {
            return;
        }

        $teamReplied = $conversation->messages()
            ->outbound()
            ->byAuthor(MessageAuthor::Staff)
            ->where('created_at', '>=', $conversation->handed_off_at)
            ->exists();

        if (! $teamReplied) {
            $this->release($conversation);
        }
    }

    public function release(Model $conversation): void
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Bot,
            'handoff_reason' => null,
            'handed_off_at' => null,
            'assigned_user_id' => null,
        ])->save();
    }
}
