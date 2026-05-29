# SeAT Broadcast

> Formerly *SeAT Discord Pings*. The Composer package name (`mattfalahe/seat-discord-pings`), route prefix (`/discord-pings`), database tables and PluginBridge capabilities are unchanged.

The **Planning HUB** for fleet commanders running on [SeAT](https://github.com/eveseat/seat). Send fleet broadcasts to Discord with rich embeds, schedule pings for later, and (with **Manager Core** + **Structure Manager** + optionally **Mining Manager** installed) surface every upcoming structure timer, manual fleet op, and moon extraction on a dedicated **FC Opportunities** board — turn any opportunity into a pre-filled form-up broadcast in one click.

[![Latest Version](https://img.shields.io/github/v/release/MattFalahe/seat-discord-pings)](https://github.com/MattFalahe/seat-discord-pings/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-blue.svg)](https://github.com/MattFalahe/seat-discord-pings/blob/master/LICENSE)
[![SeAT 5.0](https://img.shields.io/badge/SeAT-5.0-blue)](https://github.com/eveseat/seat)

## Mental model

Two surfaces with clearly separated jobs:

- **Broadcasts Calendar** — *what is being broadcast and when*. Shows your scheduled pings and manual broadcast history. Nothing else.
- **FC Opportunities** — *what is coming up that an FC might want to broadcast about*. Shows structure timers, mining extractions, and manual fleet ops ingested from other plugins via Manager Core's EventBus. Plan from here; the broadcasts you create from this board land on the Calendar.

SeAT Broadcast works perfectly fine standalone — without Manager Core installed it's a pure Discord webhook sender. Every cross-plugin integration is `class_exists`-guarded and optional.

## Features

### Core (works standalone)

- 📢 **Send broadcasts to Discord** with rich embeds, role mentions, channel links, doctrine integration
- 📅 **Schedule pings** for any future EVE time, one-off or recurring (hourly/daily/weekly/monthly)
- ✏️ **Edit / delete scheduled pings** — recurring series changes apply from the new time onwards
- 🕒 **EVE → local time auto-conversion**: every EVE timestamp gains a hover tooltip showing your browser-local time; high-priority surfaces (Calendar, FC Opportunities, Scheduled list) also show an inline `· HH:MM local` pill. Browser-detected via `Intl.DateTimeFormat()` — DST-safe, no per-user configuration
- 🔀 **Enter In: [EVE/UTC | My local]** toggle on the schedule form. Default is EVE/UTC (FC convention); switch to local and the form converts to UTC on submit
- 📆 **Broadcasts Calendar** — scheduled pings + manual broadcast history, color-coded by webhook
- 📜 **Broadcast History** with resend capability
- 📝 **Personal + global templates** with full field support
- 🧪 **Webhook testing** before going live
- 🧹 **Bulk-clear** inactive scheduled pings older than 7 or 30 days (Fleet Coordinator tier only)
- 🔐 **Webhook URLs encrypted at rest** + TLS verification on every Discord send
- 🚦 **Per-webhook rate limiting** (configurable per-minute and per-hour caps)
- ‼️ **PREPING ‼️** broadcast type alongside Fleet / Announcement / Message
- 🎨 **Visual color picker** for embed colours
- 📡 **Multi-webhook send** to fan out a single broadcast across channels

### With Manager Core + Structure Manager

- 🎯 **FC Opportunities board** — focused planning surface listing upcoming structure timers and manual fleet ops with live countdowns
- 🛰️ **Structure timer ingest** — auto-detected reinforce / anchor / fuel timers + manually entered ops flow in via the `structure_manager.timer.*` event family
- ⚔️ / 🛡️ / 📌 op-type icons for hostile / defense / generic ops
- 📨 **Pre-timer reminder pings** at T-24h and T-1h (per-webhook opt-in via `Receive structure timer alerts` flag)
- 🏢 **Corp-scoped webhook routing** — a webhook can be scoped to one corporation so alerts only fire for that corp's events
- 📡 **Live coordination badge** — every FC Opportunities row shows `📡 N scheduled` (global count of formup broadcasts already planned for that op) so FCs don't duplicate effort

### With Manager Core + Mining Manager (v2.0.1+)

- ⛏️ **Mining extractions on FC Opportunities** — moon extractions appear as a distinct mining category with the 48h fleet-able window
- 🏞️ **Multi-FC friendly** — mining ops welcome multiple formup pings (different timezones, fleet sizes, drops across the window); the badge stays informational
- 📨 **T-2h pre-expiry alerts** (per-webhook opt-in via `Receive mining extraction alerts` flag)
- 🔗 **Deep link** from any mining row to the Mining Manager extraction detail page (ore composition, jackpot status, countdowns)

### Publishes events to Manager Core (HR Manager + future subscribers)

- 📡 **`pings.broadcast.sent`** — fired after every successful Discord broadcast (manual, scheduled, test, pre-event alert)
- 📡 **`pings.formup.scheduled`** — fired when an FC schedules a broadcast correlated with a tactical event (the strongest "this user is acting as an active FC" signal)

### Diagnostic & operational

- 🩺 **Admin-only Diagnostic page** at `/discord-pings/diagnostic` (NOT in sidebar) with 6 tabs: Health Checks, Master Test, System Validation, Settings Health, Data Integrity, Broadcast Trace
- 🗺️ **Notification Routing Map** (7th Settings tab) — read-only snapshot showing which webhooks fire for each event, with corp scope + active/dormant state
- 📚 **In-plugin Help & Documentation** with scenario-driven permissions docs, EVE→local time explanation, full feature reference

## Requirements

- **SeAT** 5.0+
- **PHP** 8.0+
- **Laravel** 10.0+
- **Discord webhook URLs**
- **Queue worker + scheduler** running for scheduled pings and EventBus delivery
- *Optional* — Manager Core, Structure Manager, Mining Manager (for the cross-plugin integrations described above)

## Installation

```bash
composer require mattfalahe/seat-discord-pings
```

SeAT auto-runs migrations and publishes assets on container restart. For Docker installs:

```bash
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
```

## Permissions

Two-tier model for scheduled-broadcast surfaces (Calendar, FC Opportunities, scheduled list):

| Permission | Tier | What it grants |
|---|---|---|
| `discordpings.view` | baseline | Sidebar entry visibility |
| `discordpings.send` | **FC** | Send manual broadcasts. Schedule + see + manage own scheduled pings. Use FC Opportunities planner. See own pings on the Calendar |
| `discordpings.send_multiple` | extra | Send a single broadcast to multiple webhooks at once |
| `discordpings.view_history` | baseline | See own broadcast history (Calendar + History page) |
| `discordpings.view_all_history` | extra | See every user's broadcast history |
| `discordpings.manage_webhooks` | admin | Manage Settings page (webhooks / roles / channels / staging / PAP types / structure-timer + mining-alert toggles / routing map) |
| `discordpings.manage_scheduled` | **Fleet Coordinator** | Adds visibility + control over OTHER users' scheduled pings, plus the bulk-clear of inactive pings (stacks ON TOP of the FC tier) |
| `discordpings.manage_templates` | baseline | Manage own broadcast templates |
| `discordpings.manage_global_templates` | admin | Manage global broadcast templates visible to everyone |
| `discordpings.admin` | admin | Access the `/discord-pings/diagnostic` admin page |

The Help & Documentation page inside the plugin has a **Permissions** section with five role recipes (Solo FC / Regular FC / Fleet Coordinator / Auditor / Plugin Admin) showing the exact permission combinations to assign — start there.

## Settings page (7 tabs)

| Tab | What lives here |
|---|---|
| Webhooks | Create / edit / delete / test Discord webhooks. Per-webhook flags for structure + mining alerts, corp scope, embed colour |
| Discord Roles | Roles available for `@role` mentions in broadcasts |
| Discord Channels | Pre-registered channels for inline `#channel` links |
| Staging Locations | Pre-registered staging spots for quick formup-location selection |
| PAP Types | Configurable PAP categories (Strategic / Peacetime / CTA seeded by default) |
| Structure Timers | Master toggles for pre-event alerts (structure + mining), default formup lead time, MC / SM / MM detection badges |
| Routing Map | Read-only snapshot of which webhooks will fire for each event right now, with corp scope + master switch state |

## Form-up workflow

1. Open **FC Opportunities** (sidebar; only visible when MC + SM are installed)
2. Pick a row (structure timer, fleet op, or mining extraction)
3. Click one of the action buttons:
   - **📤 Form-up** — opens the Send Broadcast page pre-filled with urgent "forming up NOW" copy + PREPING embed type. Use when staging right now
   - **🕒 Schedule** — opens the Scheduled Broadcast form pre-filled, defaulting to the timer minus your configured formup lead time (5–720 min, set on Settings > Structure Timers)
   - **ℹ️ Details** — deep-links to the source plugin's detail page (Structure Manager structure board or Mining Manager extraction view)
4. Review, adjust, submit
5. The resulting scheduled or sent broadcast naturally appears on the Calendar via the standard broadcast pipeline

## Integration with seat-fitting

Auto-detects both `CryptaTech/seat-fitting` and `Denngarr/seat-fitting`. Doctrine dropdown appears on the broadcast forms with clickable links in Discord embeds. Falls back to plain-text doctrine field if no fitting plugin is installed.

## Time zones

All times are stored and processed in **EVE Time (UTC)** — single source of truth. The user-facing layer auto-converts to the viewer's browser timezone:

- Every EVE timestamp in the plugin carries a **hover tooltip** with the full local date/time (e.g. `Local: 2026-05-25, 14:00 EDT`)
- High-priority surfaces (Calendar event modal, FC Opportunities EVE-time column, Scheduled Broadcasts list, scheduled-ping prefill banner) also show an **inline pill**: `2026-05-25 18:00 EVE · 14:00 local`
- The schedule form has an **Enter In: [EVE/UTC | My local]** toggle so FCs can choose their input convention. Default is EVE/UTC; switch to local and the form converts to UTC on submit
- Help & Documentation page shows your **detected browser timezone** with a live sample so you can verify what the browser reports

Same conversion mechanism as Discord / Google Calendar / GitHub. DST-safe via the browser's IANA timezone database.

## Scheduled jobs

| Command | Schedule | Purpose |
|---|---|---|
| `discordpings:process-scheduled` | every minute | Sends due scheduled pings; handles rate-limit retries within a 15-minute safety window |
| `discordpings:cleanup-history` | daily | Prunes broadcast history (90-day retention) + resolved tactical events (14-day retention). Both windows configurable in `discordpings.config.php` |

Both are auto-registered in SeAT's schedule on first install.

## Troubleshooting

The Diagnostic page (`/discord-pings/diagnostic`, admin-only) covers most operational concerns — start there. Master Test runs ~15 pass/warn/fail checks: required tables exist, settings within valid ranges, cron processing, MC EventBus subscriptions registered, webhook URLs encrypted at rest, and more.

Quick checks if you can't reach the Diagnostic page:

- **Pings not sending**: verify Laravel queue worker + scheduler are running. Run `docker compose exec front php artisan schedule:list` to confirm `discordpings:process-scheduled` is registered
- **Webhook test fails**: verify the URL starts with `https://discord.com/api/webhooks/`. Confirm the webhook wasn't deleted in Discord
- **Permission issues**: assign permissions via SeAT's Access Management UI. The Help & Documentation page's Permissions section has role recipes for the common patterns
- **Sidebar entry missing for FC Opportunities**: requires both Manager Core AND Structure Manager installed (class_exists detection)
- **Mining extractions not appearing**: requires Mining Manager **v2.0.1+** (the version that ships the EventBus publisher). Check Settings > Structure Timers tab for the Mining Manager detection badge
- **Stale data**: run `docker compose exec front php artisan migrate:status` to confirm all migrations applied (look for `Yes` next to every `discord_*` migration)

## Rate limits

Discord enforces ~30 requests/minute per webhook. The plugin enforces its own configurable per-webhook caps (default 10/min, 100/hour — set in `config/discordpings.php`). Rate-limited sends retry on the next cron tick within a 15-minute safety window before being marked failed.

## Support

- **Issues**: [GitHub Issues](https://github.com/MattFalahe/seat-discord-pings/issues)
- **SeAT Discord**: [SeAT community Discord](https://discord.gg/azquy29nqs)
- **SeAT Docs**: [docs.eveseat.net](https://docs.eveseat.net/)

## Contributing

1. Fork the repo
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes
4. Open a Pull Request

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).

## Credits

- Created by [Matt Falahe](https://github.com/MattFalahe)
- Built for the [SeAT](https://github.com/eveseat/seat) Alliance / Corporation Management Platform
- Inspired by the EVE Online community's need for better fleet communication tools

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed release notes.
