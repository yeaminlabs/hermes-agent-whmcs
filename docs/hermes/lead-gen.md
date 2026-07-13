# Lead Generation — Quiz Capture

`hermes-quiz-submit.php` is a standalone, self-contained endpoint (not a WHMCS module hook) designed to receive submissions from an external marketing "personality quiz" and store them as leads visible inside the [Admin Addon](admin-addon.md)'s dashboard.

## Deployment

Drop the file directly into the **WHMCS webroot** — the same directory as `index.php` and `configuration.php` — since it `include`s `configuration.php` to obtain database credentials:

```
<whmcs_root>/hermes-quiz-submit.php
```

No WHMCS module registration or activation is required; it works as soon as the file exists and is web-accessible, e.g.:

```
https://yourwhmcs.example.com/hermes-quiz-submit.php
```

Point your quiz frontend's submit handler (hosted anywhere — a separate marketing site, landing page builder, etc.) at this URL.

## Request Contract

| Method | Content-Type | Fields |
|---|---|---|
| `POST` | `application/x-www-form-urlencoded` or `multipart/form-data` | `name`, `email` (required), `whatsapp`, `profile`, `answers` |

CORS is wide open by design (`Access-Control-Allow-Origin: *`) so the quiz can live on any domain. `OPTIONS` preflight requests return `204` immediately.

### Example Request

```bash
curl -X POST https://yourwhmcs.example.com/hermes-quiz-submit.php \
  -d "name=Jane Doe" \
  -d "email=jane@example.com" \
  -d "whatsapp=+15551234567" \
  -d "profile=architect" \
  -d 'answers={"1":{"letter":"A"},"2":{"letter":"C"}}'
```

### Response

```json
{"ok": true, "action": "created", "id": 42}
```

or, for a repeat submission from the same email:

```json
{"ok": true, "action": "updated"}
```

Error cases return `{"ok": false, "error": "..."}` with a `200` status (no non-2xx HTTP status codes are used for errors — check the `ok` field, not the HTTP status).

| Error | Cause |
|---|---|
| `POST only` | Non-POST, non-OPTIONS request |
| `WHMCS config not found` | `configuration.php` missing next to the script |
| `Invalid email` | `email` fails `FILTER_VALIDATE_EMAIL` |
| `DB connection failed` | PDO connection to WHMCS's MySQL failed |

## Input Handling

```php
function clean($v) {
    return htmlspecialchars(trim((string)($v ?? '')), ENT_QUOTES, 'UTF-8');
}
```

`name`, `whatsapp`, `profile`, `answers` are passed through `clean()` (trim + HTML-entity-encode). `email` uses `filter_var(..., FILTER_SANITIZE_EMAIL)` followed by `FILTER_VALIDATE_EMAIL` validation — invalid emails are rejected outright.

`profile` is further constrained to an allowlist:

```php
$allowed_profiles = ['architect', 'sprinter', 'craftsman', 'explorer'];
```

Any other value is coerced to `'unknown'`.

All database access uses PDO **prepared statements** with bound parameters — no raw string interpolation into SQL, so this endpoint is not vulnerable to SQL injection via any of the accepted fields.

## Database Schema

The table is created on first request if missing (`CREATE TABLE IF NOT EXISTS`), so no migration step is needed:

### `mod_hermesagent_quiz_leads`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED, PK, auto-increment | |
| `name` | VARCHAR(120) | Default `''` |
| `email` | VARCHAR(200) | Indexed, not unique at the DB level (application-level dedup — see below) |
| `whatsapp` | VARCHAR(30) | Default `''` |
| `profile` | VARCHAR(30) | Indexed; one of `architect`/`sprinter`/`craftsman`/`explorer`/`unknown` |
| `answers` | TEXT | Freeform — typically a JSON blob of quiz answers |
| `status` | VARCHAR(20) | Indexed; `new` / `contacted` / `converted` / `rejected` |
| `notes` | TEXT | Admin-editable freeform notes |
| `created_at` | DATETIME | Default `CURRENT_TIMESTAMP` |
| `updated_at` | DATETIME | Auto-updates `ON UPDATE CURRENT_TIMESTAMP` |

This same table is also defined in the addon's `hermesagent_activate()` and lazily re-checked in `hermesagent_output()` — both use Laravel's schema builder (`increments`/`string`/`text`), while `hermes-quiz-submit.php` uses raw SQL DDL. The column definitions are compatible, so whichever code path runs first "wins" without conflict.

## Deduplication Logic

Submissions are deduplicated by **email** at the application level:

1. `SELECT id FROM mod_hermesagent_quiz_leads WHERE email = ? LIMIT 1`
2. If found: `UPDATE ... SET name=?, whatsapp=?, profile=?, answers=?, updated_at=NOW() WHERE email=?` — **the existing `status` and `notes` are preserved**, so a lead that was already marked "contacted" won't be silently reset to "new" if the same person retakes the quiz.
3. If not found: `INSERT ... status='new'`.

## Answers Format

The `answers` field is stored as opaque text but is expected by the Admin Addon's rendering logic to be a JSON object keyed by question number, each value containing at least a `letter` field:

```json
{
  "1": {"letter": "A"},
  "2": {"letter": "C"},
  "3": {"letter": "B"},
  "4": {"letter": "A"},
  "5": {"letter": "D"}
}
```

The addon's leads table renders this as a compact string `Q1:A Q2:C Q3:B Q4:A Q5:D` for questions 1–5 (see `hermesagent_output()` in the addon). If your quiz has more or fewer than 5 questions, or a different JSON shape, this summary column will render incompletely — the raw `answers` value is still stored intact regardless.

> Note: `answers` is passed through `clean()` (HTML-entity-encoded) before storage. If your quiz frontend submits real JSON, quotes will be preserved as literal `"` characters (not escaped by `ENT_QUOTES` unless the string itself needs escaping), so `json_decode()` on the admin side should still succeed for typical JSON — but any `<`/`>`/`&` characters inside answer text would be HTML-entity-encoded, which could break strict JSON parsing if answers ever contain those characters. Keep the quiz's answer payload to a simple letter/id scheme (as shown above) to avoid this.

## Admin CRM Workflow

Inside **Addons → Hermes Agent Manager**, the **Quiz Leads — Beta Program Applicants** panel shows:

- Summary badges: Total, New, Contacted, Converted counts
- A full table: ID, Name, Email (mailto link), WhatsApp (click-to-chat `wa.me` link, non-digits stripped), Profile Type (emoji-labeled badge), Answers summary, Date, Status badge, Edit action

### Updating a Lead

Clicking **Edit** opens a modal (pure JS, no page reload until submit) pre-filled with the lead's current status and notes. Submitting POSTs `update_lead_status`, `lead_id`, `lead_status`, `lead_notes` back to the same addon page:

```php
$leadStatus = in_array($_POST['lead_status'], ['new','contacted','converted','rejected'])
              ? $_POST['lead_status'] : 'new';
$leadNotes  = htmlspecialchars($_POST['lead_notes'] ?? '', ENT_QUOTES, 'UTF-8');
Capsule::table('mod_hermesagent_quiz_leads')
    ->where('id', $leadId)
    ->update(['status' => $leadStatus, 'notes' => $leadNotes, 'updated_at' => now()]);
```

Status values are allowlisted server-side (defaulting to `new` for anything unrecognized), and notes are HTML-escaped before storage — mitigating stored XSS from the admin-entered notes field.

### Profile Type Labels

| Profile | Badge |
|---|---|
| `architect` | 🎯 Architect |
| `sprinter` | ⚡ Sprinter |
| `craftsman` | 🔥 Craftsman |
| `explorer` | 🚀 Explorer |
| *(anything else, incl. `unknown`)* | Raw value shown as-is, with a neutral gray badge |

## Security Notes

- The endpoint has **no rate limiting, CAPTCHA, or authentication** — it's designed to be publicly writable from any origin. Consider fronting it with rate limiting (e.g. at the reverse proxy/CDN level) if it becomes a spam target, since the open `Access-Control-Allow-Origin: *` plus no auth means anyone who discovers the URL can script submissions.
- Because `email` is not database-unique (only indexed), a race condition between two near-simultaneous submissions from the same new email could theoretically insert two rows before either `SELECT` sees the other's row — low-impact for a lead-capture form, but worth knowing if exact-duplicate prevention is critical.
- The script trusts `configuration.php`'s `$db_host`/`$db_name`/`$db_username`/`$db_password` globals exactly as WHMCS itself does — no additional credentials are needed or stored.

## Related

- [Admin Addon § Quiz Leads Panel](admin-addon.md#quiz-leads-panel)
- [Installation](installation.md) — where to place this file during setup
