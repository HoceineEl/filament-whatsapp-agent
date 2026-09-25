# Filament WhatsApp Agent

An AI assistant that answers your customers on WhatsApp, inside any multi-tenant Filament 5 app.

- **Channels:** Evolution API (link by QR or pairing code), the official Cloud API, and a simulator.
- **Inbox:** one shared inbox with take-over and hand-back, and handoff alerts on the owner's phone.
- **Media:** understands voice notes, photos and PDFs, and can reply with voice notes.
- **Conversation:** remembers each customer's dialect.
- **Shared personal numbers:** screens out family and friends when the business uses the owner's personal number.
- **Testing:** a playground to chat with the assistant without sending anything on WhatsApp.
- **Languages:** English, Arabic and French.

Built on [laravel/ai](https://github.com/laravel/ai), using Gemini with automatic failover. Your app supplies the business logic through one profile class: tools, prompt knowledge, reply gates and inbox panels.

## Install

```bash
composer require hoceineel/filament-whatsapp-agent
php artisan whatsapp-agent:install
```

The installer:

- publishes the config and the migration, then offers to run it;
- creates your profile class in `app/Ai` and registers it in the config;
- publishes the CSS.

The migration adds the WhatsApp columns to your owner (tenant) table. It also creates the `whatsapp_contacts`, `whatsapp_conversations` and `whatsapp_messages` tables. If you already have tables for contacts, conversations and messages, point `models`, `tables` and `columns` in the config at your own models instead. Those models use the `IsAgentContact`, `IsAgentConversation` and `IsAgentMessage` traits.

After the installer, two changes in your code:

```php
class Business extends Model implements AgentOwner
{
    use HasWhatsAppAgent;

    public function agentRole(): string { return 'sales assistant'; }
    public function agentDescription(): string { return 'a grocery store in Casablanca'; }
}

$panel->plugin(WhatsAppAgentPlugin::make()
    ->navigationGroups(['inbox' => 'sales', 'playground' => 'sales', 'connection' => 'settings']));
```

## Your agent profile

One class connects your business to the agent. Override only what you need. Every method is typed and has a safe default.

```php
class ShopAgentProfile extends AgentProfile
{
    public function tools(AgentContext $context): iterable
    {
        return [SearchProductsTool::class, PlaceOrderTool::class]; // extend AgentTool
    }

    public function knowledge(AgentContext $context): iterable
    {
        return ["## Store facts\n- Delivery: 20 MAD"]; // cached prompt prefix
    }

    public function blockedReason(AgentOwner $owner, Model $contact): ?string
    {
        return $owner->hasSubscription() ? null : 'No active plan';
    }
}
```

The other methods you can override:

- `liveContext`: per-call details at the end of the prompt.
- `customerFacts`: extra lines about the customer.
- `intercept`: answer reminder buttons without the AI.
- `hasHistory`: never screen existing customers as personal contacts.
- `resetSandbox`: delete test data when the playground resets.
- `contactPanel`: a sidebar panel in the inbox.
- `toolLabels`: labels for tool activity under replies.
- `canManage`: who may change the WhatsApp connection.
- `within`: tenant binding for queue jobs and webhooks.
- `settingsUrl`: where the assistant is configured.
- `playgroundSuggestions`: sample messages for the playground.

Generate another profile class with `php artisan make:whatsapp-agent-profile ShopAgentProfile`.

## Environment

```
GEMINI_API_KEY=
EVOLUTION_API_URL=
EVOLUTION_API_KEY=
WHATSAPP_WEBHOOK_BASE_URL=   # public URL (tunnel) when developing locally
```

Replies run on the queue, so keep a worker running.

## Testing

```bash
composer test
```

In your app's tests, fake the AI with `AssistantAgent::fake([...])` and `MessageScreenerAgent::fake([...])`.
