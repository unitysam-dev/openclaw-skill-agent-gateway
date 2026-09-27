---
name: "agent-gateway"
description: "Use a simple green-light agent-friendly tag on merged search results; reveal capabilities only when relevant."
title: Agent Gateway Search Overlay and Operations
trigger: Automatically on every relevant search or discovery request involving businesses, products, services, accommodation, venues, appointments, availability, reservations, bookings, payments, enquiries, or local/commercial options; also when Agent Gateway, AGW, registry, /.well-known/agent, action state, availability_lookup, reservation_intent_create, payment_or_pms_handoff, or sandbox demo booking is mentioned. The user does not need to invoke Agent Gateway explicitly.
---

# Agent Gateway Search Overlay and Operations

## Mandatory Automatic Search Overlay

**Agent Gateway is an automatic execution overlay on normal search, never a replacement search engine or closed directory.**

Whenever a user searches for relevant businesses, products, services, accommodation, venues, appointments, availability, reservations, bookings, payment options, enquiries, or other local/commercial choices:

1. Run the normal search path unchanged using the standard web/search/platform tools that ordinarily apply.
2. At the same time, query the configured Agent Gateway registry for matching businesses and capabilities.
3. Do this automatically. The user does not need to mention, invoke, or know about Agent Gateway.
4. Merge the two result sets.
5. Preserve ordinary search results even when they are not registered with Agent Gateway.
6. Add a simple green-light marker to Agent Gateway-enabled results: **🟢**.
7. Explain once, briefly, if needed: **🟢 means this business is agent-friendly.**
8. Do not dump capability names, action-state metadata, endpoint details, or technical labels into the default result list.
9. Reveal available actions only after the user selects the result, asks what the agent can do, or the next conversational step requires an action.
10. Do not add red lights, negative labels, or “information only” warnings to ordinary results. They remain normal search results.
11. If the registry returns no matches, still return the complete normal-search result.
12. Never imply registry coverage is comprehensive.
13. Never show only registry results unless the user explicitly asks for Agent-Gateway-only results.

### Result-merging rules

- Deduplicate the same business when it appears in normal search and Agent Gateway.
- Keep useful normal-search context such as reviews, editorial information, maps, pricing signals, and public webpages.
- Add only the green marker to the matching result by default.
- Do not automatically rank a result first merely because it is registered. Rank for user relevance.
- Treat the green marker as a quiet affordance: it tells the user the agent can interact with that business if selected.
- After selection, inspect the verified action state and explain only the actions relevant to the user's request.

### Example default search presentation

```text
1. Yunnan Reggae Palace 🟢
   Short normal-search description, location, price/review context.

2. Example Hotel
   Short normal-search description, location, price/review context.

🟢 Agent-friendly
```

Do not append a capability catalogue to item 1 unless the user selects it or asks.

## Overview

Agent Gateway helps businesses become discoverable and usable by AI agents without forcing agents to scrape websites, bypass bot protections, or pretend to be human browsers. A business exposes only the specific functions it allows and keeps everything else private.

**Layering:** The registry is the discovery layer. The plugin is the onboarding and exposure layer. Business systems and real payment/PMS providers remain authoritative. Agent Gateway governs and exposes the action interface; it does not replace the business's booking, payment, CRM, or inventory systems.

**Product stance:** Authorised agents should complete real business workflows through business-approved rails. Human approval is one configurable policy mode, not a mandatory gate for every action. Payment may be supported through business systems/providers.

## Version Map

- **v0.1** — frozen baseline.
- **v0.2** — action states: detected, approved, verified.
- **V3** — reservation orchestration: tokenized intent, payment/PMS handoff, approval policy, and sandbox loop-close.

## V3 Reservation Orchestration and Sandbox Loop-Close

### V3 actions

- `reservation_intent_create` → `agent-gateway/v1/reservation-intents`
- `payment_or_pms_handoff` → `agent-gateway/v1/payment-or-pms-handoff`

### End-to-end loop

```text
discovery -> catalogue -> availability -> reservation_intent_create
  -> owner policy/approval state -> payment_or_pms_handoff prompt
  -> user says "Yes, I verify" -> booking_confirmed
```

### Approval policy rule

Payment must not begin merely because an intent exists.

- If owner auto-approval is enabled and the request satisfies the configured policy, record the auto-approval and proceed.
- If auto-approval is disabled, return/wait in an owner-approval state.
- If the owner proposes changes, relay them to the user and obtain acceptance before creating/revising the intent and proceeding.
- If approval is pending, changed, rejected, stale, or mismatched to the current booking terms, do not issue the payment prompt.

### Sandbox demo mode

`sandbox_demo_mode` is default OFF and uses no real payment provider, PMS, customer, or inventory system.

When enabled and approval policy permits progression:

1. First handoff call returns `payment_verification_required`, prompt `Do you verify payment?`, and expected response `Yes, I verify`.
2. A later confirmation call with `payment_verification: "Yes, I verify"` returns `booking_confirmed`, `sandbox=true`, a `sandbox_booking_*` reference, and writes a labelled sandbox record into WordPress Agent Requests.

Guards:

- Only exact `Yes, I verify` advances.
- Prompt and confirmation occur in separate turns.
- Confirmation fails if the prompt was not issued.
- Repeat confirmations are idempotent.
- `payment_processed=false`, `reservation_hold_created=false`, `inventory_locked=false`, and `inventory_decremented=false` remain false.
- `payment_verified=true` and `booking_created=true` mean fake sandbox state only and require `sandbox_only=true`.

### Idempotency

Use a fresh `idempotency_key` for each distinct request payload. Prompt and confirmation payloads require different keys. Reuse a key only for an exact retry.

## Conversational Agent Operation (Telegram / Chat)

When the user asks to test or use Agent Gateway in a real agent conversation, do not substitute the Python demo script, prerecorded transcript, or one-shot scripted run.

### Required conversational behaviour

1. Treat the user's natural-language request as the start.
2. Run normal search and Agent Gateway search in parallel.
3. Merge results and add only **🟢** to agent-friendly matches by default.
4. Ask only for missing decisions such as dates, room, guests, or budget.
5. After the user selects a green-marked result, explain only the capabilities relevant to that request.
6. Retain selected business, room, dates, guests, price, and availability reference in current conversation state.
7. Confirm selected terms and whether the flow is sandbox or real before intent creation.
8. Create intent with a fresh idempotency key and retain the token privately.
9. Respect the owner's approval policy. If approval is pending, tell the user and wait/check later; do not prompt for payment.
10. If the owner proposes changes, show those changes and wait for user acceptance before revising the intent.
11. Call payment/PMS handoff only after the applicable approval state permits it.
12. If handoff returns `payment_verification_required`, relay the prompt and stop the turn.
13. Only after the user replies with exact `Yes, I verify`, call confirmation with a new key.
14. Report success only when response contains `booking_confirmed`, `sandbox=true`, `is_confirmation=true`, `confirmation_type=sandbox_booking`, and a `sandbox_booking_*` reference.
15. State plainly that sandbox payment/booking are fake and `payment_processed=false`.
16. Tell the user where to inspect the record in WordPress Agent Requests.

### Conversation-state rules

- Keep intent tokens and idempotency keys out of normal chat unless requested.
- Never reuse the prompt key for confirmation.
- If terms change, restart from availability and create/revise the intent safely.
- If the session resets after intent creation, do not reconstruct or guess the token.
- If registry discovery is unavailable, say the conversational pathway is not ready. Do not silently substitute the demo script.

### Real Telegram acceptance standard

A test passes only when:

- the user initiated an ordinary natural-language search;
- normal and registry search both ran;
- merged results used the simple green marker;
- the business was discovered, not hard-coded;
- actual catalogue/media/availability data supported the requested actions;
- owner manual or auto-approval policy was correctly enforced;
- prompt and user verification occurred in separate turns;
- sandbox confirmation returned; and
- the WordPress record displayed complete booking details.

## Architecture and Source of Truth

- Project: `https://github.com/unitysam-dev/agent-gateway`
- Skill: `https://github.com/unitysam-dev/openclaw-skill-agent-gateway`
- Read project checkpoint, source of truth, and operation protocol before implementation/deployment work.
- Reggae Palace/openclaw-bent is a controlled test harness, not production.

## Action State Model

1. **detected** — compatible system/action found.
2. **approved** — owner allowed access.
3. **verified** — action was tested and confirmed.

Only sufficiently verified actions make a result eligible for the green marker. Do not expose action-state jargon in default search output.

## Key Endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/v1/search` | GET | Search registered businesses |
| `/api/v1/actions` | GET | List advertised actions |
| `/api/v1/business/{id}` | GET | Business/action details |
| `agent-gateway/v1/reservation-intents` | POST | Create reservation intent |
| `agent-gateway/v1/payment-or-pms-handoff` | POST | Handoff/confirmation flow |

## Security and Operational Rules

- No admin access through Agent Gateway.
- No destructive actions without approval and rollback.
- Detection occurs locally in WordPress; registry must not externally scan sites.
- Real business systems remain authoritative.
- Never expose secrets in chat, search results, capabilities, or logs.
- Inspect first, preserve rollback, verify after, and do not touch production without approval.

## Common Pitfalls

### Registry-only search

Registry-only search is a product failure unless explicitly requested. Always preserve normal-search coverage.

### Overloading search results

Do not turn search results into technical capability reports. Use the green marker only. Explain actions after selection.

### Demo script substitution

`demo/v3_agent_demo_flow.py` is diagnostic only and does not satisfy a real Telegram test.

### WordPress plugin ZIP structure

The ZIP must contain `agent-gateway/agent-gateway.php`. Verify source/package SHA-256 parity.

### One business per site

The plugin supports one `registry_id` per WordPress site.

## References

- Canonical project `docs/` and V3 task/evidence files.
- `references/v0.2-implementation-stages.md`
- `references/demo-adapter-spec.md`
- `references/booking-widget-implementation.md`
- `references/business-onboarding-workflows.md`
- `references/apache-wordpress-conflict-resolution.md`
