# Changelog

## Unreleased

- Single-tenant mode: a built-in `WhatsAppAccount` owner for panels without tenancy, chosen in the installer. The inbox, playground and connection pages resolve the owner through `WhatsAppAgent::currentOwner()`.
- The installer asks about tenancy before publishing, so migrations run with the right owner table.
- Customers who opted out with STOP no longer receive assistant replies until they opt back in; keywords also match with trailing punctuation.
- A retried reply job no longer answers the same message twice.
- The reply job now runs on the queue set in `whatsapp-agent.queue`.
- HTTP calls to WhatsApp only retry network drops, rate limits and gateway errors, not rejected requests.

## 1.0.0 - 2026-09-25

- AI assistant for multi-tenant Filament 5 apps, built on `laravel/ai` with Gemini failover.
- Evolution API (QR or pairing code), WhatsApp Cloud API and simulator channels.
- Shared inbox with take-over, hand-back and handoff alerts on the owner's phone.
- Voice notes, photos and PDFs understood; voice note replies.
- Dialect memory, personal-number screening, daily reply limits and a playground.
- English, Arabic and French.
