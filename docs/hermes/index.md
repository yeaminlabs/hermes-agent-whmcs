# Hermes Agent — WHMCS Module Documentation

Hermes Agent is a WHMCS hosting product that provisions, sells, and manages dockerized **Hermes AI agent** instances on customer-facing VPS infrastructure. It automates the full lifecycle of a Hermes deployment — from checkout to container provisioning, credential management, LLM provider configuration, messaging-channel setup, and account suspension/termination — directly from WHMCS.

This is the primary documentation set for the module. It covers installation, provisioning internals, the client area experience, LLM provider management, the admin addon, lead-generation quiz integration, and troubleshooting.

## What Hermes Agent Does

Hermes Agent packages [Hermes](https://hermes-agent.nousresearch.com/) (Nous Research's autonomous agent runtime) as a billable WHMCS hosting product. Each purchased service becomes an isolated Docker container running on a VPS you control, with:

- A password-protected **web dashboard** for interacting with the agent
- Optional **OpenAI-compatible API** endpoint for third-party integrations
- Configurable **LLM provider** (bring-your-own-key or Nous Portal / AWS Bedrock-backed default)
- Optional **messaging platform** bridges (Telegram, Discord, Slack)
- Per-client **data isolation**, backups, and a destructive **kill switch**

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                              WHMCS                                    │
│                                                                         │
│  ┌────────────────────────┐        ┌────────────────────────────┐   │
│  │ modules/servers/        │        │ modules/addons/              │   │
│  │   hermesagent/           │        │   hermesagent/                │   │
│  │  (Provisioning Module)   │        │  (Manager Addon)              │   │
│  │                          │        │                                │   │
│  │  CreateAccount           │        │  One-Click Product Setup      │   │
│  │  SuspendAccount          │        │  Deployment Dashboard          │   │
│  │  UnsuspendAccount        │        │  Quiz Lead CRM                 │   │
│  │  TerminateAccount        │        │                                │   │
│  │  ChangePassword          │        └────────────────────────────┘   │
│  │  ChangePackage           │                                          │
│  │  ClientArea / manage_llm │        ┌────────────────────────────┐   │
│  │  Custom buttons          │        │ mod_hermesagent_instances    │   │
│  └───────────┬──────────────┘        │ mod_hermesagent_quiz_leads   │   │
│              │  SSH (phpseclib)      │  (WHMCS MySQL DB)             │   │
└──────────────┼────────────────────────────────────────────────────┘   │
               │
               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Customer VPS (per WHMCS server)                  │
│                                                                         │
│   setup-vps.sh  →  installs Docker, generates SSH access hash          │
│                                                                         │
│   /srv/hermes/<serviceid>/data/                                       │
│       .env            (provider keys, dashboard auth, messaging)      │
│       config.yaml      (active model, dashboard/tool-loop settings)   │
│                                                                         │
│   docker run nousresearch/hermes-agent:<tag> gateway run               │
│       container: hermes-<serviceid>                                   │
│       ports:  <dash_port>:9119   <api_port>:8642                       │
│                                                                         │
│   [optional] Caddy reverse proxy                                       │
│       hermes-<serviceid>.hermes.deltadns.xyz     → dash_port           │
│       hermes-api-<serviceid>.hermes.deltadns.xyz → api_port            │
└─────────────────────────────────────────────────────────────────────┘
```

Each hosting service maps to one Docker container on the assigned WHMCS server. The provisioning module talks to that server exclusively over SSH (via phpseclib 1/2/3, with automatic version detection) using either a password or an SSH key ("Access Hash").

## Key Features

| Feature | Description |
|---|---|
| **BYO LLM Providers** | OpenRouter, OpenAI, Anthropic, Nous Portal, or any custom OpenAI-compatible endpoint. Clients supply their own API keys, editable anytime post-purchase. |
| **Messaging Channels** | Optional Telegram, Discord, or Slack bot bridging configured via bot token. |
| **Client Dashboard** | Branded client-area UI showing dashboard credentials, live CPU/memory stats, token usage analytics, and a website live-chat widget generator. |
| **Lead Gen Quiz** | Standalone PHP endpoint (`hermes-quiz-submit.php`) captures quiz-based leads directly into a WHMCS-adjacent table, manageable from the addon's Quiz Leads CRM. |
| **One-Click Product Setup** | Admin addon auto-creates all Custom Fields and Configurable Options + pricing rows needed for checkout, for any product using the `hermesagent` server module. |
| **Full Lifecycle Automation** | Create, suspend, unsuspend, terminate, change password, change package (resource tier) — all driven by SSH commands against Docker and (optionally) Caddy. |
| **Admin & Client Tools** | Restart, view logs, regenerate password, download full agent backup, force redeploy, SSH health check, kill switch. |

## Prerequisites

- WHMCS with **Full Administrator** addon permissions available to grant
- A WHMCS **Server** entry per VPS, using SSH credentials (password or access hash)
- One or more Ubuntu/Debian (or similar) **VPS hosts** with root/sudo SSH access, each running `setup-vps.sh` once
- `phpseclib` available in the WHMCS environment (bundled with WHMCS 7/8)
- Docker Hub access to `nousresearch/hermes-agent` images on the VPS
- (Optional) [Caddy](https://caddyserver.com/) installed on the VPS for HTTPS reverse proxying via subdomains

## Quick Start

1. Run `install-whmcs.sh` from your WHMCS root to install both modules.
2. Provision a VPS and run `setup-vps.sh` on it — copy the generated **Access Hash** into a new WHMCS Server entry.
3. Create a WHMCS product using server module **Hermes Agent Hosting**, assign it to that server.
4. Activate the **Hermes Agent Manager** addon, grant Full Administrator permissions.
5. In **Addons → Hermes Agent Manager**, run **One-Click Product Setup** against your product.
6. Order the product (or have a client order it) — `CreateAccount` deploys the container automatically.
7. (Optional) Drop `hermes-quiz-submit.php` into your WHMCS webroot and point your marketing quiz at it to start capturing leads.

See [Installation](installation.md) for full details.

## Documentation Index

| Doc | Covers |
|---|---|
| [Installation](installation.md) | `install-whmcs.sh`, manual install, VPS setup, product configuration |
| [Provisioning](provisioning.md) | Server module lifecycle, Docker deployment, ports, DB schema |
| [Client Area](client-area.md) | Client-facing dashboard, LLM management page, custom buttons |
| [LLM Management](llm-management.md) | Provider details, env/config storage, security model |
| [Admin Addon](admin-addon.md) | One-click setup, deployment monitoring, custom fields/options automation |
| [Lead Generation](lead-gen.md) | Quiz submission endpoint, DB schema, CRM workflow |
| [Troubleshooting](troubleshooting.md) | Common failures and how to diagnose them |
