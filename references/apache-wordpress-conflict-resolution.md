# Apache/WordPress Slug Conflict Resolution

**Date:** 2026-06-20  
**Context:** Agent Gateway product site (port 8082) download URLs returning 403 Forbidden  
**Status:** RESOLVED

---

## Problem

Download URLs for plugin and skill packages returned **403 Forbidden**:
- `http://srv1536342.hstgr.cloud:8082/downloads/agent-gateway-v2.0.0.zip`
- `http://srv1536342.hstgr.cloud:8082/downloads/`

**Error:**
```
Forbidden
You don't have permission to access this resource.
Apache/2.4.67 (Debian) Server at srv1536342.hstgr.cloud Port 8082
```

---

## Root Cause

Physical directory `/var/www/html/downloads/` conflicted with WordPress page `/downloads/`:

1. WordPress had a page with slug `downloads`
2. Physical directory `downloads/` existed with ZIP files
3. Apache `Options -Indexes` blocked directory listing
4. WordPress rewrite rules didn't properly handle the physical directory
5. Result: 403 Forbidden on all `/downloads/*` URLs

---

## Diagnosis Steps

```bash
# Check HTTP status
curl -s -o /dev/null -w "%{http_code}\n" http://srv1536342.hstgr.cloud:8082/downloads/
# Result: 403

# Check physical directory exists
docker exec test-wordpress-wordpress-1 ls -la /var/www/html/downloads/
# Result: directory exists with ZIP files

# Check .htaccess
# WordPress rewrite rules present, no specific handling for /downloads/
```

---

## Solution Applied

### Step 1: Rename Physical Directory

```bash
docker exec test-wordpress-wordpress-1 mv /var/www/html/downloads /var/www/html/assets
```

### Step 2: Add Rewrite Rule

```bash
docker exec test-wordpress-wordpress-1 bash -c 'cat > /var/www/html/.htaccess << "HTACCESS"
# Serve /downloads/ from /assets/ before WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^downloads/(.*)$ /assets/$1 [L]
</IfModule>

# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress

# Enable ZIP downloads with proper headers
<IfModule mod_headers.c>
  <FilesMatch "\\.zip$">
    Header set Content-Disposition attachment
    Header set Content-Type application/zip
  </FilesMatch>
</IfModule>
HTACCESS'
```

**Critical:** Rewrite rule must be **BEFORE** WordPress rules to intercept `/downloads/` before WP handles it.

### Step 3: Verify

```bash
# Test page (WordPress)
curl -s -o /dev/null -w "%{http_code}\n" http://srv1536342.hstgr.cloud:8082/downloads
# Result: 200 (WordPress page loads)

# Test ZIP file (physical asset)
curl -s -o /dev/null -w "%{http_code}\n" http://srv1536342.hstgr.cloud:8082/downloads/agent-gateway-v2.0.0.zip
# Result: 200 (file downloads)

# Verify ZIP structure
curl -s http://srv1536342.hstgr.cloud:8082/downloads/agent-gateway-v2.0.0.zip -o /tmp/check.zip
unzip -l /tmp/check.zip
# Result: agent-gateway/agent-gateway.php (correct structure)
```

---

## Alternative Solutions

### Option 2: PHP Proxy

Create `/var/www/html/downloads/index.php`:
```php
<?php
$file = basename($_SERVER['REQUEST_URI']);
$path = '/var/www/html/assets/' . $file;

if (file_exists($path) && pathinfo($path, PATHINFO_EXTENSION) === 'zip') {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment');
    readfile($path);
    exit;
}
// Fall through to WordPress
require_once '../index.php';
```

### Option 3: WordPress Media Library

Upload files via WP Admin → Media → Add New
Access via: `/wp-content/uploads/2026/06/file.zip`

---

## Prevention

When creating WordPress sites with download functionality:

1. **Plan URL structure first:** Decide if `/downloads/` is a page or physical directory
2. **Use different slug:** Name WP page `/download/` (singular) and physical dir `/downloads/` (plural)
3. **Or use subdirectory:** `/assets/downloads/`, `/files/`, `/wp-content/uploads/`
4. **Always test:** Verify both page and file URLs work before announcing

---

## Related Issues

### WordPress Plugin ZIP Structure

Separate issue discovered: ZIP folder name must match plugin slug.

**Wrong:**
```
agent-gateway-v2.0.0.zip
└── agent-gateway-v2.0.0/
    └── agent-gateway.php
```

**Correct:**
```
agent-gateway-v2.0.0.zip
└── agent-gateway/
    └── agent-gateway.php
```

See main skill pitfalls section for full details.

---

## Verification Commands

```bash
# Quick status check
curl -sI http://site/downloads/file.zip | head -1
# Expected: HTTP/1.1 200 OK

# Full file test
curl -s http://site/downloads/file.zip -o /tmp/test.zip && unzip -t /tmp/test.zip

# Check Apache config
docker exec container cat /var/www/html/.htaccess
```
