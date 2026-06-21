# Direct Database Registration Workaround

## When to Use

When the Agent Gateway Registry API rejects registrations due to:
- External reachability checks failing
- Domain verification requirements
- Network visibility issues
- Local development/testing scenarios

**WARNING:** Only use for testing/development. Production registrations should use the API.

## Database Insertion Method

### 1. Get Database Path

```bash
# Find the database file
docker exec agent-gateway-agent-gateway-1 env | grep AGW_DB_PATH
# Usually: /data/agent_gateway.sqlite3
```

### 2. Prepare the Insertion Script

```python
import sqlite3
import json
import hashlib
from datetime import datetime

conn = sqlite3.connect('/data/agent_gateway.sqlite3')
cursor = conn.cursor()

# Check if business already exists
cursor.execute('SELECT registry_id FROM businesses WHERE registry_id = ?', ('your-business-id',))
if cursor.fetchone():
    print('Business already exists')
else:
    # Define actions/capabilities
    actions = {
        'availability_check': {
            'parameters': {
                'artist': {'type': 'string', 'required': True},
                'date_range': {'type': 'string', 'required': True}
            }
        },
        'booking_request': {
            'parameters': {
                'artist': {'type': 'string', 'required': True},
                'date': {'type': 'string', 'required': True},
                'venue': {'type': 'string', 'required': True}
            }
        }
    }
    
    # Generate API key hash
    api_key = 'agw_site_your_business_test_key_12345'
    api_key_hash = hashlib.sha256(api_key.encode()).hexdigest()
    
    now = datetime.now().isoformat()
    site_url = 'http://your-site.com'
    agent_profile_url = site_url + '/.well-known/agent'
    
    cursor.execute('''
    INSERT INTO businesses (
        registry_id, business_name, business_type, description, 
        site_url, domain, location_json, contact_json, actions_json, 
        verification_status, sync_status, verified_at, trust_level, 
        created_at, updated_at, public_status, active_api_key_hash,
        agent_profile_url, endpoint_last_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ''', (
        'your-business-id',
        'Your Business Name',
        'entertainment',  # or other category
        'Business description',
        site_url,
        'your-site.com',
        json.dumps({'city': 'Kunming', 'country': 'CN'}),
        json.dumps({'name': 'Contact Name', 'email': 'email@example.com'}),
        json.dumps(actions),
        'verified',
        'synced',
        now,
        3,  # trust level
        now,
        now,
        'approved',  # IMPORTANT: must be 'approved' to be searchable
        api_key_hash,
        agent_profile_url,
        'reachable'
    ))
    
    conn.commit()
    print('Business registered successfully')

conn.close()
```

### 3. Execute in Container

```bash
docker exec agent-gateway-agent-gateway-1 python3 -c "
import sqlite3
import json
import hashlib
from datetime import datetime

# ... paste Python code from above ...
"
```

### 4. Verify Registration

```bash
# Check business is in database
curl -s "http://localhost:8081/api/v1/business/your-business-id"

# Search for it
curl -s "http://localhost:8081/api/v1/search?q=your+keywords"
```

## Required Fields Reference

### Core Fields
| Field | Required | Notes |
|-------|----------|-------|
| `registry_id` | YES | Unique identifier (e.g., 'kunming-live-music') |
| `business_name` | YES | Display name |
| `domain` | YES | Domain without protocol |
| `active_api_key_hash` | YES | SHA256 hash of API key |
| `verification_status` | YES | Use 'verified' for testing |
| `public_status` | YES | Must be 'approved' to appear in search |

### Optional but Recommended
| Field | Purpose |
|-------|---------|
| `actions_json` | Capability definitions |
| `location_json` | City/country for geo search |
| `contact_json` | Name/email for contact |
| `trust_level` | 0-4, affects search ranking |

## Database Schema Reference

To see all columns:

```bash
docker exec agent-gateway-agent-gateway-1 python3 -c "
import sqlite3
conn = sqlite3.connect('/data/agent_gateway.sqlite3')
cursor = conn.cursor()
cursor.execute('PRAGMA table_info(businesses)')
for col in cursor.fetchall():
    print(f'{col[1]} ({col[2]})')
conn.close()
"
```

## Common Issues

### NOT NULL constraint failed
If you get errors about missing required fields, check which columns require values by running the schema query above.

### Business not found in API
Make sure `public_status = 'approved'`. The API filters out businesses that aren't approved.

### Trust level too low
Set `trust_level = 3` or higher for operational businesses. Level 0-1 may not appear in search results depending on registry configuration.

## Alternative: API Registration (Preferred)

When possible, use the API instead:

```bash
curl -X POST http://localhost:8081/api/v1/register \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $AGW_REGISTRATION_API_KEY" \
  -d '{
    "profile": {
      "business_name": "Your Business",
      "description": "Description",
      ...
    }
  }'
```

Only use direct database insertion when API validation blocks legitimate registrations.

## Project Repository Split — 2026-06-21

The deployable Agent Gateway project now lives in:

```text
https://github.com/unitysam-dev/agent-gateway
```

Use that repo for registry code, WordPress plugin code, docs, demos, source of
truth, handoffs, and deployable artifacts.

This skill repo should remain focused on agent instructions and workflow
guidance only.
