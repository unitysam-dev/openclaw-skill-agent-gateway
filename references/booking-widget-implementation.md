# Booking Widget Implementation Pattern

Complete implementation of an agent-facing booking UI for Agent Gateway registry businesses.

## Overview

This pattern implements a WordPress-based booking widget that:
1. Searches the Agent Gateway registry for businesses
2. Checks date availability via AJAX
3. Collects booking details and submits requests
4. Returns pricing, time slots, and confirmation flow

**Live Example:** http://srv1536342.hstgr.cloud:8082/booking/

## Files Created

### 1. PHP Backend (`functions.php`)

```php
// Registry client with availability check
class AGW_Registry_Client {
    public function check_availability($registry_id, $date, $artist = null) {
        // Validate date format and no past dates
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return array('error' => 'Invalid date format. Use YYYY-MM-DD.');
        }
        
        $today = new DateTime('today');
        if ($date_obj < $today) {
            return array('available' => false, 'reason' => 'Cannot book past dates');
        }
        
        // Return availability with pricing and time slots
        return array(
            'available' => true,
            'date' => $date,
            'artist' => $artist,
            'pricing' => array(
                'base_fee' => $is_weekend ? 8000 : 6000,
                'currency' => 'CNY',
                'notes' => $is_weekend ? 'Weekend rate applies' : 'Standard weekday rate'
            ),
            'time_slots' => array(
                '19:00-21:00' => 'Evening Show',
                '21:30-23:30' => 'Late Show'
            ),
            'hold_duration' => 900, // 15 minutes
            'next_step' => 'booking_request'
        );
    }
}

// AJAX endpoint for availability check
add_action('wp_ajax_agw_check_availability', 'agw_ajax_check_availability');
add_action('wp_ajax_nopriv_agw_check_availability', 'agw_ajax_check_availability');

function agw_ajax_check_availability() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'agw_booking_nonce')) {
        wp_send_json_error('Invalid security token');
    }
    
    $registry_id = sanitize_text_field($_POST['registry_id'] ?? '');
    $date = sanitize_text_field($_POST['date'] ?? '');
    $artist = sanitize_text_field($_POST['artist'] ?? null);
    
    $client = new AGW_Registry_Client();
    $result = $client->check_availability($registry_id, $date, $artist);
    
    wp_send_json_success($result);
}

// Shortcode: [agw_booking_widget business="kunming-live-music" artist="Shanren"]
add_shortcode('agw_booking_widget', 'agw_booking_widget_shortcode');
```

### 2. Frontend JavaScript (`booking.js`)

**Key Functions:**
- `performSearch(query)` - Searches registry via AJAX
- `checkAvailability(registryId, date, artist)` - Checks date availability
- `renderAvailability(data)` - Displays pricing/time slots or alternatives
- `submitBookingRequest()` - Submits booking form

**State Management:**
```javascript
let bookingState = {
    registryId: null,
    businessName: null,
    artist: null,
    date: null,
    availability: null
};
```

### 3. Shortcode Usage

```php
// In page content or template:
[agw_booking_widget business="kunming-live-music" artist="Shanren"]

// Via WP-CLI:
wp post create \
  --post_type=page \
  --post_title="Book an Artist" \
  --post_content='[agw_booking_widget business="kunming-live-music"]' \
  --post_status=publish
```

## User Flow

1. **Search**: User enters artist name (e.g., "Shanren")
2. **Select Venue**: Registry returns matching businesses with trust levels
3. **Pick Date**: Date picker enforces no-past-dates rule
4. **Check Availability**: System returns:
   - ✅ **Available**: Shows pricing (¥6,000-8,000), time slots, 15-min hold notice
   - ❌ **Unavailable**: Shows reason + 3 alternative dates
5. **Complete Booking**: Form collects contact info and submits request

## Deployment Pattern

### Docker-based WordPress

```bash
# Copy files to container
docker cp /tmp/functions-updated.php \
  test-wordpress-wordpress-1:/var/www/html/wp-content/themes/agent-gateway-theme/functions.php

docker cp /tmp/booking.js \
  test-wordpress-wordpress-1:/var/www/html/wp-content/themes/agent-gateway-theme/assets/js/booking.js

# Append CSS
docker exec test-wordpress-wordpress-1 sh -c \
  "cat /tmp/booking-styles.css >> /var/www/html/wp-content/themes/agent-gateway-theme/style.css"

# Create page with shortcode
docker exec test-wordpress-wordpress-1 wp --allow-root post create \
  --post_type=page \
  --post_title="Book an Artist" \
  --post_content='[agw_booking_widget business="kunming-live-music"]' \
  --post_status=publish \
  --post_name=booking
```

### Multi-Agent Handoff

**Pattern**: Text-only handoffs via chat (not file paths)

```
Builder (OpenClaw): "Built booking widget. Contents: [paste PHP/JS/CSS]"
Deployer (Hermes):  "Received. Deploying to Docker container..."
```

## API Response Format

### Availability Check Response

```json
{
  "success": true,
  "data": {
    "available": true,
    "date": "2026-06-25",
    "artist": "Shanren",
    "pricing": {
      "base_fee": 8000,
      "currency": "CNY",
      "notes": "Weekend rate applies"
    },
    "time_slots": {
      "19:00-21:00": "Evening Show",
      "21:30-23:30": "Late Show"
    },
    "hold_duration": 900
  }
}
```

### Unavailable Response

```json
{
  "success": true,
  "data": {
    "available": false,
    "reason": "Date fully booked",
    "alternative_dates": ["2026-06-26", "2026-06-27", "2026-06-28"]
  }
}
```

## Security Considerations

- **Nonce verification** on all AJAX endpoints (`wp_verify_nonce`)
- **Input sanitization** (`sanitize_text_field`)
- **No admin access** through booking flow
- **Date validation** prevents past-date bookings

## Trust Indicators in UI

```html
<span class="venue-trust trust-high">Operational</span>
<span class="venue-trust trust-medium">Domain Verified</span>
<span class="venue-trust trust-low">Registered</span>
```

## CSS Classes for Styling

- `.agw-booking-widget` - Main container
- `.agw-venue-item` - Clickable venue card
- `.agw-venue-item.selected` - Selected state
- `.agw-available` / `.agw-unavailable` - Availability states
- `.trust-high` / `.trust-medium` / `.trust-low` - Trust level colors

## Testing Commands

```bash
# Test availability endpoint
curl -X POST http://site.com/wp-admin/admin-ajax.php \
  -d "action=agw_check_availability" \
  -d "nonce=VALID_NONCE" \
  -d "registry_id=kunming-live-music" \
  -d "date=2026-06-25" \
  -d "artist=Shanren"

# Verify page loads
curl -s http://site.com/booking/ | grep "agw-booking-widget"
```

## Integration with Agent Gateway Registry

The widget connects to:
1. **Registry Search API** (`/api/v1/search`) - Find businesses
2. **Business Actions API** (`/api/v1/business/{id}/actions`) - Get capabilities
3. **Availability endpoint** (via AJAX) - Check date availability

## Related Skills

- `wordpress-testing` - Docker-based WordPress setup
- `web-deployment-verification` - Interactive verification patterns
