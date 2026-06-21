---
name: agent-gateway-registry
title: Agent Gateway Registry Operations
description: Deploy, configure, and manage Agent Gateway Registry for business-to-agent discovery. Handles v0.1 preservation, v0.2 staged upgrades, action state model, and WordPress plugin integration.
version: 1.0.0
trigger: When user mentions "agent gateway", "AGW", "registry", "/.well-known/agent", "action state", "availability_lookup", or business agent profile work
---

# Agent Gateway Registry Operations

## Overview

Agent Gateway Registry helps businesses become discoverable and usable by AI agents without forcing agents to scrape websites, bypass bot protections, or pretend to be human browsers.

**Core Principle:** The registry is the discovery layer. The plugin is an onboarding and exposure layer. Business systems remain authoritative. Agent Gateway must NOT become a booking engine, payment processor, ecommerce platform, CRM, or inventory system.

## Architecture

### v0.1 (Frozen Baseline)

**Ports:**
- Registry API: 8081
- WordPress Plugin Site: 8082
- Test Instance: 8083
- Fallback: 8084

**Backup:** `/opt/agent-gateway-v0.1-working-20260607-0201.tar.gz`

**CRITICAL:** Never modify v0.1 directly. All v0.2 work happens in separate working copy.

### v0.2 (Development)

**Ports:**
- Registry API: 8085
- WordPress Plugin Site: 8086
- Test Endpoints: 8087-8088

**Working Directory:** `/home/node/.openclaw/workspace/registry-v0.2/agent-gateway/`

## Action State Model

v0.2 introduces three states for every action:

1. **detected** — Plugin found a compatible system (WooCommerce, Bookly, etc.)
2. **approved** — Site owner explicitly allowed agents to access that action
3. **verified** — Agent Gateway tested the action and confirmed it works

**Detection Results:**
- `supported_by_agent_gateway` — Full native support available
- `not_yet_supported` — Not yet implemented
- `custom_endpoint_required` — Requires custom endpoint configuration

**Search Behavior:** Only **verified** actions are marked as `available: true`. Detected/approved actions show their current state with human-readable messages.

## Key Files

### Registry
- `registry/app.py` — Main FastAPI application
- `registry/schema.sql` — Database schema (v0.1)
- `registry/schema_v0.2.sql` — Extended schema with action_states table
- `start-registry.sh` / `start-registry-v0.2.sh` — Startup scripts

### WordPress Plugin
- `wordpress-plugin/agent-gateway/agent-gateway.php` — Main plugin file
- Auto-detects: WooCommerce, Bookly, Amelia, The Events Calendar, Contact Form 7, WPForms, Gravity Forms

### Demo/Test
- `demo/availability_adapter.py` — Mock booking system (port 8088)
- `demo/register_demo_business.py` — Registration helper
- `test-harness/index.html` + `app.js` — Test UI (port 8087)

## API Endpoints

### v0.2 Action State Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/actions/detect` | POST | Report detected actions from plugin |
| `/api/v1/actions/approve` | POST | Approve a detected action |
| `/api/v1/actions/verify` | POST | Verify an approved action works |
| `/api/v1/business/{id}/actions` | GET | Get all action states for a business |
| `/api/v1/business/{id}` | GET | Get business details with action states |

### Core Endpoints (unchanged)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/register` | POST | Register a new site |
| `/api/v1/search` | GET | Search businesses |
| `/api/v1/verify` | POST | Verify domain ownership |
| `/health` | GET | Health check |

## Environment Variables

```bash
# Required
AGW_REGISTRATION_API_KEY=<secure_random_key>
AGW_ADMIN_TOKEN=<secure_random_key>

# Optional (dev)
AGW_DEV_MODE=true                    # Enable dev mode
AGW_ALLOW_LOCAL_VERIFICATION=true    # Allow local verification
AGW_ALLOW_PRIVATE_FETCH=true         # Allow fetching from private IPs
AGW_DB_PATH=/data/agent_gateway.sqlite3
AGW_HOST=0.0.0.0
AGW_PORT=8085
```

## Operations

### User Communication Preference

**CRITICAL: Execute first, explain if asked.**

This user's preference pattern:
- ✅ **DO:** Take action immediately when request is clear
- ✅ **DO:** Provide summary after completion
- ❌ **DON'T:** Ask for confirmation before executing obvious fixes
- ❌ **DON'T:** Explain what you're about to do before doing it
- ❌ **DON'T:** Provide multiple options when user wants direct action

**Signal phrases that indicate "just do it":**
- "do this"
- "make the required changes"
- "fix it"
- "do it now"
- (any imperative without question mark)

**Example exchange:**
```
User: "download page returns: Forbidden"
→ IMMEDIATE ACTION: Diagnose and fix
→ AFTER: "Fixed. [brief technical summary]"

WRONG: "I see the issue. Would you like me to..."
```

### Start v0.2 Registry

```bash
cd /home/node/.openclaw/workspace/registry-v0.2/agent-gateway/registry
export AGW_DB_PATH="../data/agent_gateway_v0.2.sqlite3"
export AGW_DEV_MODE="true"
export AGW_ALLOW_LOCAL_VERIFICATION="true"
export AGW_REGISTRATION_API_KEY="..."
export AGW_ADMIN_TOKEN="..."
uvicorn app:app --host 0.0.0.0 --port 8085
```

### Start Demo Availability Adapter

```bash
cd /home/node/.openclaw/workspace/registry-v0.2/agent-gateway/demo
python availability_adapter.py
# Runs on port 8088
```

### Start Test Harness

```bash
cd /home/node/.openclaw/workspace/registry-v0.2/agent-gateway/test-harness
python server.py
# Runs on port 8087
```

### Register Demo Business

```bash
cd /home/node/.openclaw/workspace/registry-v0.2/agent-gateway/demo
AGW_REGISTRATION_API_KEY="..." python register_demo_business.py
```

## Security Principles

- **No admin access** through Agent Gateway
- **No destructive actions** unless explicitly approved and protected
- **No automatic payment processing** in v0.2
- Rate limiting enforced at adapter level
- Per-action enablement required
- Owner approval required for all commercial actions

## Detection (Local Only)

Detection must happen **locally inside WordPress**. Never externally scan websites from the registry.

**Preferred language:**
- "detect compatible integrations" ✓
- "discover installed systems" ✓
- "identify supported site functions" ✓
- "scan" ✗ (avoid)

## Rollback Procedure

If v0.2 fails:

1. Stop v0.2 services (ports 8085-8088)
2. Restore v0.1 from backup if needed:
   ```bash
   tar -xzf /opt/agent-gateway-v0.1-working-20260607-0201.tar.gz -C /home/node/.openclaw/workspace/
   ```
3. Restart v0.1 services (ports 8081-8084)

## References

- `references/v0.1-backup-location.md` — v0.1 backup path and verification
- `references/v0.2-implementation-stages.md` — Staged rollout plan (4 stages)
- `references/action-state-model.md` — Detailed state machine documentation
- `references/demo-adapter-spec.md` — Availability adapter API specification
- `references/direct-database-registration.md` — Bypass API validation for local testing
- `references/creating-agent-skills.md` — Create skill YAML/JSON packages for agents
- `references/booking-widget-implementation.md` — Complete booking UI pattern with availability checking
- `references/business-onboarding-workflows.md` — Plugin packaging, practice mode setup, capability templates
- `references/apache-wordpress-conflict-resolution.md` — 403 Forbidden on download URLs, slug conflicts

## Common Tasks

### Check Service Health

```bash
curl http://127.0.0.1:8085/health
curl http://127.0.0.1:8088/health
```

### Search Registry

```bash
curl "http://127.0.0.1:8085/api/v1/search?q=demo"
```

### Get Business Details

```bash
curl "http://127.0.0.1:8085/api/v1/business/agw_..."
```

### Test Availability Lookup

```bash
curl "http://127.0.0.1:8088/availability?date=2026-07-01&service=room"
```

### Deploy Booking Widget

For WordPress sites with Agent Gateway integration:

```bash
# 1. Add booking shortcode to theme functions.php
# See: references/booking-widget-implementation.md

# 2. Create booking page
docker exec <container> wp --allow-root post create \
  --post_type=page \
  --post_title="Book an Artist" \
  --post_content='[agw_booking_widget business="kunming-live-music"]' \
  --post_status=publish \
  --post_name=booking

# 3. Verify deployment
curl -s http://site.com/booking/ | grep "agw-booking-widget"
```

## Common Pitfalls

### Polling Architecture: Do Not Poll From LLM Loop (CRITICAL)

**Agents using expensive models should not reason every 30 seconds just to check a status endpoint.**

### Required Flow

```
submit_request → receive request_id → schedule polling worker → agent sleeps → worker wakes agent on meaningful state change
```

### Implementation

**After submitting a request:**
1. Do NOT poll from the LLM loop
2. Create or delegate to a lightweight polling job
3. The polling job checks request status with exponential backoff
4. Only wake the agent when status changes, expires, errors, or returns a counter-offer

### Suggested Polling Schedule

```
15s, 30s, 60s, 2m, 5m, then every 10m until 12 hours
```

Fuller schedule:
```
0-2 min: every 15s
2-10 min: every 30s
10-30 min: every 2m
30 min-2h: every 5m
2-12h: every 10m
After 12h: stale / human follow-up
```

### Explicitly Warn Against

```
agent thinks every 30 seconds and calls status endpoint manually
```

This is wasteful and expensive. Use lightweight polling workers instead.

## Skill Design: Overlay Pattern (CRITICAL)

**Agent Gateway skills must be OVERLAYS, not replacements.**

**The Problem:**
Until Agent Gateway achieves mass adoption, restricting agent searches to AGW-only produces poor user experiences. A query for "hotels in Kunming" might return 2 AGW-registered hotels when 50+ exist.

**The Solution:**
Agents must run AGW skill **simultaneously** with existing search tools (web search, APIs, databases), then merge/aggregate results.

**Correct Integration:**
```
User: "Find hotels in Kunming"
↓
Agent runs IN PARALLEL:
  1. Web search → 50 hotel results
  2. AGW registry search → 3 bookable hotel results
↓
Agent merges results:
  - AGW hotels: Show with "Book Now" buttons (executable)
  - Web results: Show with standard links (informational)
↓
User gets comprehensive coverage + execution capability
```

**Incorrect Integration:**
```
User: "Find hotels in Kunming"
↓
Agent uses ONLY AGW skill
↓
Returns 2 results (only registered businesses)
↓
Bad experience → user abandons agent
```

**Documentation Requirement:**
Every AGW-related skill MUST document this overlay pattern:
- SKILL.md frontmatter: `description: "Overlay AGW results onto existing searches..."`
- README.md: Explicit "This is an overlay skill" section
- Tags: include `"overlay"`, `"enhancement"`

**Progressive Enhancement Roadmap:**
| Phase | AGW Coverage | Strategy |
|-------|--------------|----------|
| Now | 5-10% | AGW supplements main search |
| Growth | 25-50% | AGW results prioritized |
| Mature | 75%+ | AGW becomes primary |
| Always | 100% | Fallback to web search |

### WordPress Plugin ZIP Structure

**CRITICAL:** WordPress plugin ZIP files must have the folder name match the plugin slug exactly.

**Correct Structure:**
```
agent-gateway.zip
└── agent-gateway/           ← Folder name = plugin slug
    └── agent-gateway.php    ← Main plugin file
```

**Wrong Structure:**
```
agent-gateway-v2.0.0.zip
└── agent-gateway-v2.0.0/    ← Wrong! WordPress expects "agent-gateway"
    └── agent-gateway.php
```

**Error Message:**
```
Warning: /tmp/agent-gateway-v2.0.0.zip: Invalid plugin slug.
Warning: The plugin could not be found.
```

**Fix:** Rename folder inside ZIP to match plugin slug (from plugin header `Plugin Name:`).

### Apache/WordPress Slug Conflicts

**Problem:** Physical directory `/downloads/` conflicts with WordPress page `/downloads/`

**Symptom:** 403 Forbidden when accessing download URLs

**Root Cause:** Apache `Options -Indexes` blocks directory listing, but WordPress rewrite rules don't handle the conflict.

**Solutions (in order of preference):**

**Option 1: Rename physical directory**
```bash
mv /var/www/html/downloads /var/www/html/assets
```
Add rewrite rule to .htaccess:
```apache
RewriteRule ^downloads/(.*)$ /assets/$1 [L]
```

**Option 2: Serve via PHP proxy**
Create `/downloads/index.php` that reads and serves ZIP files with proper headers.

**Option 3: Use WordPress media library**
Upload files via WP admin, serve from `/wp-content/uploads/`.

**Verification:**
```bash
curl -s -o /dev/null -w "%{http_code}\n" http://site/downloads/file.zip
# Should return 200, not 403
```

### One Business Per Site

**CRITICAL:** The Agent Gateway WordPress plugin only supports ONE registry_id per site. You cannot have multiple businesses on the same WordPress installation.

**If you need multiple test businesses:**

```bash
# Create separate WordPress containers for each business
# Example: Main product site + Kunming test site

# Main site (port 8082) - Official Agent Gateway product site
docker run -p 8082:80 wordpress:latest

# Test site (port 8085) - Kunming Live Music Booking
docker run -p 8085:80 wordpress:latest
```

**Configure each with different registry_id:**

```php
// Main site: registry_id = "" or product-specific ID
// Test site: registry_id = "kunming-live-music"
```

**Why this matters:**
- User said "whay cant this just be one part of the site, not take over the site"
- The plugin's `.well-known/agent` endpoint can only serve ONE profile
- Multiple businesses = multiple WordPress instances

### Product Site vs Booking Site

**AGENT GATEWAY IS NOT A BOOKING PLATFORM.**

The booking functionality (dates, artists, availability) is ONLY for testing agent skills. The site is infrastructure for agent-driven commerce.

**Correct Structure:**
- **Main site (port 8082):** Product website explaining Agent Gateway V2
  - Homepage: "Infrastructure Layer for Agent-Driven Commerce"
  - Demo page: Interactive walkthroughs (User angle + Business owner angle)
  - How It Works: Technical architecture explanation
  - NO booking widgets on public pages

- **Test site (port 8085):** Kunming Live Music Booking
  - Hidden from public navigation
  - Used ONLY for testing agent booking capabilities
  - Accessible via direct URL, not linked from main site

**WRONG:** Adding booking widgets to main product site
**RIGHT:** Separate test site for booking functionality

### Settings Storage Format

The plugin stores settings as a PHP array, NOT a JSON string:

```php
// CORRECT:
$settings = array(
    'registry_id' => 'kunming-live-music',
    'business_type' => 'entertainment',
    'enabled_actions' => array('availability_request', 'booking_request')
);
update_option('agw_settings', $settings);

// WRONG (creates double-encoded string):
update_option('agw_settings', json_encode($settings));
```

Verify with:
```bash
wp option get agw_settings --format=json
```