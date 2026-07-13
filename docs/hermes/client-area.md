# Client Area

The client-area experience is rendered by `hermesagent_ClientArea()` (main dashboard, `templates/clientarea.tpl`) and `hermesagent_manage_llm()` (`templates/manage_llm.tpl`), both in `modules/servers/hermesagent/hermesagent.php`.

## Main Dashboard (`clientarea.tpl`)

Reached via **Services → (Hermes product) → View Details**. The template aggressively hides default WHMCS panels (cPanel widgets, "Service Overview", "Additional Information") via a `DOMContentLoaded` script that scans for matching text/images and sets `display: none`, then moves the Hermes container to the top of the tab content — this gives a fully custom SaaS-style page inside the stock WHMCS chrome.

### Deployment States

| `deployment_status` | UI Behavior |
|---|---|
| `Pending Provisioning` (no DB row yet) | Shows an error banner: "No active server deployment found." |
| `Active` | Full dashboard: credentials, live stats, analytics, chat widget generator, pairing guide, backups, danger zone |
| `Suspended` / `Terminated` | A blurred overlay locks the entire page with a red warning card explaining data is unrecoverable |
| Any other value (e.g. `Error`) | Status badge shows the raw status string; most interactive sections are conditionally hidden |

### Sections

**Gateway Dashboard Connection card** — shows:
- Web Dashboard URL (`https://hermes-<id>.hermes.deltadns.xyz` if the server is Secure, else `http://<ip>:<dash_port>`)
- Auth Username / Auth Password (password field is masked via `-webkit-text-security`, toggleable, with one-click copy buttons using `document.execCommand("copy")`)
- Dashboard Port
- "Open Dashboard" button linking directly to the URL

**Agent Neural Link card** — a cosmetic live/offline indicator with an animated SVG "EKG" line and three metric tiles (Container ID = `serviceid`, Uptime Status = static `LIVE`, Memory Allocation = static `OK`). These are decorative, not derived from `hermesagent_ClientArea()`'s real CPU/mem stats.

**AWS Bedrock Trial Quota card** — progress bar computed from:
```
created_at + 15 days → days_remaining
total_used = prompt_tokens + completion_tokens (queried live via SSH)
token_limit = 1,200,000,000 (hardcoded)
percent_used = min(100, total_used / token_limit * 100)
```

**Analytics & Usage** (shown only when `Active`) — live CPU/Memory tiles plus a Chart.js doughnut chart of prompt vs. completion tokens, filtered specifically to the `nvidia.nemotron-nano-3-30b` model (see [Provisioning § Client Area Live Stats](provisioning.md#client-area-live-stats) for how these numbers are sourced).

**Website Live Chat Widget** — a read-only `<textarea>` containing a complete, copy-pasteable `<script>` snippet. It embeds:
- `API_URL = "<api_url>/v1/chat/completions"`
- `API_KEY = "<api_key>"` (the service's plaintext API server key, embedded directly in client-side JS)
- A self-contained floating chat bubble UI (no external dependencies) that POSTs to the OpenAI-compatible endpoint and renders streaming-free chat turns

> **Security note:** Because the API key is embedded in publicly-servable JavaScript, any visitor to the client's website can extract and reuse this key. This is expected for a "public chat widget" API key but means the key should be treated as **public**, not secret — clients should not reuse a sensitive/rate-limited provider key here if they're worried about abuse. Consider recommending clients rotate this key (via **Regenerate Password**, which does *not* rotate `api_key` — only `dashboard_password`; a separate mechanism would be needed to rotate the API key specifically) if abused.

**Pair with Hermes Desktop App** — a 4-step guide linking to `hermes-agent.nousresearch.com`, showing the dashboard URL/username to enter into the desktop app's "Remote Gateway" connector.

**Data Management** — "Download Agent Brain" button linking to `?a=downloadbackup` (see [Provisioning § Download Agent Brain](provisioning.md#download-agent-brain--implementation-detail)).

**Danger Zone** — "Execute Kill Switch" button, gated by a JS `confirm()` dialog, linking to `?a=killswitch`. This is destructive and unrecoverable (see [Provisioning § TerminateAccount vs Kill Switch](provisioning.md#terminateaccount) — killswitch mirrors termination but does **not** delete the WHMCS `mod_hermesagent_instances` row; it updates `status` to `Terminated` instead, leaving the row and port allocation in place).

## Manage LLM Providers Page (`manage_llm.tpl`)

Reached via the "Manage LLM Providers" button (visible only when `Active`), which links to:
```
clientarea.php?action=productdetails&id=<serviceid>&modop=custom&a=manage_llm
```

### How Values Are Populated

`hermesagent_manage_llm()` connects over SSH and reads the container's live `.env` and `config.yaml` directly off disk (`/srv/hermes/<serviceid>/data/`) — it does **not** read from the WHMCS database. This means the form always reflects the actual deployed configuration, including any out-of-band edits made directly on the VPS.

Parsing logic:
- `.env` is split line-by-line on `=`, skipping comments (`#`)
- Recognized keys: `OPENROUTER_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `NOUS_PORTAL_API_KEY`, `OPENAI_API_BASE`, `TELEGRAM_BOT_TOKEN`, `DISCORD_BOT_TOKEN`
- Because both "OpenAI provider" and "Custom endpoint" reuse `OPENAI_API_KEY`, the module disambiguates: if `OPENAI_API_BASE` is set (i.e. a custom endpoint is active), the value is shown in the **Custom API Key** field instead of **OpenAI API Key**
- `config.yaml`'s `model:` line is extracted via regex to populate the Active Model field

### Form Sections

| Section | Fields |
|---|---|
| Active Model | Free-text model string, e.g. `openrouter/meta-llama/llama-3-70b-instruct` |
| API Keys | OpenRouter, OpenAI, Anthropic, Nous Portal (all password inputs) |
| Messaging Channels | Telegram Bot Token, Discord Bot Token |
| Custom OpenAI-Compatible Endpoint | Custom API Base URL, Custom API Key |
| Connect to Your Agent's API *(shown only if `api_enabled`)* | Read-only display of API base URL, API key, API port |

### Save Flow (`update_llm`)

On submit (POST to `?a=update_llm`), `hermesagent_update_llm()`:

1. Rejects non-POST requests by redirecting back to the management page.
2. Builds a map of keys to update from any **non-empty** submitted fields — empty fields are left untouched server-side but are still stripped from `.env` in step 3 below if not resubmitted (see caveat).
3. Over SSH: deletes **all** recognized key lines from `.env` via `sed -i '/^KEY=/d'` for the full list of possible keys, then re-appends only the keys present in the submitted, non-empty map (values wrapped with `escapeshellarg()`).
4. Updates `config.yaml`'s `model:` line via `sed` if a model string was submitted.
5. `docker restart hermes-<serviceid>` to apply changes.
6. Redirects to the management page with `?success=1`, or `?error=1` on SSH failure.

> **Caveat:** Because step 3 unconditionally deletes all known keys before re-adding only the ones with non-empty submitted values, **leaving a field blank on save clears that credential** even if it had a value before. The form pre-fills fields with currently-stored values specifically to avoid this — clients must not blank out a field they want to keep. There is no partial-update / "leave unchanged" semantics beyond what the browser pre-fills.

### Suspended/Terminated Overlay

Like the main dashboard, this page shows the same blurred "Account Suspended/Terminated" overlay when applicable, preventing any further LLM configuration changes.

## Single Sign-On

The module metadata defines `ServiceSingleSignOnLabel` / `AdminSingleSignOnLabel` ("Open Hermes Dashboard"), but no `hermesagent_ServiceSingleSignOn()` function is implemented in the module. If WHMCS's SSO button appears for this product, it will not function until such a hook is added — currently, clients reach the dashboard only via the **Open Dashboard** link/URL shown in the client area.

## Related

- [LLM Management](llm-management.md) — deep dive on provider env var mapping and security
- [Provisioning](provisioning.md) — backend mechanics behind every button on this page
- [Troubleshooting](troubleshooting.md) — "Manage LLM Providers" fails to load / save
