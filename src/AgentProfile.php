<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent;

use Closure;
use HoceineEl\WhatsAppAgent\Agent\AgentContext;
use HoceineEl\WhatsAppAgent\Agent\Tools\AgentTool;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Plugs your business into the agent. Extend it, override what you need and set it as `profile` in config/whatsapp-agent.php.
 */
class AgentProfile
{
    /**
     * @return iterable<class-string<AgentTool>|AgentTool>
     */
    public function tools(AgentContext $context): iterable
    {
        return [];
    }

    /**
     * Facts and rules that rarely change. They sit before the per-call details so the prompt prefix can be cached.
     *
     * @return iterable<string|null>
     */
    public function knowledge(AgentContext $context): iterable
    {
        return [];
    }

    /**
     * Details that change on every call, placed at the end of the prompt.
     *
     * @return iterable<string|null>
     */
    public function liveContext(AgentContext $context): iterable
    {
        return [];
    }

    /**
     * Extra lines under "Customer" in the prompt, e.g. "Recent orders: SO-00012".
     *
     * @return iterable<string|null>
     */
    public function customerFacts(AgentContext $context): iterable
    {
        return [];
    }

    /**
     * A reason to stop the assistant replying (e.g. an unpaid plan), or null to let it reply.
     */
    public function blockedReason(AgentOwner $owner, Model $contact): ?string
    {
        return null;
    }

    /**
     * Handle an inbound message before the AI sees it (e.g. reminder buttons). Return true when it was handled.
     */
    public function intercept(Model $inbound, AgentContext $context): bool
    {
        return false;
    }

    /**
     * Whether the contact already did business with you, so it is never screened as personal.
     */
    public function hasHistory(Model $contact): bool
    {
        return false;
    }

    /**
     * Delete what a playground chat created (test bookings, orders).
     */
    public function resetSandbox(AgentOwner $owner, Model $contact): void {}

    /**
     * Shown beside a conversation in the inbox (upcoming bookings, recent orders).
     */
    public function contactPanel(Model $contact): Htmlable|string|null
    {
        return null;
    }

    /**
     * Chips under assistant replies in the inbox, in priority order.
     *
     * @return array<string, string> tool name => translation key
     */
    public function toolLabels(): array
    {
        return [];
    }

    /**
     * Who may change the WhatsApp connection.
     */
    public function canManage(AgentOwner $owner, ?Authenticatable $user): bool
    {
        return $user !== null;
    }

    /**
     * Run agent work outside a request (queue jobs, webhooks) inside the owner's tenant scope.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function within(AgentOwner $owner, Closure $callback): mixed
    {
        return $callback();
    }

    /**
     * Where the owner tunes the assistant; linked from the playground.
     */
    public function settingsUrl(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function playgroundSuggestions(AgentOwner $owner): array
    {
        return (array) __('whatsapp-agent::playground.suggestions');
    }
}
