# Demo Availability Adapter API Specification

## Base URL

```
http://127.0.0.1:8088
```

## Endpoints

### GET /health

Health check endpoint.

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2026-06-11T18:20:43.460208",
  "services_available": ["room", "suite", "conference", "spa"],
  "rate_limit_window": 60
}
```

### GET /services

List all available services.

**Response:**
```json
{
  "services": [
    {
      "id": "room",
      "name": "Hotel Room",
      "description": "Standard hotel room with queen bed",
      "base_price": 120.0,
      "currency": "USD",
      "capacity": 2,
      "amenities": ["wifi", "tv", "minibar", "ac"]
    }
  ]
}
```

### GET /availability

Query availability for a specific date and service.

**Parameters:**
- `date` (required) — Date in YYYY-MM-DD format (must be future date)
- `service` (required) — Service ID (room, suite, conference, spa)

**Example:**
```bash
curl 'http://127.0.0.1:8088/availability?date=2026-07-01&service=room'
```

**Success Response:**
```json
{
  "success": true,
  "service": {
    "name": "Hotel Room",
    "description": "Standard hotel room with queen bed",
    "base_price": 120.0,
    "currency": "USD",
    "capacity": 2,
    "amenities": ["wifi", "tv", "minibar", "ac"]
  },
  "availability": {
    "date": "2026-07-01",
    "service_id": "room",
    "total_available": 5,
    "slots": [
      {
        "time": "14:00",
        "available": 5,
        "type": "check-in"
      }
    ],
    "price": 120.0,
    "currency": "USD",
    "restrictions": [],
    "last_updated": "2026-06-11T18:20:23.501474"
  },
  "agent_metadata": {
    "human_approval_required": true,
    "confirmation_policy": "Bookings are requests only until confirmed by business owner",
    "response_format_version": "0.2.0",
    "timestamp": "2026-06-11T18:20:46.252905"
  }
}
```

**Error Response (past date):**
```json
{
  "success": false,
  "error": "Cannot query past dates"
}
```

## Security Features

### Rate Limiting

- General endpoints: 30 requests per 60-second window
- Sensitive endpoints: 10 requests per 60-second window
- Headers returned: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

### Anti-Scraping

- Maximum date range: 90 days
- Suspicious threshold: 50 requests per window triggers temporary block
- Block duration: 300 seconds
- Suspicious clients receive 429 Too Many Requests

### Request Logging

All requests logged with:
- Timestamp
- Client IP
- Endpoint
- Response status
- Rate limit tracking
