# Creating Agent Skill Packages

## Overview

Agent skills define how AI agents interact with businesses registered in the Agent Gateway. A skill package includes capability definitions, parameter schemas, prompts, and examples.

## Skill Package Structure

### YAML Format (Preferred)

```yaml
skill_id: unique-business-id
name: Human-Readable Business Name
description: What this business does
version: 1.0.0
author: Your Name

capabilities:
  capability_name:
    name: Display Name
    description: What this capability does
    parameters:
      param_name:
        type: string|number|boolean|array
        required: true|false
        enum: [option1, option2]  # for constrained values
        description: Parameter description
    returns:
      field_name: description of return value
    endpoint:
      method: GET|POST|PUT|DELETE
      url: http://site.com/api/endpoint
      requires_approval: true|false

# Business data
artists:
  artist-id:
    name: "Artist Name"
    genre: "Music Genre"
    price_min: 1000
    price_max: 5000
    currency: CNY

venues:
  venue-id:
    name: "Venue Name"
    capacity: 500

# Natural language prompts
prompts:
  action_name: |
    Template for how agent should phrase this action
    Variables: {param_name}

# Example queries
examples:
  - query: "Book Artist Name for June 25th"
    capability: booking_request
    parameters:
      artist: artist-id
      date: "2026-06-25"

# Registry connection
registry:
  business_id: unique-business-id
  endpoint: http://site.com/api
  verification_status: verified
  trust_level: 3
```

### JSON Format (For Programmatic Use)

```json
{
  "skill_id": "unique-business-id",
  "name": "Business Name",
  "description": "What this business does",
  "version": "1.0.0",
  "capabilities": {
    "capability_name": {
      "endpoint": "http://site.com/api/endpoint",
      "method": "POST",
      "parameters": {
        "param_name": {
          "type": "string",
          "required": true
        }
      }
    }
  }
}
```

## Creating a Skill from a Business Page

### Step 1: Identify Capabilities

Look at what actions the business supports:
- Check availability
- Make bookings
- Get information
- Compare options

### Step 2: Define Parameters

For each capability, define:
- Required vs optional parameters
- Data types (string, date, enum)
- Validation constraints (enum values, format)

### Step 3: Create the YAML File

```yaml
skill_id: kunming-live-music
name: Kunming Live Music Booking
description: Book live music performances from Kunming artists
capabilities:
  availability_check:
    name: Check Artist Availability
    parameters:
      artist:
        type: string
        required: true
        enum: [manhu-shanren, puman, bagedai]
      date_range:
        type: string
        required: true
  booking_request:
    name: Book Performance
    parameters:
      artist:
        type: string
        required: true
        enum: [manhu-shanren, puman, bagedai]
      date:
        type: string
        required: true
        format: date
      venue:
        type: string
        required: true
```

### Step 4: Add Business Data

Include static data agents need:
- Artist details (genre, price, set length)
- Venue information (capacity, address)
- Available dates
- Contact information

### Step 5: Write Example Prompts

Help agents understand how to use the skill:

```yaml
examples:
  - query: "Book Manhu Shanren for June 25th at Modernsky Lab"
    capability: booking_request
    parameters:
      artist: manhu-shanren
      date: "2026-06-25"
      venue: modernsky-lab
```

## Kunming Booking Example

See complete examples:
- `kunming-booking-skill.yaml` - Full skill definition
- `kunming-booking-skill.json` - JSON version

Key features demonstrated:
- Multiple artists with different pricing
- Venue options with capacity
- Date-based availability
- Parameter enums for validation
- Natural language examples

## Testing Skills

### Search Test
```bash
curl "http://localhost:8081/api/v1/search?q=kunming+music"
```

### Business Details
```bash
curl "http://localhost:8081/api/v1/business/kunming-live-music"
```

### Simulate Agent Query
```bash
# Agent would parse this and call appropriate capability
echo "Book Manhu Shanren for June 25th"
# → availability_check + booking_request
```

## Best Practices

1. **Use enums for constrained values** - Artist IDs, venue IDs should be enums
2. **Include price ranges** - Agents need to show users pricing info
3. **Add example queries** - Helps agents understand usage patterns
4. **Define return values** - Agents need to know what data comes back
5. **Set requires_approval for bookings** - Commercial actions need confirmation

## Integration with Agent Systems

### OpenClaw
```bash
openclaw skills install kunming-booking-skill.yaml
```

### Hermes
```bash
hermes skills add kunming-booking-skill.yaml
```

### Custom Agent
```python
import yaml

with open('skill.yaml') as f:
    skill = yaml.safe_load(f)

# Use skill definition to guide agent behavior
for cap_name, cap_def in skill['capabilities'].items():
    print(f"Available: {cap_def['name']}")
```
