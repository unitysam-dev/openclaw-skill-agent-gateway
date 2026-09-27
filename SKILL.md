---
name: "agent-gateway"
description: "Add real conversational Telegram/chat operation to the current V3 sandbox-loop skill."
title: Agent Gateway Registry Operations
trigger: When user mentions "agent gateway", "AGW", "registry", "/.well-known/agent", "action state", "availability_lookup", "reservation_intent_create", "payment_or_pms_handoff", sandbox demo booking, business agent profile work, or asks to book/find/reserve through Agent Gateway in chat or Telegram
---

# Agent Gateway Registry Operations

## Overview

Agent Gateway helps businesses become discoverable and usable by AI agents without forcing agents to scrape websites, bypass bot protections, or pretend to be human browsers. A business exposes only the specific functions it is happy for agents to use and keeps everything else private — reorganising the site for agents rather than redesigning the internet.

**Layering:** The registry is the discovery layer. The plugin is the onboarding and exposure layer. Business systems (and, in production, real payment/PMS providers) remain authoritative and execute the actual actions. Agent Gateway governs and exposes the action interface; it does not replace the business's own booking, payment, CRM, or inventory systems.

**Product stance (current):** The goal is automation — authorised agents completing real business workflows through business-approved rails. Human approval is one configurable policy mode, not a mandatory gate on every action. Payment is not taboo: Agent Gateway exposes/governs the action interface while business systems and payment providers execute authorised actions. Do not reintroduce blanket "Agent Gateway must never touch payment/booking" language.

## Version Map

- **v0.1** — frozen baseline (do not modify directly).
- **v0.2** — action-state model (detected/approved/verified) and metadata registry.
- **V3** — executable reservation orchestration: tokenized reservation intent, payment/PMS handoff, and (for demos) a sandbox loop-close that returns a fake confirmed booking.

## V3: Reservation Orchestration and Sandbox Loop-Close

V3 adds two agent-facing actions on top of the v2 metadata registry.

### V3 actions (discoverable via `/api/v1/actions`)

- `reservation_intent_create` → endpoint `agent-gateway/v1/reservation-intents`
- `payment_or_pms_handoff` → endpoint `agent-gateway/v1/payment-or-pms-handoff`

Both are advertised in the registry `ACTIONS` list and in the site's `/capabilities` and `/.well-known` discovery surfaces.

### The end-to-end agent loop

```
discovery -> catalogue -> availability -> reservation_intent_create
  -> payment_or_pms_handoff (prompt) -> "Yes, I verify" -> booking_confirmed
```

1. Agent discovers the business and its actions via the registry.
2. Agent reads availability and creates a reservation intent, receiving an `intent_token` (`intent_<64 hex>`) bound to a re-evaluated availability reference.
3. Agent calls the payment/PMS handoff with the intent token.

### Sandbox demo mode (fake, zero real side effects)

`sandbox_demo_mode` is an admin setting, **default OFF**. It exists only for controlled demonstration on a private test site; it uses no real payment provider, PMS, customer, or inventory system.

When enabled, the payment/PMS handoff becomes a two-stage flow:

1. **Prompt stage** — first handoff call returns `status: payment_verification_required` with `prompt: "Do you verify payment?"` and `expected_response: "Yes, I verify"`. No booking is created.
2. **Confirm stage** — a second handoff call with `payment_verification: "Yes, I verify"` returns `status: booking_confirmed`, `sandbox: true`, a `sandbox_confirmation_reference`, and writes an explicitly-labelled `sandbox_booking` record (status `completed`) into the plugin's Agent Requests admin list.

Guards (fail-closed):

- Only the exact string `Yes, I verify` advances; any other value returns HTTP 400.
- The confirm stage returns HTTP 409 if the prompt stage was never issued for that intent.
- Repeat confirms are idempotent and return the cached booking.
- All real side effects stay false: `payment_processed=false`, `reservation_hold_created=false`, `inventory_locked=false`, `inventory_decremented=false`. The `payment_verified`/`booking_created` flags that read `true` are explicitly fake, marked `sandbox_only: true`.

When sandbox mode is OFF, the handoff returns instructions-only `handoff_ready` with no side effects (production-safe default).

### Idempotency (important for autonomous agents)

Use a **fresh `idempotency_key` for each distinct request payload**. In particular, the payment-verification prompt request and the confirmation request must use **different** keys, because the payloads differ (the confirm adds `payment_verification`). Reusing the same key across the prompt and the confirm trips the payload-conflict guard and returns HTTP 409, stalling the loop. Reuse a key only to retry the exact same payload.

## Conversational Agent Operation (Telegram / Chat)

When the user asks to test or use Agent Gateway in a **real agent conversation**, do not substitute `demo/v3_agent_demo_flow.py`, a prerecorded transcript, or a one-shot scripted run. The conversation itself is the test surface.

### Required conversational behaviour

1. Treat the user's natural-language booking request as the start of the flow.
2. Discover matching businesses through the configured Agent Gateway registry. Do not jump directly to a known test-site endpoint unless the user explicitly asks for direct-site mode.
3. Present useful catalogue/availability results conversationally. Do not dump raw JSON unless requested.
4. Ask only for missing booking decisions (dates, room, guests, budget) and retain the selected business/room/availability reference in the current conversation state.
5. Before creating a reservation intent, briefly confirm the selected room, dates, guest count, and sandbox status.
6. Create the reservation intent with a fresh idempotency key and retain the returned intent token privately for the next turn.
7. Call the first payment/PMS handoff with its own fresh idempotency key.
8. If it returns `payment_verification_required`, relay the gateway's prompt to the user and **stop the turn**. Do not auto-answer it, infer consent, or run the confirmation call in the same turn.
9. Only after the user replies with the exact required phrase `Yes, I verify`, call the confirmation handoff using a new idempotency key and `payment_verification: "Yes, I verify"`.
10. Report success only if the returned response contains `status=booking_confirmed`, `sandbox=true`, `is_confirmation=true`, `confirmation_type=sandbox_booking`, and a `sandbox_booking_*` confirmation reference.
11. State plainly that the payment and booking are fake sandbox records and that `payment_processed=false`; do not imply money moved or real inventory changed.
12. Tell the user where to inspect the resulting record in WordPress: **Agent Gateway → Agent Requests**.

### Conversation-state rules

- Keep the intent token and idempotency keys out of normal chat unless the user asks for technical evidence.
- Never reuse the prompt idempotency key for confirmation.
- If the user changes dates, room, guest count, or other payload fields, restart from availability and create a new intent.
- If the conversation/session resets after intent creation, do not guess or reconstruct the token. Restart safely from availability.
- If registry discovery is unavailable, say that the conversational pathway is not ready; do not silently fall back to the demo script and claim the Telegram test passed.

### Acceptance standard for a real Telegram test

A real conversational test passes only when:

- the user initiated the request in ordinary language;
- the agent discovered the business via the registry;
- at least one user choice/confirmation occurred conversationally;
- the payment-verification prompt and user's exact reply occurred in separate chat turns;
- the agent returned the sandbox confirmation; and
- the corresponding `sandbox_booking` record is visible in WordPress admin.

## Architecture

### v0.1 (Frozen Baseline)

**Ports:** Registry API 8081; WordPress Plugin Site 8082; Test Instance 8083; Fallback 8084.
**Backup:** `/opt/agent-gateway-v0.1-working-20260607-0201.tar.gz`.
**CRITICAL:** Never modify v0.1 directly. All later work happens in a separate working copy/branch.

### Canonical repo and source of truth

The deployable project and cross-agent source of truth is the GitHub repo `unitysam-dev/agent-gateway`. Read `SOURCE_OF_TRUTH.md`, `AGENT_GATEWAY_CHECKPOINT.md`, and `docs/OPERATION_PROTOCOL.md` before handoff, deploy, registry, or plugin work. Handovers and checkpoints live in that repo, not in local scratch paths.

### Controlled test site

The Reggae Palace / `openclaw-bent...hstgr.cloud` WordPress instance is a private throwaway harness for testing the plugin and watching the loop close. It is never public. Do not gold-plate it; canned availability is fine as long as the loop visibly completes and shows in the plugin.

## Action State Model (v0.2)

Three states for every action:

1. **detected** — plugin found a compatible system (WooCommerce, Bookly, etc.).
2. **approved** — site owner explicitly allowed agents to access that action.
3. **verified** — Agent Gateway tested the action and confirmed it works.

**Detection results:** `supported_by_agent_gateway`, `not_yet_supported`, `custom_endpoint_required`.

**Search behaviour:** only **verified** actions are marked `available: true`. Detected/approved actions show their current state with human-readable messages. Search results imply a working action only when `verified`.

## Key Files

### Registry
- `registry/app.py` — main FastAPI application; `ACTIONS` list advertises available actions (now includes the V3 actions).
- `registry/schema.sql` / `registry/schema_v0.2.sql` — schemas.

### WordPress Plugin
- `wordpress-plugin/agent-gateway/agent-gateway.php` — main plugin file. Auto-detects WooCommerce, Bookly, Amelia, The Events Calendar, Contact Form 7, WPForms, Gravity Forms.
- V3 handlers live here: reservation-intent creation, payment/PMS handoff, and the sandbox loop-close.

### Demo/Test
- `demo/v3_agent_demo_flow.py` — scripted diagnostic only; supports `--verify-fake-payment`. It may verify endpoint mechanics, but it does **not** satisfy a request for a real Telegram/chat conversation.
- `demo/availability_adapter.py` — mock availability system.

## API Endpoints

### V3 executable endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `agent-gateway/v1/reservation-intents` | POST | Create a tokenized reservation intent bound to an availability reference |
| `agent-gateway/v1/payment-or-pms-handoff` | POST | Payment/PMS handoff; instructions-only by default, sandbox loop-close when `sandbox_demo_mode` is on |

### v0.2 action-state endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/actions` | GET | List advertised actions (includes V3 actions) |
| `/api/v1/actions/detect` | POST | Report detected actions from plugin |
| `/api/v1/actions/approve` | POST | Approve a detected action |
| `/api/v1/actions/verify` | POST | Verify an approved action works |
| `/api/v1/business/{id}` | GET | Business details with action states |
| `/api/v1/business/{id}/actions` | GET | All action states for a business |

### Core endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/register` | POST | Register a new site |
| `/api/v1/search` | GET | Search businesses |
| `/api/v1/verify` | POST | Verify domain ownership |
| `/health` | GET | Health check |

## Security Principles

- No admin access through Agent Gateway.
- No destructive actions unless explicitly approved and protected.
- Real payment/PMS execution belongs to the business's own systems and providers; Agent Gateway governs the interface. Sandbox demo mode performs only clearly-labelled fake payment verification and fake booking confirmation with no real side effects, and is default OFF.
- Rate limiting enforced at adapter level; per-action enablement required.
- Owner approval is a configurable policy mode, applied where the business chooses — not a blanket requirement on every action.

## Detection (Local Only)

Detection must happen locally inside WordPress. Never externally scan websites from the registry. Prefer "detect compatible integrations", "discover installed systems", "identify supported site functions"; avoid "scan".

## Operations Notes

- Prefer decisive action on clear imperative requests; summarise after. Do not stack confirmation questions when the request is unambiguous.
- For any deploy/registry/plugin change: inspect current state first, preserve a rollback path, verify after, and never touch production or run a production registry sync without explicit approval.

## Common Pitfalls

### Do not poll from the LLM loop

After submitting a request, do not have an expensive model reason every 30s to poll status. Submit, receive `request_id`, delegate to a lightweight polling worker with exponential backoff (15s, 30s, 60s, 2m, 5m, then every 10m up to ~12h), and wake the agent only on meaningful state change.

### Skill design: overlay pattern

Agent Gateway skills must be OVERLAYS, not replacements. Until AGW has mass adoption, run the AGW skill in parallel with normal search tools and merge results: AGW businesses show as executable ("Book Now"), web results show as informational links. Restricting a search to AGW-only produces poor coverage.

### WordPress plugin ZIP structure

The ZIP folder name must match the plugin slug exactly:

```
agent-gateway.zip
└── agent-gateway/
    └── agent-gateway.php
```

A versioned folder name (`agent-gateway-v2.0.0/`) causes "Invalid plugin slug / plugin could not be found". Always verify ZIP/source parity by SHA-256 before deploying.

### One business per site

The plugin supports ONE `registry_id` per site. For multiple test businesses, use separate WordPress instances, each with its own `registry_id`.

### Settings storage format

The plugin stores settings as a PHP array, not a JSON string:

```php
// CORRECT:
update_option('agw_settings', array(
  'registry_id' => 'kunming-live-music',
  'sandbox_demo_mode' => false,
));
// WRONG (double-encoded): update_option('agw_settings', json_encode($settings));
```

Verify with `wp option get agw_settings --format=json`.

### Apache/WordPress slug conflicts

A physical `/downloads/` directory conflicting with a WordPress page of the same slug returns 403 Forbidden. Rename the physical directory (and add a rewrite), serve via a PHP proxy, or use the WP media library. Verify with an HTTP status check expecting 200.

## References

- `references/v0.2-implementation-stages.md` — staged rollout plan.
- `references/action-state-model.md` — action state machine.
- `references/demo-adapter-spec.md` — availability adapter API.
- `references/booking-widget-implementation.md` — booking UI pattern.
- `references/business-onboarding-workflows.md` — plugin packaging and onboarding.
- `references/apache-wordpress-conflict-resolution.md` — 403/slug conflict fixes.
- Canonical repo `docs/` — V3 task specs and the demo loop-close evidence.
