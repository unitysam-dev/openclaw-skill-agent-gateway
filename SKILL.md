---
name: "agent-gateway"
description: "Make Agent Gateway an automatic parallel overlay on normal search, with actionable results clearly marked."
title: Agent Gateway Search Overlay and Operations
trigger: Automatically on every relevant search or discovery request involving businesses, products, services, accommodation, venues, appointments, availability, reservations, bookings, payments, enquiries, or local/commercial options; also when Agent Gateway, AGW, registry, /.well-known/agent, action state, availability_lookup, reservation_intent_create, payment_or_pms_handoff, or sandbox demo booking is mentioned. The user does not need to invoke Agent Gateway explicitly.
---

# Agent Gateway Search Overlay and Operations

## Mandatory Automatic Search Overlay

**This is the governing product rule. Agent Gateway is an automatic execution overlay on normal search, never a replacement search engine or closed directory.**

Whenever a user searches for relevant businesses, products, services, accommodation, venues, appointments, availability, reservations, bookings, payment options, enquiries, or other local/commercial choices:

1. Run the normal search path unchanged using the standard web/search/platform tools that would ordinarily apply.
2. At the same time, query the configured Agent Gateway registry for matching businesses and capabilities.
3. Do this automatically. The user does not need to mention, invoke, or know about Agent Gateway.
4. Merge the two result sets into one useful answer.
5. Preserve ordinary search results even when they are not registered with Agent Gateway.
6. Clearly label Agent Gateway results as **Agent-friendly** or **Actionable by agent** and list the actions actually available, such as:
   - Check live availability
   - Create reservation intent
   - Complete authorised booking/payment flow
   - Send enquiry
   - Request appointment
7. Present non-Agent-Gateway results normally as informational/web results. Do not describe them as inferior; simply do not claim executable actions that have not been verified.
8. If the registry returns no matches, still return the complete normal-search result. Do not reduce coverage or fail the search.
9. Never imply the Agent Gateway registry is comprehensive. Until adoption is broad, its role is to enrich search with executable options.
10. Never show only registry results unless the user explicitly asks for Agent-Gateway-only results.

### Result-merging rules

- Deduplicate the same business when it appears in both normal search and Agent Gateway.
- Keep useful normal-search context such as reviews, editorial information, maps, pricing signals, and public webpages.
- Add Agent Gateway capability data to the matching result rather than showing a confusing duplicate.
- Give actionable status only when the corresponding action is declared and sufficiently verified under the registry/action-state rules.
- Make the difference visible in concise language, for example:
  - **Agent-friendly — availability and booking supported**
  - **Agent-friendly — enquiry supported**
  - **Web result — information only**
- Do not automatically rank an Agent Gateway result first merely because it is registered. Rank for user relevance, then make actionability clear.

### Example merged answer pattern

```text
1. Yunnan Reggae Palace — Agent-friendly
   Available agent actions: check availability, create reservation intent, complete authorised sandbox booking.
   Normal-search context: location, room information, reviews, public website.

2. Example Hotel — Web result
   Public information and booking link available; no verified agent actions found.
```

## Overview

Agent Gateway helps businesses become discoverable and usable by AI agents without forcing agents to scrape websites, bypass bot protections, or pretend to be human browsers. A business exposes only the specific functions it is happy for agents to use and keeps everything else private — reorganising the site for agents rather than redesigning the internet.

**Layering:** The registry is the discovery layer. The plugin is the onboarding and exposure layer. Business systems (and, in production, real payment/PMS providers) remain authoritative and execute the actual actions. Agent Gateway governs and exposes the action interface; it does not replace the business's own booking, payment, CRM, or inventory systems.

**Product stance:** The goal is automation — authorised agents completing real business workflows through business-approved rails. Human approval is one configurable policy mode, not a mandatory gate on every action. Payment is not taboo: Agent Gateway exposes/governs the action interface while business systems and payment providers execute authorised actions.

## Version Map

- **v0.1** — frozen baseline.
- **v0.2** — action-state model: detected, approved, verified.
- **V3** — executable reservation orchestration: tokenized reservation intent, payment/PMS handoff, and sandbox loop-close.

## V3 Reservation Orchestration and Sandbox Loop-Close

### V3 actions

- `reservation_intent_create` → `agent-gateway/v1/reservation-intents`
- `payment_or_pms_handoff` → `agent-gateway/v1/payment-or-pms-handoff`

### End-to-end loop

```text
discovery -> catalogue -> availability -> reservation_intent_create
  -> payment_or_pms_handoff prompt -> user says "Yes, I verify"
  -> booking_confirmed
```

### Sandbox demo mode

`sandbox_demo_mode` is default OFF. It is only for controlled demonstration and uses no real payment provider, PMS, customer, or inventory system.

When enabled:

1. First handoff call returns `payment_verification_required`, prompt `Do you verify payment?`, and expected response `Yes, I verify`.
2. A later confirmation call with `payment_verification: "Yes, I verify"` returns `booking_confirmed`, `sandbox=true`, a `sandbox_booking_*` confirmation reference, and writes a labelled `sandbox_booking` record into WordPress Agent Requests.

Guards:

- Only the exact string `Yes, I verify` advances.
- Confirmation fails if the prompt was not previously issued.
- Repeat confirmations are idempotent.
- `payment_processed=false`, `reservation_hold_created=false`, `inventory_locked=false`, and `inventory_decremented=false` remain false.
- `payment_verified=true` and `booking_created=true` mean fake sandbox state only and must be accompanied by `sandbox_only=true`.

When sandbox mode is OFF, handoff remains external/instructions-only unless a real configured business rail provides structured completion evidence.

### Idempotency

Use a fresh `idempotency_key` for each distinct request payload. The payment prompt request and confirmation request have different payloads and must use different keys. Reuse a key only for an exact retry.

## Conversational Agent Operation (Telegram / Chat)

When the user asks to test or use Agent Gateway in a real agent conversation, do not substitute the Python demo script, a prerecorded transcript, or a one-shot scripted run. The conversation itself is the test surface.

### Required conversational behaviour

1. Treat the user's natural-language request as the start of the flow.
2. Run normal search and Agent Gateway registry search in parallel.
3. Present merged options conversationally, clearly marking agent-friendly results and available executable actions.
4. Ask only for missing decisions such as dates, room, guests, or budget.
5. Retain the selected business, room, dates, guest count, and availability reference in current conversation state.
6. Before intent creation, briefly confirm the selected details and whether the flow is sandbox or real.
7. Create the reservation intent with a fresh idempotency key; retain the intent token privately.
8. Call the first payment/PMS handoff with another fresh key.
9. If it returns `payment_verification_required`, relay the prompt and stop the turn. Do not auto-answer, infer consent, or confirm in the same turn.
10. Only after the user replies with exact required phrase `Yes, I verify`, call confirmation using a new key.
11. Report success only when response contains `status=booking_confirmed`, `sandbox=true`, `is_confirmation=true`, `confirmation_type=sandbox_booking`, and a `sandbox_booking_*` reference.
12. State plainly that sandbox payment and booking are fake and `payment_processed=false`.
13. Tell the user to inspect **Agent Gateway → Agent Requests** in WordPress.

### Conversation-state rules

- Keep intent tokens and idempotency keys out of normal chat unless technical evidence is requested.
- Never reuse the prompt key for confirmation.
- If dates, room, guests, or payload fields change, restart from availability and create a new intent.
- If the session resets after intent creation, do not reconstruct or guess the token; restart safely.
- If registry discovery is unavailable, say the conversational pathway is not ready. Do not silently substitute the demo script and claim success.

### Real Telegram acceptance standard

The test passes only when:

- the user initiated an ordinary natural-language search/request;
- normal search and registry search both ran;
- results were merged and actionable businesses were labelled;
- the business was discovered through Agent Gateway rather than hard-coded;
- at least one user choice occurred conversationally;
- payment prompt and exact user reply occurred in separate Telegram turns;
- the agent returned sandbox confirmation; and
- the `sandbox_booking` record is visible in WordPress admin.

## Architecture and Source of Truth

- Deployable project: `https://github.com/unitysam-dev/agent-gateway`
- Skill repository: `https://github.com/unitysam-dev/openclaw-skill-agent-gateway`
- Read project `AGENT_GATEWAY_CHECKPOINT.md`, `SOURCE_OF_TRUTH.md`, and `docs/OPERATION_PROTOCOL.md` before implementation, deployment, registry, or plugin work.
- Cross-agent handovers belong in the canonical project repository, not local scratch paths.
- The Reggae Palace/openclaw-bent WordPress instance is a private controlled test harness, not a production business.

## Action State Model

1. **detected** — compatible system/action found.
2. **approved** — owner allowed agent access.
3. **verified** — Agent Gateway tested and confirmed the action.

Only verified actions should be presented as fully available/executable. Detected or approved states may be shown with accurate status but must not be overstated.

## Key Endpoints

### V3 executable endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `agent-gateway/v1/reservation-intents` | POST | Create tokenized reservation intent |
| `agent-gateway/v1/payment-or-pms-handoff` | POST | Handoff or sandbox confirmation flow |

### Registry endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/v1/search` | GET | Search registered businesses |
| `/api/v1/actions` | GET | List advertised actions |
| `/api/v1/business/{id}` | GET | Business details and action states |
| `/api/v1/business/{id}/actions` | GET | Business action states |
| `/api/v1/actions/detect` | POST | Report detected actions |
| `/api/v1/actions/approve` | POST | Approve action |
| `/api/v1/actions/verify` | POST | Verify action |

## Security and Operational Rules

- No admin access through Agent Gateway.
- No destructive actions without explicit approval and rollback.
- Detection occurs locally in WordPress; registry must not externally scan sites.
- Real business systems remain authoritative.
- Never expose secrets in chat, search results, public capability documents, or logs.
- For deployment or registry changes: inspect first, preserve rollback, verify after, and do not touch production without approval.

## Common Pitfalls

### Registry-only search

Registry-only search is a product failure unless explicitly requested. It discards normal-search coverage and makes Agent Gateway useless before mass adoption. Always use the mandatory automatic overlay pattern.

### Demo script substitution

`demo/v3_agent_demo_flow.py` is a diagnostic tool. It can verify endpoint mechanics but does not satisfy a real conversational Telegram test.

### Polling from the LLM loop

Do not reason every few seconds to poll. Delegate longer status polling to a lightweight worker with exponential backoff and wake the agent on meaningful state change.

### WordPress plugin ZIP structure

The ZIP must contain:

```text
agent-gateway.zip
└── agent-gateway/
    └── agent-gateway.php
```

Verify source/package SHA-256 parity before deployment.

### One business per site

The plugin supports one `registry_id` per WordPress site. Use separate test instances for separate businesses.

### Settings format

WordPress stores Agent Gateway settings as a PHP array, not a JSON string.

## References

- Canonical project `docs/` and V3 task/evidence files.
- `references/v0.2-implementation-stages.md`
- `references/demo-adapter-spec.md`
- `references/booking-widget-implementation.md`
- `references/business-onboarding-workflows.md`
- `references/apache-wordpress-conflict-resolution.md`
