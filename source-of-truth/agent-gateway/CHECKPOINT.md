# Agent Gateway v0.2 Shared Checkpoint

**Canonical file:** `/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/CHECKPOINT.md`  
**Purpose:** single reset-safe checkpoint for Hermes and OpenClaw/Codex  
**Last updated:** 2026-06-16 10:10 UTC  
**Checkpoint owner:** Hermes  
**OpenClaw/Codex role:** read this file on resume; update it only when explicitly taking over, reporting a completed review/fix, or instructed by Sam  
**Deployment verdict:** not ready for live/public deployment

This file is the shared working checkpoint. Hermes is responsible for keeping
it updated. OpenClaw/Codex must read it first after any session reset before
reading handovers, reports, or old checkpoint files.

Historical/context files:

- `HERMES_HANDOVER.md` — Hermes handover context, historical input
- `PROJECT_CHECKPOINT.md` — older project checkpoint, historical context
- `HERMES_DEPLOYMENT_REPORT_2026-06-16.md` — deployment attempt report

Do not treat those files as the current source of truth if they conflict with
this file.

---

## Hermes/OpenClaw GitHub Handoff

Primary handoff channel: GitHub.

- Repo: `https://github.com/unitysam-dev/openclaw-skill-agent-gateway`
- Default branch: `main`
- OpenClaw auth: Git credential helper, backed by local credential storage
  (`/home/node/.git-credentials`)
- Secret value: never write the token into checkpoints, handovers, memory files,
  repo files, or chat
- Required token scope: repo-limited to
  `unitysam-dev/openclaw-skill-agent-gateway`, contents read/write, metadata read
- Branch policy: push handoffs to named branches, do not force-push `main`
- Handoff directory: `handoffs/agent-gateway/`
- Verification directory: `checks/agent-gateway/`
- Binary artifacts: GitHub Releases preferred later; small ZIP artifacts may be
  committed under `artifacts/agent-gateway/` during prototype phase

Current pushed handoff branch:

```text
handoff/agent-gateway-plugin-fix-20260621
```

Current commit:

```text
168ce32 handoff(agent-gateway): add plugin request-status relay fix
```

Pull request URL:

```text
https://github.com/unitysam-dev/openclaw-skill-agent-gateway/pull/new/handoff/agent-gateway-plugin-fix-20260621
```

Rule: never reference local OpenClaw paths as Hermes-readable unless shared
filesystem access has been confirmed for that exact path.

---

## Current State

The current Agent Gateway v0.2 project is here:

```text
/home/node/.openclaw/workspace/registry-v0.2/agent-gateway
```

Core implementation files:

- `registry/app.py`
- `registry/schema_v0.2.sql`
- `registry/cron_runner.py`
- `wordpress-plugin/agent-gateway/agent-gateway.php`
- `docs/installation.md`
- `docs/api-spec.md`
- `docs/data-model.md`

OpenClaw/Codex read the Hermes handover and performed a focused red-team review
on 2026-06-16.

Result: **not deployable yet**.

### 2026-06-21 Local Registry Repair Note

OpenClaw/Codex re-checked the Yunnan Reggae Palace registry issue after the
active local DB was found empty. The business was re-registered into the active
local registry as `agw_64ddea88f0f48cba`.

Root cause found during sync: `assert_safe_fetch_url()` stripped trailing
slashes from fetch URLs. WordPress redirects
`/.well-known/agent` to `/.well-known/agent/`, and the registry correctly blocks
redirect-following fetches, so sync failed with `fetch_redirects_not_allowed`.

Fix applied in `registry/app.py`: safe fetch URL validation now preserves the
exact path, including trailing slashes. After restarting uvicorn on port 8081
and updating the registered `agent_profile_url` to
`http://187.77.147.80:8085/.well-known/agent/`, sync succeeds with HTTP 200 and
search returns Yunnan Reggae Palace for `kunming` + `accommodation`.

Remaining state: `verification_status=verification_pending`,
`sync_status=unverified`, `endpoint_last_status=ok`,
`endpoint_schema_valid=0`. Action metadata still needs schema/verification work
before actions can be treated as verified.

### 2026-06-21 Plugin Request Status Relay Fix

Sam observed that an owner note added in the plugin Requests admin screen
(`only Roots Standard available`) did not relay back to the agent through the
public request-status endpoint.

Root cause in plugin source:

- `agw_get_request_status()` returned status, payload, and metadata, but omitted
  `internal_notes`.
- `agw_public_request_status()` mapped raw `completed` to public `approved`,
  preventing agents from distinguishing owner approval from completed booking.

Local fix applied to both plugin source copies:

- `/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/wordpress-plugin/agent-gateway/agent-gateway.php`
- `/home/node/.openclaw/workspace/wordpress-plugin/agent-gateway/agent-gateway.php`

Changes:

- Public request-status responses now include `owner_response`.
- `metadata.owner_response` also carries the same agent-readable owner note.
- Raw `completed` now maps to public `completed`, not `approved`.
- Rebuilt both local `agent-gateway.zip` plugin packages using Python `zipfile`
  because `zip` is unavailable in this container.

Verification caveat:

- PHP CLI is not installed in this container, so `php -l` could not be run here.
- The live WordPress site will not reflect this fix until the rebuilt plugin ZIP
  is deployed/updated there.

---

## Confirmed Working / Implemented

- Registry app reports version `0.2.0`.
- `registry/app.py` and `registry/cron_runner.py` pass Python syntax checks.
- Active V2 SQLite database has the new idempotency columns:
  - `actor_context`
  - `payload_hash`
  - `status`
  - `locked_until`
  - `expires_at`
- Public serializers redact `endpoint_url` from public action state responses.
- Proxy requires `state='verified'` and `verified_at IS NOT NULL`.
- Sensitive actions require an idempotency key.
- App safe-fetch connects to a validated resolved IP while preserving Host/SNI.
- Admin endpoint uses header authentication, not query-string token auth.

---

## Current Blockers

### 1. High — Verified Action Endpoint Can Be Repointed Without Re-Verification

In `/api/v1/actions/detect`, if an action is already `verified`, a later detect
sync can update `endpoint_url` while preserving the `verified` state and
existing `verified_at`.

Reproduced by OpenClaw/Codex with a temporary SQLite DB:

```text
contact_request stayed verified while endpoint_url changed from /old to /new
```

Risk:

- This bypasses the per-action verification guarantee.
- A valid site API key can silently repoint a verified action to a new same-domain
  endpoint without another verification pass.

Required fix:

- If `/api/v1/actions/detect` receives a different non-empty `endpoint_url` for
  an action currently in `verified` state:
  - downgrade state to `approved` or `detected`
  - clear `verified_at`
  - clear `verified_by`
  - set `verification_result` to something like `endpoint_changed_reverification_required`
  - require `/api/v1/actions/verify` again

### 2. High — Bundled V2 WordPress Plugin Cannot Verify Sensitive Actions It Advertises

The registry validator requires special pending schemas:

- `availability_lookup` requires an `availability` list
- `reservation_create` requires `reservation_ref` and `confirmation_url`
- `payment_link_generate` requires `payment_ref` and `payment_url`

The bundled WordPress plugin currently returns the same generic
`pending_owner_approval` response for all actions.

Direct validator check from OpenClaw/Codex:

```text
contact_request = verified
availability_lookup = availability_response_missing_availability_list
reservation_create = reservation_response_missing_pending_fields
payment_link_generate = payment_response_missing_pending_fields
```

Risk:

- Sensitive V2 actions cannot pass registry verification with the bundled plugin
  as currently implemented.
- The plugin may advertise actions that the registry cannot verify.

Required fix:

Choose one:

1. Update the plugin to return action-specific pending schemas for sensitive
   actions.
2. Or stop advertising/verifying sensitive actions through the generic plugin
   handler until real adapters exist.

### 3. Medium — Deployment/Demo Still Uses Old Admin Query-Link Pattern

The registry `/admin` endpoint now requires `X-Admin-Token`.

The old deployment prompt and public onboarding page used/published
`?admin_token=...` links.

Risk:

- Query-token admin links are obsolete and unsafe.
- Public onboarding material previously exposed sensitive material.

Required fix:

- Remove all public `?admin_token=` links from demos/docs/onboarding pages.
- Use `TOKEN REQUIRED` / header-auth instruction language instead.

### 4. Low/Medium — Cron Safe-Fetch Should Match App Safe-Fetch

The app safe-fetch explicitly blocks multicast addresses. Cron safe-fetch blocks
private, loopback, link-local, reserved, and unspecified addresses, but does not
explicitly check multicast.

Required fix:

- Align cron safe-fetch checks with app safe-fetch checks.

---

## Deployment Attempt Status From 2026-06-16

OpenClaw/Codex followed the deployment prompt from the uploaded Word document.

Result: partial deployment only.

Observed public endpoints:

- `http://187.77.147.80:8082/` — PASS, WordPress reachable
- `http://187.77.147.80:8082/.well-known/agent/` — PASS, but reports `agent_gateway_version: 0.1.0`
- `http://187.77.147.80:8083/` — PASS, agent/user demo reachable
- `http://187.77.147.80:8084/` — PASS, onboarding demo reachable
- `http://187.77.147.80:8084/downloads/agent-gateway.zip` — PASS, but ZIP is stale/not current V2
- `http://187.77.147.80:8081/*` — FAIL, HTTP `502`

The V2 registry was started locally/container-side:

```text
python3 -m uvicorn app:app --host 0.0.0.0 --port 8081
```

It returned V2 locally, but public `187.77.147.80:8081` still returned `502`,
which indicates host/proxy/firewall routing outside this container.

Do not present this as a working public V2 deployment.

---

## What To Do Next

1. Fix blocker 1 in `registry/app.py`.
2. Fix blocker 2 in `wordpress-plugin/agent-gateway/agent-gateway.php` or remove
   sensitive action advertisement from the generic plugin flow.
3. Align cron safe-fetch with app safe-fetch.
4. Rebuild `wordpress-plugin/agent-gateway.zip`.
5. Rerun focused tests:
   - verified endpoint cannot be repointed without downgrade
   - sensitive action verification passes or is intentionally not advertised
   - syntax checks
   - migration check
6. Hermes updates this `CHECKPOINT.md` with:
   - changes made
   - tests run
   - deployment readiness verdict
7. Only then retry public deployment.

## v3 Lifecycle Requirement — Approval, Payment, Completion

For v3, `approved` must not always be treated as the final state.

Expected request lifecycle:

- `pending_owner_approval` — owner has not reviewed yet
- `approved` — owner has accepted the request and may return next-step data
- `payment_required` — owner approval requires payment before completion
- `paid` — payment has been completed or verified
- `completed` — booking/request is fully completed
- `rejected` / `spam` — terminal negative states

Polling/orchestration rule:

- Poll until the first meaningful owner decision: `approved`, `rejected`, or
  `counter_offer`.
- If `approved` includes a payment option/payment link, notify the user and then
  poll again after payment is made until `paid` or `completed`.
- If no payment is required for the booking, the plugin/business system should
  move the request directly from `approved` to `completed`.
- Once `completed`, `rejected`, or `spam` is reached, polling stops.

Agent-facing response rule:

- `approved` means owner approval only.
- `completed` means the booking/request is fully settled.
- Payment-related fields must be explicit; agents must not infer payment or
  confirmed reservation from `approved` alone.

## v3 Caveat — Guest Identity, Passport, and Card Details

Some booking flows, especially hotels, may require guest identity details before
the booking can move from `approved` or `payment_required` to `completed`.

Required model:

- The skill/agent must not collect passport, ID, or credit-card details during
  general search.
- The business profile/action schema should declare whether completion requires:
  - guest full legal name
  - nationality
  - passport/ID number
  - passport/ID expiry date
  - guest phone/email
  - credit card or payment method
  - arrival time or other legally required registration fields
- The agent should request these details only after the user chooses the
  business/offer and the site returns `identity_required`, `payment_required`,
  or another explicit next-step state.
- The user must explicitly approve sending identity/payment details to the site.
- Agents must minimize data sent: only fields required for that booking step.
- Raw credit-card details should not pass through the agent or registry if a
  hosted payment link/tokenized payment flow is available.
- If raw card capture is unavoidable, v3 needs a separate security design before
  implementation: encryption, retention policy, PCI implications, audit logging,
  redaction, and deletion flow.

Possible v3 states:

- `identity_required`
- `identity_submitted`
- `payment_required`
- `paid`
- `completed`

Agent-facing rule:

- Do not ask for passport/card data speculatively.
- Do not store passport/card data in durable memory, logs, checkpoints, or skill
  files.
- Treat identity/payment submission as an external action requiring fresh user
  approval.

---

## Commands Used For Review

Syntax check:

```bash
cd /home/node/.openclaw/workspace/registry-v0.2/agent-gateway
PYTHONPATH=/home/node/.openclaw/workspace/registry/.venv/lib/python3.11/site-packages:. \
  python3 -m py_compile registry/app.py registry/cron_runner.py
```

Result: passed.

Focused tests were run with temporary SQLite databases and direct validator calls.
They reproduced blockers 1 and 2 above.

---

## Reset Resume Instruction

When resuming this project after reset, first read:

```text
/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/CHECKPOINT.md
```

Then inspect current git/worktree state and continue from **What To Do Next**.

Do not rely on `PROJECT_CHECKPOINT.md` or `HERMES_HANDOVER.md` as current truth
unless this file explicitly points there.
