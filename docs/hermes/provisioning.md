# Provisioning — Server Module Internals

The provisioning module lives at `modules/servers/hermesagent/hermesagent.php`. It implements the standard WHMCS server-module API (`APIVersion 1.1`) and drives every container lifecycle event entirely over SSH.

## Module Metadata

```php
function hermesagent_MetaData() {
    return [
        'DisplayName' => 'Hermes Agent Hosting',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '22',
        'DefaultSSLPort' => '22',
        'ServiceSingleSignOnLabel' => 'Open Hermes Dashboard',
        'AdminSingleSignOnLabel' => 'Open Hermes Dashboard (Admin)',
        'ListAccountsUniqueIdentifierDisplayName' => 'Domain',
        'ListAccountsUniqueIdentifierField' => 'domain',
        'ListAccountsProductField' => 'configoption1',
    ];
}
```

`RequiresServer = true` means every product using this module **must** have a WHMCS Server assigned — that server's SSH details are what gets used for every action.

## Database Schema

The module owns a single custom table, created lazily by `hermesagent_setup_database()` on first `CreateAccount` call (and also by the addon's `hermesagent_activate()`):

### `mod_hermesagent_instances`

| Column | Type | Notes |
|---|---|---|
| `serviceid` | integer, unique | FK-equivalent to `tblhosting.id` |
| `dash_port` | integer | Host port mapped to container's `9119` |
| `api_port` | integer | Host port mapped to container's `8642` |
| `dashboard_username` | string | Basic-auth username for the web dashboard |
| `dashboard_password` | string | Basic-auth password (plaintext in DB — see [LLM Management § Security](llm-management.md#security-considerations)) |
| `dashboard_secret` | string | Additional dashboard auth secret (`HERMES_DASHBOARD_BASIC_AUTH_SECRET`) |
| `api_key` | string | Bearer key for the OpenAI-compatible API server |
| `status` | string | `Pending` / `Active` / `Suspended` / `Error` / `Terminated` |
| `created_at` / `updated_at` | timestamps | Standard Capsule timestamps |

This table is also read directly by the [Admin Addon](admin-addon.md) to build the deployments list.

## Configurable Options

`hermesagent_ConfigOptions()` defines the module's own admin-level settings (visible under a product's **Module Settings** tab as `configoption1`…`configoption10`):

| Key | Config Option # | Friendly Name | Type | Default |
|---|---|---|---|---|
| `llm_provider` | 1 | LLM Provider | dropdown | `nous_portal` |
| `provider_api_key` | 2 | Provider API Key | password | — |
| `custom_endpoint_url` | 3 | Custom Endpoint URL | text | — |
| `model_name` | 4 | Model | text | `hermes-4-405b` |
| `messaging_platform` | 5 | Messaging Platform | dropdown | `None` |
| `messaging_token` | 6 | Bot Token | password | — |
| `dashboard_username` | 7 | Dashboard Username | text | `admin` |
| `enable_api_server` | 8 | Enable OpenAI-Compatible API | yesno | — |
| `resource_tier` | 9 | Resource Tier | dropdown | `Standard (2 vCPU / 2GB)` |
| `docker_image_tag` | 10 | Image Version | text | `latest` |

These map 1:1 to `configoption1`...`configoption10` in `$params`. Values are resolved through `hermesagent_resolve_param()`, which prefers customer-supplied Configurable Options / Custom Fields (matched case-insensitively, alphanumeric-only) over these admin defaults — see [Installation § Server-Level vs Customer-Level Config Resolution](installation.md#server-level-vs-customer-level-config-resolution).

## SSH Connectivity

`hermesagent_get_ssh_client($params)` builds a connection using WHMCS's standard server fields (`serverip`, `serverport` default `22`, `serverusername`, `serverpassword`, `serveraccesshash`). It auto-detects whichever `phpseclib` version WHMCS ships (`phpseclib3\Net\SSH2`, `phpseclib\Net\SSH2`, or legacy `Net_SSH2`), tries key-based auth first if an access hash is present, and falls back to password auth. A 30-second timeout is set on all variants.

## CreateAccount

Triggered on order activation (or manually via **Admin → Service → Module Commands → Create**). Flow:

1. `hermesagent_setup_database()` — ensures the instances table exists.
2. Resolve all config values (`llm_provider`, `provider_api_key`, `custom_endpoint_url`, `model_name`, `messaging_platform`, `messaging_token`, `dashboard_username`, `enable_api_server`, `resource_tier`, `docker_image_tag`).
3. Look up (or create) the `mod_hermesagent_instances` row:
   - **New service**: allocate ports via `hermesagent_allocate_ports()`, generate a random 16-char dashboard password, a 32-char hex dashboard secret, and a 32-char hex API key.
   - **Existing service** (e.g. redeploy): reuse stored ports/password/API key.
4. Connect over SSH.
5. Map **Resource Tier** to Docker `--cpus`/`--memory` limits:

   | Tier match (substring) | CPUs | Memory |
   |---|---|---|
   | `Starter` | `1.0` | `1g` |
   | *(default/Standard)* | `2.0` | `2g` |
   | `Pro` | `4.0` | `4g` |

6. Build the `.env` file contents — always includes dashboard basic-auth vars and a hardcoded `AWS_BEARER_TOKEN_BEDROCK` value, plus provider-specific keys (see [LLM Management](llm-management.md#provider-env-mapping)), API server vars if enabled, and a messaging bot token if configured.
7. Build `config.yaml` — sets `model: "<provider>/<model_name>"`, dashboard token-analytics flag, tool-loop guardrail thresholds, and `terminal.backend: docker`.
8. Remotely, via a single heredoc-based shell script:
   - `mkdir -p /srv/hermes/<serviceid>/data`
   - Write `.env` (`chmod 600`) and `config.yaml` into that directory
   - `docker rm -f hermes-<serviceid>` (clean slate)
   - `docker run -d --name hermes-<serviceid> --restart unless-stopped --cpus=<cpus> --memory=<memory> --env-file .env -v data:/opt/data -p <bind>:<dash_port>:9119 -p <bind>:<api_port>:8642 nousresearch/hermes-agent:<tag> gateway run`
   - Rewrite the dashboard `<title>` tag inside the container to the dashboard username
   - Inject SNBD Host branding HTML/CSS (GTM snippet + a dismissible top banner) into the compiled dashboard's `index.html`
   - If Caddy is present (`which caddy`) **and** the server's `secure` flag is on, write a per-service Caddy config block mapping `hermes-<serviceid>.hermes.deltadns.xyz` → `dash_port` (and `hermes-api-<serviceid>...` → `api_port` if the API is enabled), then reload Caddy
   - `echo "HEALTHY"` as a completion sentinel

9. The command's output is scanned for the literal string `HEALTHY`. If absent, the DB row's `status` is set to `Error` and a truncated error string is returned to WHMCS. Otherwise `status` is set to `Active` and `"success"` is returned.
10. All SSH command output is logged via `logModuleCall()`, with API keys, tokens, dashboard password/secret redacted from the logged command text before storage.

### Port Allocation

```php
$dashBase = 9119;
$apiBase = 8642;
$dashPort = $dashBase + ($serviceid % 1000);
$apiPort  = $apiBase  + ($serviceid % 1000);
```

The base offset is derived deterministically from `serviceid % 1000`, then linearly probed upward (up to 500 attempts) against existing rows in `mod_hermesagent_instances` to avoid port collisions across services on the same shared host. If 500 attempts are exhausted, a random fallback in `10000–30000` is used.

> Because the base scheme only spans a 1000-port window per service, deploying **more than ~1000 concurrent services on a single VPS** increases collision-probe overhead. Split large fleets across multiple WHMCS Server entries.

### Bind Address

If the WHMCS Server's **Secure (SSL)** checkbox is enabled, container ports are bound to `127.0.0.1` only (expecting Caddy to terminate TLS and reverse-proxy). Otherwise they bind `0.0.0.0`, exposing dashboard and API ports directly on the public VPS IP.

### Reverse Proxy / Caddy

When present, Caddy configs are written to `/etc/caddy/conf.d/hermes-<serviceid>.conf` and imported via an `import conf.d/*.conf` line appended to the main `Caddyfile` (added once, checked with `grep -q`). The hardcoded proxy domain is `hermes.deltadns.xyz`.

## SuspendAccount

- Stops the container: `docker stop hermes-<serviceid>`.
- If Caddy's `conf.d` directory exists, overwrites that service's Caddy config with a static `403` responder showing a branded "Account Suspended" HTML page (and, if the API is enabled, a JSON 403 for the API subdomain), then reloads Caddy.
- Sets `status = 'Suspended'` in the DB.

## UnsuspendAccount

- Starts the container: `docker start hermes-<serviceid>`.
- Restores the standard reverse-proxy Caddy config (pulling ports from the stored DB row) and reloads Caddy.
- Sets `status = 'Active'`.
- Returns an error string if no DB row exists (e.g. instance was never created or was deleted out-of-band).

## TerminateAccount

1. `tar -czf` a timestamped backup of `/srv/hermes/<serviceid>/data` into `/srv/hermes/archive/` (best-effort, errors suppressed).
2. `docker rm -fv hermes-<serviceid>`.
3. `rm -rf /srv/hermes/<serviceid>` — **irrecoverably deletes all local data**, including the archive step's source (the archive itself remains in `/srv/hermes/archive/`).
4. Removes the Caddy config for the service and reloads Caddy if present.
5. Deletes the `mod_hermesagent_instances` row entirely.

> The `/srv/hermes/archive/` directory is **not** cleaned up automatically by this module — it accumulates over time and should be pruned/rotated externally if disk space is a concern.

## ChangePassword

Rotates the dashboard basic-auth password (used both by WHMCS's native "Change Password" and by the client-area "Regenerate Password" button):

1. `sed -i` replaces `HERMES_DASHBOARD_BASIC_AUTH_PASSWORD=...` in-place inside the remote `.env` file.
2. `docker restart hermes-<serviceid>` to apply.
3. Updates `dashboard_password` in the DB.
4. If `.env` doesn't exist on the host, returns `"ENV_FILE_NOT_FOUND"` without restarting anything.

## ChangePackage

Triggered on a WHMCS product/configurable-option upgrade/downgrade. Re-maps the **Resource Tier** to CPU/memory limits (same table as `CreateAccount`) and applies them live via `docker update --cpus=... --memory=...` — no restart or `.env` changes needed since Docker supports live resource updates.

## Custom Buttons

### Client Area (`hermesagent_ClientAreaCustomButtonArray`)

| Button | Function | Behavior |
|---|---|---|
| Manage LLM Providers | `manage_llm` | Renders `templates/manage_llm.tpl` — see [Client Area](client-area.md) |
| Restart Agent | `restart` | `docker restart hermes-<serviceid>` |
| View Logs | `viewlogs` | Returns last 100 lines of `docker logs`, HTML-escaped, in a styled `<pre>` block |
| Regenerate Password | `regenpassword` | Generates a new password and calls `ChangePassword` internally |
| Download Agent Brain | `downloadbackup` | Streams a `tar.gz` of the container's data dir directly to the browser |
| Kill Switch | `killswitch` | Irreversibly wipes the container and data dir; requires JS `confirm()` in the template |

### Admin Area (`hermesagent_AdminCustomButtonArray`)

| Button | Function | Behavior |
|---|---|---|
| Restart Agent | `restart` | Same as client-area restart |
| View Logs | `viewlogs` | Same as client-area logs |
| Regenerate Password | `regenpassword` | Same as client-area |
| Force Redeploy | `redeploy` | Re-runs the full `CreateAccount` flow against the existing service (re-writes `.env`/`config.yaml`, recreates the container) |
| SSH Health Check | `healthcheck` | Reports container status (`docker inspect --format='{{.State.Status}}'`) and a local HTTP status-code probe (`curl` against `127.0.0.1:<dash_port>`) |

### Download Agent Brain — Implementation Detail

`hermesagent_downloadbackup()` runs `tar -czf ... && base64 <file> && rm -f <file>` over SSH, base64-decodes the SSH command's stdout locally, then streams it to the browser with `Content-Disposition: attachment`. This means the **entire backup transits as base64 text over the SSH channel** — for very large agent data directories (extensive chat history/memory), this can be slow or hit PHP memory/execution-time limits. There is no chunked/streaming transfer.

## Client Area Live Stats

`hermesagent_ClientArea()` (see [Client Area](client-area.md) for the rendered UI) fetches, over SSH, on every page load when `status === 'Active'`:

1. `docker stats --no-stream --format "{{.CPUPerc}}|{{.MemUsage}}"` for CPU/memory.
2. A base64-encoded inline Python script executed via `docker exec ... python3 -c`, which scans `/opt/data/*.db` and `*.sqlite*` files inside the container for tables containing token-usage columns (`prompt_tokens`/`completion_tokens` or `input_tokens`/`output_tokens` plus a `model` column), summing values where the model name contains `nemotron`.

Both calls happen synchronously on page load — an unresponsive or slow SSH/Docker host will directly slow down the client-area page render. Errors are caught and degrade to `'Error'` placeholder values rather than breaking the page.

A **15-day trial token quota** (`1,200,000,000` combined tokens) is computed from `created_at + 15 days` and rendered as a countdown/progress bar — this is a fixed client-side/UI construct, not enforced server-side by the module itself.

## Next Steps

- [Client Area](client-area.md) — what customers interact with
- [LLM Management](llm-management.md) — provider env var mapping details
- [Troubleshooting](troubleshooting.md) — diagnosing failed `CreateAccount` / SSH errors
