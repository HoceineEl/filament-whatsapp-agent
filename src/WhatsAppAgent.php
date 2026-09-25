<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent;

use Closure;
use HoceineEl\WhatsAppAgent\Agent\AgentContext;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point the package uses to reach the host app's profile, models and tenant scope.
 */
final class WhatsAppAgent
{
    public static function profile(): AgentProfile
    {
        return app(AgentProfile::class);
    }

    /**
     * @return list<object|class-string>
     */
    public static function resolveTools(AgentContext $context): array
    {
        return [...self::profile()->tools($context)];
    }

    /**
     * @return list<string>
     */
    public static function resolveKnowledge(AgentContext $context): array
    {
        return self::filled(self::profile()->knowledge($context));
    }

    /**
     * @return list<string>
     */
    public static function resolveLiveContext(AgentContext $context): array
    {
        return self::filled(self::profile()->liveContext($context));
    }

    /**
     * @return list<string>
     */
    public static function resolveCustomerFacts(AgentContext $context): array
    {
        return self::filled(self::profile()->customerFacts($context));
    }

    public static function blockedReason(AgentOwner $owner, Model $contact): ?string
    {
        return self::profile()->blockedReason($owner, $contact);
    }

    public static function intercept(Model $inbound, AgentContext $context): bool
    {
        return self::profile()->intercept($inbound, $context);
    }

    public static function hasHistory(Model $contact): bool
    {
        return self::profile()->hasHistory($contact);
    }

    public static function resetSandbox(AgentOwner $owner, Model $contact): void
    {
        self::profile()->resetSandbox($owner, $contact);
    }

    public static function contactPanel(Model $contact): Htmlable|string|null
    {
        return self::profile()->contactPanel($contact);
    }

    /**
     * @return list<string>
     */
    public static function suggestionsFor(AgentOwner $owner): array
    {
        return self::profile()->playgroundSuggestions($owner);
    }

    public static function canManage(AgentOwner $owner, ?Authenticatable $user): bool
    {
        return self::profile()->canManage($owner, $user);
    }

    public static function settingsUrl(): ?string
    {
        return self::profile()->settingsUrl();
    }

    /**
     * The chip under an assistant reply: the first used tool that has a label, else a generic one.
     *
     * @param  list<string>  $tools
     */
    public static function toolSummary(array $tools): string
    {
        $labels = self::profile()->toolLabels();
        $labelled = collect($labels)->keys()->first(fn (string $tool): bool => in_array($tool, $tools, true));

        return __($labelled === null ? 'whatsapp-agent::inbox.tool_used' : $labels[$labelled]);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function within(AgentOwner $owner, Closure $callback): mixed
    {
        return self::profile()->within($owner, $callback);
    }

    /**
     * @return class-string<Model&AgentOwner>
     */
    public static function ownerModel(): string
    {
        return config('whatsapp-agent.models.owner');
    }

    /**
     * @return class-string<Model>
     */
    public static function contactModel(): string
    {
        return config('whatsapp-agent.models.contact');
    }

    /**
     * @return class-string<Model>
     */
    public static function conversationModel(): string
    {
        return config('whatsapp-agent.models.conversation');
    }

    /**
     * @return class-string<Model>
     */
    public static function messageModel(): string
    {
        return config('whatsapp-agent.models.message');
    }

    /**
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        return config('whatsapp-agent.models.user');
    }

    /**
     * A query on one of the agent models, limited to the current Filament tenant when there is one.
     *
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    public static function tenantQuery(string $model): Builder
    {
        $tenant = filament()->getTenant();

        return $tenant instanceof AgentOwner ? $model::query()->forOwner($tenant) : $model::query();
    }

    public static function ownerKey(): string
    {
        return (string) config('whatsapp-agent.columns.owner', 'business_id');
    }

    public static function contactKey(): string
    {
        return (string) config('whatsapp-agent.columns.contact', 'contact_id');
    }

    /**
     * @param  iterable<string|null>  $items
     * @return list<string>
     */
    private static function filled(iterable $items): array
    {
        return array_values(array_filter([...$items], filled(...)));
    }
}
