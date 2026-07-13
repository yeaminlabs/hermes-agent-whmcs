# Admin Addon — Hermes Agent Manager

The addon module (`modules/addons/hermesagent/hermesagent.php`) is the operator-facing control panel for the whole product line. It is reached via **Addons → Hermes Agent Manager** after activation.

## Configuration

```php
function hermesagent_config() {
    return [
        'name' => 'Hermes Agent Manager',
        'description' => 'Admin tools to automate product configurations (Custom Fields & Configurable Options) and manage active dockerized client deployments.',
        'author' => 'snbdhost',
        'language' => 'english',
        'version' => '1.0',
        'fields' => []
    ];
}
```

The addon defines no configurable settings fields of its own — all behavior is driven from its output page. It **must** be granted **Full Administrator** access rights (Setup → Addon Modules → Configure) because it directly manipulates `tblcustomfields`, `tblproductconfiggroups`, `tblproductconfigoptions`, `tblproductconfigoptionssub`, `tblpricing`, and reads `tblhosting`/`tblclients`/`tblproducts`/`tblservers`.

## Activation (`hermesagent_activate`)

Runs once when the addon is activated (or re-run if tables are missing). Creates, if not already present:

- `mod_hermesagent_instances` — same schema as described in [Provisioning § Database Schema](provisioning.md#database-schema)
- `mod_hermesagent_quiz_leads` — see [Lead Generation](lead-gen.md#database-schema)

Returns a WHMCS-standard `['status' => 'success'|'error', 'description' => '...']` array, surfaced in the Addon Modules activation UI.

## Deactivation

`hermesagent_deactivate()` is a no-op returning success — **tables and data are not dropped** on deactivation. Reactivating later resumes with all historical data intact.

## One-Click Product Setup

The core automation feature. From the addon dashboard:

1. Select a product from the dropdown (populated from `tblproducts` where `servertype = 'hermesagent'`).
2. Click **Run Setup Configuration**.

This calls two helper functions:

### `hermesagent_addon_create_custom_field($productId, $name, $type, $desc, $showOrder, $required)`

Idempotently creates (or updates the `required` flag on) a product-level Custom Field. Looks up existing fields by exact `fieldname` match scoped to `type='product'` and `relid=$productId` before inserting.

Fields created by the One-Click flow:

| Field Name | Type | Shown at Checkout | Required |
|---|---|---|---|
| Provider API Key | password | Yes | Yes |
| Dashboard Username | text | Yes | Yes |
| Bot Token | password | Yes | No |
| Custom Endpoint URL | text | Yes | No |

### `hermesagent_addon_setup_config_options($productId)`

1. Creates (or reuses) a Configurable Option **Group** named `Hermes Agent Options (Product #<productId>)`, linking it to the product via `tblproductconfiglinks` if not already linked.
2. Creates each option and its sub-values (dropdown choices) if they don't already exist, matched by a `LIKE '<name>%'` lookup against `optionname`.
3. For every sub-value, inserts a `$0.00` `tblpricing` row (`type='configoptions'`) across **every currency** defined in `tblcurrencies`, if one doesn't already exist.

Options created:

| Option | Sub-values |
|---|---|
| LLM Provider | `bedrock` → "Amazon Bedrock (Nvidia Models)" |
| Resource Tier | Starter (1 vCPU/1GB), Standard (2 vCPU/2GB), Pro (4 vCPU/4GB) |
| Enable OpenAI-Compatible API | No, Yes |
| Model | `nvidia.nemotron-nano-3-30b` → "Nvidia Nemotron Nano 3 30B (Free)" |
| Messaging Platform | None, Telegram, Discord, Slack |

> **Note on OpenRouter model discovery:** the addon source contains a `$openRouterModels = []` placeholder and a comment ("Fetch OpenRouter Models dynamically") but the dynamic-fetch logic is **not implemented** — the `Model` configurable option is always seeded with only the single hardcoded Nemotron sub-value (`$modelSubs`). If you need OpenRouter's full model catalog as selectable checkout options, you'll need to add that API call yourself (e.g. querying `https://openrouter.ai/api/v1/models`) and populate `$modelSubs` before calling `hermesagent_addon_setup_config_options()`. In practice, clients can still pick any OpenRouter model string manually via [Manage LLM Providers](client-area.md#manage-llm-providers-page-manage_llmtpl) post-purchase — checkout-time selection is just limited to the Nemotron default.

### Idempotency

Both the custom field and configurable option/pricing setup are safe to re-run repeatedly against the same product — existing rows are detected and reused rather than duplicated. This makes it safe to click **Run Setup Configuration** again after, e.g., a WHMCS currency addition (to backfill missing `$0.00` pricing rows for the new currency) or after this module is updated with new fields.

### Global Field Fixup

On every page load of the addon (not just on setup submission), `hermesagent_output()` runs:

```php
Capsule::table('tblcustomfields')
    ->where('type', 'product')
    ->whereIn('fieldname', ['Bot Token', 'Custom Endpoint URL'])
    ->update(['required' => '']);
```

This forces **Bot Token** and **Custom Endpoint URL** to be non-required across **all** products globally, every time the addon page is opened — a safety net against these optional fields accidentally being marked required (e.g. by manual admin edits), since a required-but-usually-blank field would block checkout for clients not using messaging/custom endpoints.

## Dashboard Overview

The addon's main page renders:

### Stat Widgets

Computed by iterating all rows in `mod_hermesagent_instances`:

| Stat | Condition |
|---|---|
| Total Agents | `count($deployments)` |
| Healthy / Active | `status === 'Active'` |
| Suspended | `status === 'Suspended'` |
| Failed / Error | anything else (includes `Pending`, `Error`, `Terminated` if the row wasn't deleted) |

### Active Client Deployments Table

A joined query across `mod_hermesagent_instances`, `tblhosting`, `tblclients`, `tblproducts`, `tblservers`, showing Service ID, client name (+ company if set), product name, VPS server IP, a clickable Dashboard URL (port-based or Caddy-subdomain based on the server's `secure` flag), and a status badge.

This is a **read-only monitoring view** — there are no bulk actions (e.g. bulk restart, bulk suspend) here; per-service actions still happen via the standard WHMCS **Admin Custom Buttons** on each service's management page (see [Provisioning § Custom Buttons](provisioning.md#custom-buttons)).

### Quiz Leads Panel

Embedded directly below the deployments table — see [Lead Generation § Admin CRM Workflow](lead-gen.md#admin-crm-workflow) for full detail on this section.

## UI Notes

- The addon injects a Google Fonts (`Outfit`) stylesheet and a large inline `<style>` block directly into the page — this only affects the addon's own output region, not the rest of WHMCS admin.
- All dynamic content (client names, product names, server IPs, lead answers) is passed through `htmlspecialchars()` before rendering, mitigating stored-XSS via any of these fields (e.g. a client setting their name to `<script>`).

## Related

- [Installation § What One-Click Product Setup Automates](installation.md#what-one-click-product-setup-automates)
- [Provisioning](provisioning.md) — the underlying deployment mechanics this addon monitors
- [Lead Generation](lead-gen.md) — the quiz CRM embedded in this addon
