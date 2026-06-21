# Hermes Deploy Handover — Agent Gateway Plugin/Registry Fixes

Date: 2026-06-21  
Owner context: Sam / OpenClaw  
Target: deploy local Agent Gateway fixes to the live Yunnan Reggae Palace test site and registry flow

## Summary

Several Agent Gateway issues were exposed during a live hotel booking test for
Yunnan Reggae Palace.

The live WordPress plugin accepted a booking request and the request was later
marked completed/approved in the plugin admin, but the agent-facing status
endpoint did not relay the owner note and incorrectly exposed `completed` as
`approved`.

Local source and ZIP packages have been patched. Hermes should deploy the
rebuilt plugin package and verify the live endpoint behaviour.

## Files Changed Locally

Plugin source patched in both local copies:

- `/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/wordpress-plugin/agent-gateway/agent-gateway.php`
- `/home/node/.openclaw/workspace/wordpress-plugin/agent-gateway/agent-gateway.php`

Plugin ZIPs rebuilt:

- `/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/wordpress-plugin/agent-gateway.zip`
- `/home/node/.openclaw/workspace/wordpress-plugin/agent-gateway.zip`

Registry source patched:

- `/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/registry/app.py`

Checkpoint updated:

- `/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/CHECKPOINT.md`

## Fix 1 — Request Owner Notes Did Not Relay

Problem:

- Sam added a note in the plugin Requests admin: only Roots Standard available.
- Public status endpoint did not return that note.
- Endpoint checked:
  - `GET /wp-json/agent-gateway/v1/request/{request_id}`

Root cause:

- `agw_get_request_status()` stored admin notes as `internal_notes`, but did not
  expose them in the public agent-readable response.

Local fix:

- Added top-level `owner_response`.
- Added `metadata.owner_response`.

Expected response after deployment:

```json
{
  "request_id": "...",
  "status": "completed",
  "owner_response": "Only Roots Standard available",
  "metadata": {
    "raw_status": "completed",
    "owner_response": "Only Roots Standard available"
  }
}
```

## Fix 2 — Completed Was Mapped Back To Approved

Problem:

- Live request status showed:
  - public `status`: `approved`
  - metadata `raw_status`: `completed`
- The agent could not tell that the owner had completed the request.

Root cause:

- `agw_public_request_status()` mapped both `approved` and `completed` to
  public `approved`.

Local fix:

- `approved` now returns public `approved`.
- `completed` now returns public `completed`.

## Fix 3 — Registry Safe Fetch Stripped Trailing Slash

Problem:

- WordPress serves:
  - `/.well-known/agent/` -> HTTP 200 JSON
  - `/.well-known/agent` -> HTTP 301 redirect to trailing slash
- Registry safe fetch blocks redirects, correctly.
- Registry code stripped trailing slashes from fetch URLs, forcing the redirect
  path and causing sync failure.

Local fix:

- `assert_safe_fetch_url()` in `registry/app.py` now preserves the exact path,
  including trailing slash.

Result after local restart:

- Registry sync to `http://187.77.147.80:8085/.well-known/agent/` succeeds with
  HTTP 200.

## Live Test Context

Booking request sent:

- Request ID: `a48224bf-8d5e-4886-abe1-c94dc6b763c1`
- Action: `booking_request`
- Dates: 2026-06-23 to 2026-06-28
- Rooms: 5
- Budget: under 300 CNY per room/night
- Preferred under-budget room types:
  - Roots Standard, 288 CNY/night
  - Solo Traveler, 188 CNY/night

Live status before plugin deployment:

- public `status`: `approved`
- metadata `raw_status`: `completed`
- owner note: not relayed

This is the exact behaviour the patched plugin should fix.

There is also an accidental diagnostic request:

- Request ID: `97a04191-8d2f-41f2-844c-27f1db3e0e92`
- Payload says diagnostic only; no booking requested.
- It can be ignored or marked spam/rejected in the plugin admin.

## Deployment Tasks For Hermes

1. Deploy the rebuilt plugin ZIP to the live WordPress test site:
   - preferred ZIP: `/home/node/.openclaw/workspace/wordpress-plugin/agent-gateway.zip`
2. Confirm the live plugin version/source is the rebuilt package.
3. Re-check the booking request status endpoint:
   - `GET http://187.77.147.80:8085/wp-json/agent-gateway/v1/request/a48224bf-8d5e-4886-abe1-c94dc6b763c1`
4. Expected after deployment:
   - public `status` should be `completed`
   - `owner_response` should include the owner note
   - `metadata.owner_response` should also include the note
5. Confirm no booking/payment is automatically confirmed unless the owner action
   explicitly says so.
6. If deploying registry code too, restart the registry after applying
   `registry/app.py`, then verify safe fetch with trailing slash.

## Verification Commands

```bash
curl -sS 'http://187.77.147.80:8085/.well-known/agent/'

curl -sS \
  'http://187.77.147.80:8085/wp-json/agent-gateway/v1/request/a48224bf-8d5e-4886-abe1-c94dc6b763c1'
```

Expected important fields:

```json
{
  "status": "completed",
  "owner_response": "...",
  "metadata": {
    "raw_status": "completed",
    "owner_response": "..."
  }
}
```

## V3 Requirements Added To Checkpoint

Sam clarified two v3 requirements:

1. Approval/payment/completion lifecycle:
   - `approved` may return payment options.
   - If payment is required, poll again after payment until `paid` or
     `completed`.
   - If no payment is required, booking should move directly to `completed`.
   - Agents must not treat `approved` as completed booking.

2. Identity/passport/card caveat:
   - Hotels may require passport/identity details and possibly payment method.
   - Do not collect passport/card details during search.
   - Only collect after the site explicitly returns `identity_required` or
     `payment_required`.
   - User must explicitly approve sending those details to the site.
   - Raw card handling should be avoided; use hosted/tokenized payment links
     where possible.

## Important Caveats

- PHP CLI is not installed in this OpenClaw container, so `php -l` could not be
  run here.
- Shell `zip` is also unavailable; ZIP packages were rebuilt with Python
  `zipfile`.
- The live site will not show the owner note until the rebuilt plugin is
  deployed.
- Do not create additional booking-request POSTs while testing. Use GET status
  checks only unless Sam explicitly asks for another request.

