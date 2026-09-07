# AI

## Surfaces

- Workspace AI settings UI (`workspace/ai-settings`).
- Public/member assistant endpoint `POST /assistant/chat` (`AssistantController` + `WebsiteAssistantService`).
- POS menu AI chat routes (separate, table/general menu).
- Entitlement feature `ai` + meters `ai_usage` / `ai_tokens`.

## Providers

Configured via `config/ai.php` and `services.google_ai_studio` / OpenAI settings. Default provider often Google AI Studio; missing key falls back to deterministic Arabic helper replies.

## Hardening (current)

- No product stock in public assistant context.
- Products only when: authenticated workspace member **or** published website resolved for the request host.
- System prompt blocks secrets/credentials and cross-workspace disclosure.
- Controller enforces max messages/minute via `RateLimiter` (plus route throttle).

## Honest status

Assistant is usable for onboarding/FAQ; quality depends on configured provider keys. Do not treat fallback replies as live LLM output.
