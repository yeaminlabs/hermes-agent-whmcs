# Installation Guide

This guide covers installing the Hermes Agent WHMCS modules, preparing a VPS to host containers, and configuring a sellable WHMCS product.

## 1. Install the WHMCS Modules

### Option A — Automated (`install-whmcs.sh`)

Run the installer from your WHMCS root directory (the folder containing `configuration.php`):

```bash
cd /var/www/whmcs
curl -sL https://raw.githubusercontent.com/yeaminlabs/hermes-agent-whmcs/main/install-whmcs.sh -o install-whmcs.sh
bash install-whmcs.sh
```

What it does:

1. Verifies `configuration.php` exists in the current directory (or prompts for the correct WHMCS path).
2. Checks for `curl` and `unzip`.
3. Downloads the latest module source from `github.com/yeaminlabs/hermes-agent-whmcs` (`main` branch, as a zip).
4. Extracts and copies:
   - `modules/servers/hermesagent/` → provisioning module
   - `modules/addons/hermesagent/` → admin addon
5. Cleans up temporary files.

If `configuration.php` isn't found in the current directory, the script will prompt:

```
WARNING: 'configuration.php' was not found in the current directory.
Please enter the absolute path to your WHMCS root directory (e.g. /var/www/whmcs):
```

### Option B — Manual Install

Copy the following directories from this repository into your WHMCS installation, preserving structure:

```
modules/servers/hermesagent/    →  <whmcs_root>/modules/servers/hermesagent/
modules/addons/hermesagent/     →  <whmcs_root>/modules/addons/hermesagent/
```

Ensure the WHMCS webserver user (e.g. `www-data`) has read access to both directories, including `templates/clientarea.tpl` and `templates/manage_llm.tpl`.

### Post-Install Activation Steps

1. Log in to **WHMCS Admin Area**.
2. Go to **Setup → Addon Modules**.
3. Find **Hermes Agent Manager**, click **Activate**, then **Configure**.
4. Under configuration, check **Full Administrator** permissions and **Save**. (The addon needs this to read/write `tblcustomfields`, `tblproductconfiggroups`, `tblhosting`, etc.)
5. Go to **Addons → Hermes Agent Manager** — this is the addon's management dashboard (see [Admin Addon](admin-addon.md)).

At this point the provisioning module (`Hermes Agent Hosting`) is available as a server module option when creating a product, but no product is configured yet.

## 2. VPS Setup (`setup-vps.sh`)

Each WHMCS **Server** entry corresponds to one VPS that will host Docker containers for its assigned clients. Run the setup script once per VPS, as a user with `sudo`/root access (Docker install and systemd calls require it):

```bash
curl -fsSL https://raw.githubusercontent.com/yeaminlabs/hermes-agent-whmcs/main/setup-vps.sh -o setup-vps.sh
sudo bash setup-vps.sh
```

What it does:

| Step | Action |
|---|---|
| 1 | Detects the VPS's public IP via `ipinfo.io` or `api.ipify.org` |
| 2 | Detects the current shell user (`whoami`) |
| 3 | Installs Docker via `get.docker.com` if not already present, then `systemctl start/enable docker` |
| 4 | Installs `curl` if missing (via `apt-get` or `yum`) |
| 5 | Generates a fresh 4096-bit RSA SSH keypair at `~/.ssh/whmcs_hermes`, appends the public key to `~/.ssh/authorized_keys`, then **deletes the local key files** after printing the private key once |

The script prints connection details formatted for direct copy-paste into the WHMCS **Server** form:

```
=========================================================
              WHMCS SERVER ENTRY DETAILS
=========================================================
Hostname or IP Address:
----------------------
203.0.113.42

Username:
--------
root

Password:
--------
(Leave blank, we are using the Access Hash)

Access Hash:
------------
-----BEGIN OPENSSH PRIVATE KEY-----
...
-----END OPENSSH PRIVATE KEY-----
=========================================================
```

> **Security note:** The private key is printed to stdout only once and is not written to disk afterward. Copy it immediately into the WHMCS Server's **Access Hash** field over an encrypted admin session, then avoid leaving it in shell history/scrollback longer than necessary.

### Adding the Server in WHMCS

Go to **Setup → Products/Services → Servers → Add New Server** and fill in:

| Field | Value |
|---|---|
| Name | Any label, e.g. `Hermes VPS 1` |
| Hostname/IP Address | `PUBLIC_IP` from script output |
| Username | `USER_NAME` from script output (commonly `root`) |
| Password | Leave blank |
| Access Hash | Paste the full private key block |
| Secure (SSL) | Check this **only** if Caddy will front the containers with HTTPS on `hermes.deltadns.xyz` subdomains (see [Provisioning](provisioning.md#reverse-proxy--caddy)) |

Click **Test Connection** to confirm SSH auth works via the access hash before saving.

## 3. WHMCS Product Configuration

1. Go to **Setup → Products/Services → Products/Services → Create a New Product**.
2. Set **Product Type** (e.g. Other/Hosting Account) and **Product Group**.
3. Under **Module Settings**, set **Module Name** to `Hermes Agent Hosting`.
4. Assign the VPS **Server** (or a Server Group with load balancing) created above.
5. Leave the module's own config options (LLM Provider, Provider API Key, etc. — see table below) at defaults; the addon's One-Click Setup will layer Configurable Options and Custom Fields on top for client-facing checkout.
6. Save the product.
7. In **Addons → Hermes Agent Manager**, select this product from the dropdown and click **Run Setup Configuration** (see [Admin Addon](admin-addon.md)).

### What "One-Click Product Setup" Automates

Running the setup for a product creates:

**Custom Fields** (`tblcustomfields`, type=`product`):

| Field Name | Type | Required |
|---|---|---|
| Provider API Key | password | Yes |
| Dashboard Username | text | Yes |
| Bot Token | password | No |
| Custom Endpoint URL | text | No |

**Configurable Option Group** — `Hermes Agent Options (Product #<id>)`, linked to the product, containing:

| Option | Type | Choices |
|---|---|---|
| LLM Provider | Dropdown | `bedrock` (Amazon Bedrock / Nvidia Nemotron) |
| Resource Tier | Dropdown | Starter (1 vCPU/1GB), Standard (2 vCPU/2GB), Pro (4 vCPU/4GB) |
| Enable OpenAI-Compatible API | Dropdown | No / Yes |
| Model | Dropdown | `nvidia.nemotron-nano-3-30b` (Free) |
| Messaging Platform | Dropdown | None / Telegram / Discord / Slack |

Every configurable option sub-value is given a **$0.00** price row across all currencies in `tblpricing`, so it doesn't affect checkout totals unless you manually adjust pricing afterward.

Running the setup is idempotent — re-running it on the same product will not duplicate fields, groups, or options; it looks up existing rows by name first.

## Server-Level vs Customer-Level Config Resolution

The provisioning module resolves each setting with this precedence (see `hermesagent_resolve_param()` in [Provisioning](provisioning.md)):

1. Customer's **Configurable Options** selected at checkout
2. Customer's **Custom Fields** filled at checkout
3. The server module's own `ConfigOptions` (admin-set defaults on the product's Module Settings tab)
4. A hardcoded fallback default

This lets you either fully automate checkout-time customer input (recommended, via One-Click Setup) or preset values centrally per-product without exposing them to clients.

## Next Steps

- [Provisioning](provisioning.md) — what happens when an order is accepted
- [Client Area](client-area.md) — what the customer sees post-provisioning
- [Troubleshooting](troubleshooting.md) — SSH/Docker error triage
