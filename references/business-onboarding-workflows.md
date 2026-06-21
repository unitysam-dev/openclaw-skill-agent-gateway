# Business Onboarding Workflows

Patterns for preparing Agent Gateway-enabled business test sites and distributing plugin packages.

## Creating Downloadable Plugin Packages

WordPress plugins inside Docker containers are NOT directly downloadable by users. Use this pattern:

### 1. Copy Plugin from Container to Shared Location

```python
import subprocess

# Copy plugin files from container to host
subprocess.run([
    "docker", "cp",
    "container-name:/var/www/html/wp-content/plugins/agent-gateway",
    "/path/to/downloads/agent-gateway-v2.0.0"
], check=True)
```

### 2. Create ZIP Archive (Python - works everywhere)

```python
import zipfile
import os

def create_plugin_zip(source_dir, output_path):
    with zipfile.ZipFile(output_path, 'w', zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(source_dir):
            for file in files:
                file_path = os.path.join(root, file)
                arcname = os.path.relpath(file_path, os.path.dirname(source_dir))
                zf.write(file_path, arcname)
    return output_path

# Usage
create_plugin_zip(
    "/path/to/downloads/agent-gateway-v2.0.0",
    "/path/to/downloads/agent-gateway-v2.0.0.zip"
)
```

### 3. Make Available via Web Server

```python
# Copy to WordPress container's web root for direct download
subprocess.run([
    "docker", "cp",
    "/path/to/downloads/agent-gateway-v2.0.0.zip",
    "wordpress-container:/var/www/html/downloads/"
], check=True)

# Set permissions
subprocess.run([
    "docker", "exec", "wordpress-container",
    "chown", "www-data:www-data", "/var/www/html/downloads/agent-gateway-v2.0.0.zip"
], check=True)
```

Download URL: `http://site.com/downloads/agent-gateway-v2.0.0.zip`

## Practice Mode: Site Without Plugin (For User Training)

When the user wants to practice plugin installation and configuration themselves:

### 1. Verify Plugin is NOT Present

```bash
# Check plugins directory - should NOT show agent-gateway
docker exec <container> ls /var/www/html/wp-content/plugins/ | grep agent
```

### 2. Create Admin User for User Access

```python
subprocess.run([
    "docker", "exec", "<container>",
    "wp", "user", "create", "<username>", "<email>",
    "--user_pass=<password>",
    "--role=administrator",
    "--allow-root"
], check=True)
```

### 3. Provide Credentials to User

```
Admin URL: http://site.com/wp-admin
Username: <username>
Password: <password>
```

### 4. User's Installation Steps (Document For Them)

1. Log in to wp-admin
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Upload `agent-gateway-v2.0.0.zip`
4. Activate the plugin
5. Navigate to **Agent Gateway** settings menu
6. Enter registry URL and API key
7. Register business

## Creating Business Capability Templates

When setting up a test business, create these JSON templates:

### 1. Business Capabilities Schema

```json
{
  "business_id": "unique-business-id",
  "name": "Business Display Name",
  "description": "Short description for agents",
  "category": "accommodation|restaurant|retail|services",
  "location": {
    "city": "City Name",
    "province": "Province",
    "country": "Country"
  },
  "contact": {
    "phone": "+86 XXX-XXXX-XXXX",
    "email": "contact@business.com"
  },
  "capabilities": [
    {
      "action_id": "action_name",
      "name": "Human-readable name",
      "description": "What this action does",
      "parameters": {
        "param_name": {
          "type": "string|integer|date",
          "required": true|false,
          "description": "What this parameter means"
        }
      },
      "endpoint": "/wp-json/agent-gateway/v1/action",
      "human_approval_required": true|false
    }
  ]
}
```

### 2. Registry Registration Template

```json
{
  "business_id": "unique-business-id",
  "name": "Business Display Name",
  "description": "Description for registry",
  "category": "accommodation",
  "location": {
    "address": "Street address",
    "city": "City",
    "country": "CN"
  },
  "contact": {
    "phone": "+86 XXX-XXXX-XXXX",
    "email": "contact@business.com",
    "website": "http://site.com"
  },
  "verification": {
    "domain": "site.com",
    "verification_method": "dns_txt",
    "verification_token": "agw-verify-XXXXXX"
  },
  "actions": [
    {
      "action_id": "action_name",
      "name": "Action Name",
      "description": "Description",
      "endpoint_url": "http://site.com/wp-json/agent-gateway/v1/action",
      "human_approval_required": true|false,
      "parameters": {}
    }
  ]
}
```

## Creating Agent Skill Packages

Create ZIP files for agent integration:

### 1. Hermes Integration Package

```python
hermes_skill = {
    "skill_id": "agent-gateway-hermes",
    "name": "Agent Gateway Integration for Hermes",
    "version": "1.0.0",
    "description": "Enables Hermes agents to discover and execute actions on Agent Gateway businesses",
    "capabilities": ["search_registry", "get_business_details", "execute_action"],
    "config": {
        "registry_url": "http://registry.com:8081",
        "api_version": "v1",
        "timeout_seconds": 30
    },
    "endpoints": {
        "search": "/api/v1/search",
        "business": "/api/v1/business/{business_id}",
        "execute": "/api/v1/execute"
    }
}
```

### 2. Create Skill ZIP

```python
import zipfile
import json

skill_data = {...}  # Hermes or OpenClaw skill JSON
skill_name = "hermes-agent-gateway-skill.json"

with zipfile.ZipFile(f"hermes-integration-v1.0.0.zip", 'w') as zf:
    zf.writestr(skill_name, json.dumps(skill_data, indent=2))
```

## Site Content Creation via WP-CLI

When setting up test business sites, create content programmatically:

### Create Pages with Block Content

```python
import subprocess

homepage_content = """<!-- wp:heading -->
<h1>Welcome to Business Name</h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Description here...</p>
<!-- /wp:paragraph -->"""

subprocess.run([
    "docker", "exec", "<container>",
    "wp", "post", "create",
    "--post_title=Welcome",
    "--post_content=" + homepage_content,
    "--post_status=publish",
    "--post_type=page",
    "--allow-root"
], check=True)
```

### Set Site Identity

```python
# Update site title and tagline
subprocess.run([
    "docker", "exec", "<container>",
    "wp", "option", "update", "blogname", "Business Name",
    "--allow-root"
], check=True)

subprocess.run([
    "docker", "exec", "<container>",
    "wp", "option", "update", "blogdescription", "Tagline here",
    "--allow-root"
], check=True)
```

## File Organization

Keep templates and downloads organized:

```
workspace/
├── downloads/
│   ├── agent-gateway-v2.0.0.zip          # Plugin package
│   ├── skills/
│   │   ├── hermes-integration-v1.0.0.zip
│   │   └── openclaw-integration-v1.0.0.zip
│   ├── business-name-capabilities.json    # Capability schema
│   └── business-name-registration.json    # Registry template
```
