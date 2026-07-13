# Hermes Agent — Troubleshooting Guide

Common issues when deploying and managing Hermes Agent through the WHMCS module, with diagnostic steps and fixes based on the module's actual code.

---

## SSH Connection Failures

The module uses `hermesagent_get_ssh_client()` to connect to the customer's VPS via SSH. Failures here block all operations.

### "Connection refused" or "Host key verification failed"

**Causes:**
- VPS not yet provisioned or not reachable on the configured IP/port
- SSH port is non-standard (not 22) and the module hasn't been configured for it
- Host key mismatch after VPS reinstallation

**Fix:**
```bash
# Verify the VPS is reachable
ssh -p 22 root@<vps-ip>

# If host key changed, remove old key
ssh-keygen -R <vps-ip> -p <port>
```

### "Authentication failed" or "Permission denied"

The module uses password-based SSH auth (`$ssh->exec()` via `phpseclib`). If the VPS password was changed outside WHMCS, provisioning and management actions will fail.

**Fix:** Reset the VPS root password and update the server configuration in WHMCS (Products/Services → Servers → edit the Hermes server group).

### SSH timeout (30+ seconds)

The module has no explicit SSH connection timeout. If the VPS is slow or under load, the PHP process can hang indefinitely. This blocks the entire WHMCS operation.

**Workaround:** Ensure the VPS has sufficient resources (CPU/memory). For high-volume deployments, consider reducing PHP's `max_execution_time`.

---

## Docker Deployment Issues

### Container fails to start

The module runs `docker run -d --name hermes-{serviceid} ...` during provisioning. If this fails:

**Check Docker logs:**
```bash
# On the customer VPS
docker logs hermes-{serviceid}
docker inspect hermes-{serviceid}
```

**Common causes:**
- Docker not installed on the VPS (check with `docker info`)
- Port already in use (dash_port / api_port conflict)
- Image not found (`hermesagent:latest` doesn't exist on the VPS)
- Insufficient disk space (`df -h`)

**Fix:**
- Run `setup-vps.sh` on the VPS to install Docker and pull the image
- Check port allocation in the `mod_hermesagent_instances` database table — ensure no duplicate ports for the same VPS
- Free up disk space on the VPS (`docker system prune -af`)

### Container status stuck on "Pending"

If the `status` column in `mod_hermesagent_instances` stays "Pending" beyond provisioning, the `AdminServiceEdit` hook or the server module's `AdminServicesTabFields` may not have triggered properly.

**Check:**
1. Is the addon module activated in Setup → Addon Modules?
2. Is the server module configured with the correct VPS IP, username, and password?
3. Check `tblmodulelog` for SSH errors

---

## API Key Problems

The module stores API keys in `/srv/hermes/{serviceid}/data/.env` and manages them via `sed` commands over SSH.

### Keys not being saved

The `hermesagent_update_llm()` function (line 1017 of the server module) runs `sed` to remove old keys and `echo` to append new ones. If this SSH command fails:

**Check WHMCS module logs:**
```php
// The module logs all commands
logModuleCall('hermesagent', 'update_llm', $cmd, 'Success');
```
Look in **Utilities → Logs → Module Log** for the full SSH command that was attempted.

**Common causes:**
- SSH connection failed during the save
- The `.env` file path `/srv/hermes/{serviceid}/data/.env` doesn't exist
- The container isn't running (`docker restart` fails because the container name is wrong)

### "sed: -e expression #1" errors

The model string escaping on line 1052 only escapes `/` characters:
```php
$escModel = str_replace('/', '\/', $model);
```

If the model string contains special characters like `&`, `\`, or newlines, `sed` will error.

**Fix:** Use safe model strings like `openrouter/meta-llama/llama-3.1-8b-instruct` (alphanumeric, slashes, and hyphens only).

### Keys displayed in plaintext in module logs

This is a known concern — `logModuleCall('hermesagent', 'update_llm', $cmd, 'Success')` at line 1071 logs the full command including `echo "OPENROUTER_API_KEY=*** .env"`. Anyone with access to WHMCS module logs can read all customers' API keys.

**Mitigation:**
- Regularly audit and purge `tblmodulelog`
- Restrict admin access to Utilities → Logs
- In production, patch the module to mask keys before logging

---

## Model Configuration Errors

### Container restarting in a loop after model change

If the model string in `config.yaml` is invalid, the Hermes Agent may fail to start, causing Docker to restart it repeatedly.

**Diagnose:**
```bash
# Check if container is restarting
docker ps -a | grep hermes-{serviceid}
docker logs hermes-{serviceid} --tail 50
```

**Fix:** Set a known-good model via the WHMCS client area → Manage LLM Providers → Active Model field. Use a model string like:
```
openrouter/meta-llama/llama-3.1-8b-instruct
```

### Config changes not applying after save

The module runs `docker restart hermes-{serviceid}` after writing config. If the container name differs (e.g., renamed or deployed with a different naming scheme), the restart command silently fails.

**Fix:** Verify the container name matches:
```bash
docker ps --format '{{.Names}}' | grep hermes
```

---

## Container Restart Failures

### "docker restart" command times out

If the Hermes Agent takes too long to shut down gracefully, `docker restart` may hang the SSH command. The default `stop_grace_period` in the Docker image controls this.

**Fix:** Manually force the restart:
```bash
docker stop --time 5 hermes-{serviceid}
docker start hermes-{serviceid}
```

### Container name conflict

If a service is terminated and re-provisioned with the same ID, the old container may still exist:
```bash
docker rm -f hermes-{serviceid}
```

The module's `AdminServiceEdit` hook should handle this, but manual cleanup may be needed if the WHMCS database and Docker state desync.

---

## WHMCS Module Logs

All module activity is logged to `tblmodulelog`. Access via **Utilities → Logs → Module Log**.

### Key log entries to look for

| Description | Logged by | What to search for |
|---|---|---|
| SSH command execution | `logModuleCall('hermesagent', 'AdminServicesTabFields', ...)` | `AdminServicesTabFields` |
| LLM config updates | `logModuleCall('hermesagent', 'update_llm', ...)` | `update_llm` |
| Provisioning actions | `logModuleCall('hermesagent', 'CreateAccount', ...)` | `CreateAccount` |
| SSH errors | `logModuleCall('hermesagent', ...)` contains exception messages | `Exception` / `error` |

### PHP Fatal Errors

If the module crashes with a white screen or 500 error:
```bash
# Check WHMCS error logs
tail -100 /var/log/whmcs/errors.log
tail -100 /var/log/php-fpm/error.log
```

Common PHP errors:
- `Class 'Capsule' not found` — WHMCS not bootstrapped correctly for addon pages
- `Call to undefined function hermesagent_get_ssh_client()` — Server module not deployed to the correct path

---

## Installation Issues

### install-whmcs.sh cannot find configuration.php

The script looks for `configuration.php` in the current directory. Run it from the WHMCS root:
```bash
cd /var/www/whmcs
bash /path/to/install-whmcs.sh
```

### "curl: command not found"

The installer uses `curl` to download from GitHub. Install it:
```bash
apt install curl -y    # Debian/Ubuntu
yum install curl -y    # CentOS/RHEL
```

### "unzip: command not found"

The installer uses `unzip` to extract the downloaded module:
```bash
apt install unzip -y
```

### Module not showing in Addon Modules list

After installation, go to **Setup → Addon Modules** and check that "Hermes Agent Manager" appears. If not:
1. Ensure files are in the correct paths:
   - `modules/servers/hermesagent/hermesagent.php`
   - `modules/addons/hermesagent/hermesagent.php`
2. Verify file permissions (644 for PHP files, 755 for directories)
3. Clear WHMCS cache: **Setup → General Settings → Other → Empty cache**

---

## VPS Setup Problems

### "Docker is not installed" during setup-vps.sh

Run the VPS setup script again — it installs Docker if missing:
```bash
bash setup-vps.sh
```

Or manually:
```bash
curl -fsSL https://get.docker.com | bash
systemctl enable --now docker
```

### "hermesagent:latest" image not found

The setup script pulls the Hermes Agent Docker image. If it fails:
```bash
# Manually pull the image
docker pull nousresearch/hermes-agent:latest

# Or tag an existing image
docker tag hermesagent:latest nousresearch/hermes-agent:latest
```

### Insufficient disk space

Docker containers and agent data accumulate over time. On the VPS:
```bash
# Check disk usage
df -h

# Clean Docker
docker system prune -af --volumes

# Check agent data directory size
du -sh /srv/hermes/*
```

---

## Dashboard Access Issues

The module creates dashboard credentials during provisioning:
- Username: Random string
- Password: Random string
- Secret: Random string

### "401 Unauthorized" when accessing dashboard

The basic auth credentials in the Docker container's environment may differ from what's stored in `mod_hermesagent_instances`.

**Fix:** The credentials are set via environment variables at container creation:
```
HERMES_DASHBOARD_BASIC_AUTH_USERNAME
HERMES_DASHBOARD_BASIC_AUTH_PASSWORD
HERMES_DASHBOARD_BASIC_AUTH_SECRET
```

Check `docker inspect hermes-{serviceid}` to verify the running container's env vars match the database. If not, the config wasn't applied on restart.

### Dashboard port not accessible

The `dash_port` in the database must match the port published in the Docker container:
```bash
docker port hermes-{serviceid}
```

Check firewall rules on the VPS — the dashboard port must be open.

---

## Quiz Lead Capture Issues

### "Database connection failed" in hermes-quiz-submit.php

The quiz script may not have access to the WHMCS database. Ensure:
1. The script is placed inside the WHMCS root directory
2. Database credentials in `configuration.php` are valid
3. The `mod_hermesagent_quiz_leads` table exists (created on addon activation)

### Leads not showing in admin area

The addon's admin pages query `mod_hermesagent_quiz_leads`. If leads exist in the database but don't display:
1. Check the table directly via phpMyAdmin or MySQL CLI
2. Verify the addon is activated in Setup → Addon Modules
3. Check for PHP errors in the WHMCS error log

---

## Template Rendering Issues

### WHMCS variables not displayed in manage_llm.tpl

The `hermesagent_manage_llm()` function (line 946) assigns variables to the template. If variables show as empty:
1. Check that the `.env` file exists and is readable on the VPS
2. Verify SSH connection works — the module reads env vars via SSH
3. Check WHMCS module logs for SSH errors

```
// From the code — vars are read from the VPS .env file:
$envData = $ssh->exec("cat \"{$dataDir}/.env\" 2>/dev/null || echo ''");
```

### "Fatal error: Smarty error" in templates

If templates use undefined variables, Smarty may throw errors. The module passes these variables to the template:
- `active_model`, `openrouter_key`, `openai_key`, `anthropic_key`, `nous_key`
- `custom_url`, `custom_key`, `telegram_token`, `discord_token`
- `api_url`, `api_key`, `api_port`, `serviceid`, `success`, `error`, `deployment_status`

All have defaults (empty strings) set in `hermesagent_manage_llm()`.

---

## Database Problems

### "Base table or view not found" on addon activation

The addon creates two tables on activation:
- `mod_hermesagent_instances` — service-to-container mappings
- `mod_hermesagent_quiz_leads` — captured leads

If activation fails, run manually:
```sql
CREATE TABLE IF NOT EXISTS mod_hermesagent_instances (
  serviceid INT UNIQUE,
  dash_port INT,
  api_port INT,
  dashboard_username VARCHAR(255),
  dashboard_password VARCHAR(255),
  dashboard_secret VARCHAR(255),
  api_key VARCHAR(255),
  status VARCHAR(20) DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mod_hermesagent_quiz_leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) DEFAULT '',
  email VARCHAR(200) NOT NULL,
  whatsapp VARCHAR(30) DEFAULT '',
  profile VARCHAR(30) DEFAULT '',
  answers TEXT,
  status VARCHAR(20) DEFAULT 'new',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (email),
  INDEX (status),
  INDEX (profile)
);
```

### Capsule::schema() not found

If PHP throws `Class 'Illuminate\Database\Capsule\Manager' not found`, WHMCS's Laravel framework isn't bootstrapped. This typically happens when:
- Accessing addon pages from a custom script instead of WHMCS admin
- WHMCS version is too old (requires v8.0+)

---

## General Debugging Checklist

1. **Check WHMCS Module Log**: Utilities → Logs → Module Log → filter by `hermesagent`
2. **Verify SSH access**: Can you SSH into the VPS with the credentials stored in WHMCS?
3. **Check container status**: `docker ps -a | grep hermes-{serviceid}`
4. **Check Docker logs**: `docker logs hermes-{serviceid} --tail 100`
5. **Verify database records**: Check `mod_hermesagent_instances` for the service ID
6. **Test API connectivity**: Can the container reach OpenRouter/OpenAI from the VPS?
7. **Check disk space**: `df -h` on both WHMCS server and customer VPS
8. **Review PHP error logs**: `/var/log/php-fpm/error.log` or WHMCS admin error log
