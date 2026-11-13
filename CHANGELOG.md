# Changelog

Below is a summary of all major updates, improvements, and fixes made to **SeAT Broadcast** (the plugin was renamed in v2.0.0; earlier releases shipped as "SeAT Discord Pings" — the Composer package name, route prefix, database tables, and PluginBridge capabilities are unchanged, so historical entries here use the old name where they describe behaviour from those versions).
Each version entry lists key changes for easier reference and upgrade planning.

---

## 🙋 Version 2.0.2 — *November 2026*

**Requests and Roles.** Line members get their own way into the plugin. You set up buttons in Settings ("Help, I'm tackled", "I want a mining fleet"), each with a webhook and a role to ping, and anyone holding the new **Line Member** permission presses one from the new **Ping Requests** page. The message reaches Discord with the sender's SeAT main character and corporation, plus their Discord name when seat-connector is installed and the account is linked, because the character in trouble can be any alt. FCs answer a request from a link in its Discord message, and everyone can see who did.

**Backwards compatible.** Three new tables, a column on one of them and one new permission. Nothing that exists is changed or rewritten, no role loses anything, and nobody gets the page until you hand out the permission. No button posts anywhere until you give it a webhook and switch it on. The Send form starts empty, as before, until you switch on its defaults.

### 🎉 New Features

**🙋 Ping Requests**
- New **Ping Requests** sidebar page for anyone with `discordpings.view` and `discordpings.line_member`
- Two kinds of button. **Emergency** buttons are red and post the moment they're pressed: no confirm box, no cooldown and no limit from the plugin, since every second counts. **Request** buttons show what will be posted first, then rest for a per-member cooldown you set on each button
- **Section names (format)** is a list with one row per section: what it asks, its box (text, system and spot, my characters, ship, yes / no, long text, or a fixed line that goes out as written) and when it's filled in, on a saved form or after the press. Start from Tackled, Mining fleet or PvP fleet, or paste your alliance's format into any section name and each line becomes a row with its box already picked. A box left empty is dropped from the message
- **Saved forms** per button and per member (20 at most), so one press can carry the ship, the system and the cyno
- An emergency can ask a few things **after the press**, like whether panic is running, an inhibitor is up or what's on grid. The press still posts at once. The questions then show under **Active requests** at the top of the page, and each answer edits that same Discord message without pinging the role again. A request button asks them in its confirm box, and no saved form keeps them
- **Active requests** stay through a reload and show in another tab, for 10 minutes after the press or until the member presses **Close**. Having one open never holds up the next press, which gets its own entry
- **Send now, fill in after** posts an emergency with no saved form at once and leaves every section to fill in under Active requests
- On Discord each section name is bold, with a line between sections. While an emergency can still change, a **Live updates** line says so and counts down to when it closes in each reader's own time. It also says when the sender last added to it and how long ago that was, and when they closed it early
- Boxes **suggest as you type**. A system and spot box offers systems after three letters, then the moons, planets, structures, stations and gates in the one you pick. A my characters box offers the member's own characters, and a ship box offers every published ship in SeAT's static data. Anything typed by hand stays as typed. It all comes from SeAT's database. Structures are limited to the member's own corporations and alliances, and a moon names its structure when SeAT holds a moon extraction for it
- A button can be limited to one or more Broadcast permissions, where holding any one is enough, and to a corporation or an alliance. SeAT roles and squads still decide who holds those permissions
- **Role to ping** can be typed, or picked from every role the Discord Roles tab and seat-connector know about, with a badge saying where each one came from, a search box and a filter to narrow the list to one source
- An optional line on the button tells members who it notifies, such as Cap Umbrella or Designated SIG. It's never posted
- Only the role set on the button can be pinged, whatever a member types, and member text can't hide a link behind other words
- A press waits for Discord's answer, so "Sent to Discord" means it got there. If it didn't, the member is told and the cooldown is handed back
- Requests are not broadcasts: they don't publish `pings.broadcast.sent`, so HR Manager won't take a member asking for help for an active FC
- The bottom of the page lists what the member sent in the last 7 days, 10 to a page, or 25, 50 or 100 if they pick
- **Settings > Ping Requests** is the new tab for all of this. **Load examples** adds four buttons to start from, switched off and with no webhook. Buttons can be deleted one at a time, ticked and deleted together with **Delete selected**, or all at once with **Delete all**, and the forms members saved for them go too

**🔐 Line Member permission**
- `discordpings.line_member` is the permission for the member side of the plugin. `discordpings.view` is still the menu and Help & Documentation, and its description now says so

**🎯 FC Opportunities**
- Requests that reached Discord show on FC Opportunities for 24 hours, in a table of their own above the board, newest first and 10 to a page. Each row says what kind of request it is, who sent it and what they wrote
- An FC sees the requests made with buttons open to their corporation or alliance, or to everyone. Admins see them all
- **Form-up** on a request opens the Send form starting from it: the button, who asked and what they wrote
- FC Opportunities shows in the menu whenever Manager Core is installed. It used to need Structure Manager too, so an install with only Mining Manager, or one using it for requests alone, never saw it

**🤝 Answering a request**
- Each request's Discord message has a **Form up in SeAT** link for FCs. It opens the Send form filled in from the request, the same as Form-up, and only for an FC who could see the request on FC Opportunities
- A request counts as answered once a broadcast sent from it goes out, whether the FC came from the link or from Form-up, and whether it was sent now or scheduled. Opening the form isn't answering
- The request's Discord message then gets an **Answered by** line naming each FC and when. It's an edit, so nobody is pinged again, and an emergency still being added to keeps it
- FC Opportunities has an **Answered by** column, and History shows who answered a request and which request a broadcast answered
- The member sees a **Handled by** badge on their request, in their list and under Active requests, without reloading the page
- Each button can set what an answer starts from under **When an FC answers**: webhook, broadcast type, mention, Discord channel, colour, PAP type and comms. Where to form up and what to fly stay the FC's call

**📝 Broadcast form**
- **Settings > Send Form** picks how Send and Schedule start: empty, or filled in with defaults for every box except the message. The FC name can start as the sender's SeAT main. The message only starts filled in when answering a request
- The message box is about four times taller on every form
- A formatting bar above it adds bold, italic, underline, strikethrough, spoilers, code, quotes, lists and links. **Discord time** turns an EVE or local time into a Discord timestamp in any of its styles, which everyone reads in their own time zone
- FC Name suggests your own characters, Formup Location suggests systems and then the stations, structures and other spots in them, and Comms and Doctrine suggest what you've typed in your own broadcasts before
- Send, Schedule and the template pages share one set of boxes, so each has every field the others do
- Preview shows the formatting and Discord timestamps the way Discord will

**📜 History**
- Requests show on the History page with a **Request** badge, or a red **Emergency** one, and an All / Broadcasts / Ping requests filter. They can't be resent
- They stay off the Calendar and out of an FC's recent pings

**🩺 Diagnostics**
- Master Test checks the two new tables and warns about a switched-on button with no active webhook, an emergency button that pings nobody, a button limited to a permission that no longer exists, or a button nobody can see because nobody holds Line Member together with what it asks for. It also says whether seat-connector was found

### 🐛 Bug Fixes

- Quick Links on the Help page now only offer what you can open. Someone holding View alone used to get links that ended in a 403
- The What's New box on the Help page can be read on a light SeAT theme. Its text used to fade into the background
- The plugin's tables have proper page buttons and search boxes. A Bootstrap script they load was never shipped with the plugin, so each of those pages got a 404 and the page numbers ran together as plain text
- **Schedule** on the Send form left the webhook behind, and a role or custom mention it carried over stayed hidden on the schedule page. It now carries every field
- Templates kept no webhook, custom mention or Discord channel, and one saved from the Send form lost its role. They now keep every field

### 📖 Documentation

- **Mining extraction windows.** Mining Manager now gives each chunk the mining window its refinery's moon drilling rigs allow: 48 hours, or 72 / 96 hours with a Moon Drilling Stability or Proficiency rig. FC Opportunities already takes each extraction's deadline from Mining Manager's `window_closes_at`, so a rigged moon shows its longer window with no change here. Help and the README no longer say a flat 48h
- Help & Documentation has a Ping Requests section, a Live Updates box under Key Features and a Line Member recipe, and its NEW badges now mark what 2.0.2 adds. The permission list now uses the name SeAT shows for View
- Help covers answering a request, the Send Form tab, the formatting bar and Discord time, and the suggestions. It no longer says FC Opportunities needs Structure Manager
- **Plugins & Ecosystem** in Help: every plugin SeAT Broadcast works with, crucial and optional, and whether this server has it. Key Features gives each feature that needs another plugin a box of its own, marked red while Manager Core is missing and yellow while only an optional plugin is
- Help and Settings no longer say structure timers show on the Calendar. They've been on FC Opportunities since 2.0.0, where **Form-up** sends a broadcast straight away and **Schedule** plans one for later

### 🧪 Under the hood

- A test suite and GitHub checks now run on every push

### 📦 Upgrade

Migrations run on restart. Then give **Line Member** to the role your members hold and add your buttons in Settings > Ping Requests. If you'd like the Send form to start filled in, set that up in Settings > Send Form.

```
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
```

### ⚠️ Breaking Changes

None. v2.0.1 installs upgrade cleanly with all settings, webhooks, scheduled pings, broadcast history, templates and tactical events preserved.

---

## 🩹 Version 2.0.1 — *June 2026*

Patch release. One blocker bug fix on top of v2.0.0. No migrations, no schema changes, no new features. Fully backwards-compatible.

### 🐛 Bug Fixes

**Send Broadcast and Scheduled Broadcast forms crashed without `seat-fitting` installed.** Four controller methods (`PingController::index`, `ScheduledController::create`, `TemplateController::create`, `TemplateController::edit`) handed the doctrines list to their views as a raw PHP array. The blade templates guarded the doctrine dropdown with `@if((... ?? collect())->count() > 0)`, which expects a Collection. PHP's null-coalesce only fires on null, so an empty array (the case when no fitting plugin is installed) fell through to `[]->count()` and crashed the page with a fatal `Illuminate\View\ViewException`.

The fix wraps each `DiscordHelper::listFittingDoctrines()` call with `collect()` at the four affected call sites so views always receive a Collection. The helper signature stays `: array` (no public API change). `ScheduledController::edit` already had this wrap from earlier work; the other four call sites were missed during the v2.0.0 refactor.

Affected pages, all broken in v2.0.0 when `seat-fitting` is not installed (most installs):
- Send Broadcast (`/discord-pings/send`)
- Scheduled Broadcast create (`/discord-pings/scheduled/create`)
- Template create (`/discord-pings/templates/create`)
- Template edit (`/discord-pings/templates/{id}/edit`)

Manual doctrine entry without `seat-fitting` was always intended: every form has an `@else` branch that renders a plain text input, and the server-side validation accepts a free-text `doctrine` field. v2.0.0 just crashed before render so operators could not reach the input.

### 📦 Upgrade

No migrations, no schema changes. Restart the front container to pick up the new controller code:

```
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
```

### ⚠️ Breaking Changes

None. v2.0.0 installs upgrade cleanly with all settings, webhooks, scheduled pings, broadcast history, templates and tactical events preserved.

---

## 🆕 Version 2.0.0 — *May 2026*

This release turns SeAT Broadcast into the **Planning HUB for fleet commanders**. With **Manager Core**, **Structure Manager** and **Mining Manager** installed, every structure timer, manual fleet op and moon extraction flows automatically onto a dedicated **FC Opportunities** board, and one click on any opportunity opens a pre-filled formup broadcast ready to schedule. The Broadcasts Calendar stays strictly broadcast-focused (scheduled + manually sent pings); the planner and the calendar are deliberately separate surfaces. Also folds in the 2026 reliability/security hardening, pre-timer reminder pings, EVE→local time auto-conversion, and an internal PHP namespace alignment with the rest of the plugin family.

> **Mental model:** Structure Manager owns the timers, Mining Manager owns the extractions, Manager Core is the wire, and SeAT Broadcast is the Planning HUB. FCs plan from the **FC Opportunities** board (every upcoming event in one place); broadcasts they create land on the **Calendar** for the rest of the corp to see. Two surfaces, two clear jobs — the calendar never gets cluttered with timers. SeAT Broadcast never reaches into another plugin's database; remove Manager Core and it keeps working exactly as a standalone webhook sender.

### 🎉 New Features

**🎯 FC Opportunities Board**
- New sidebar entry **FC Opportunities** — a focused view of upcoming structure timers, mining extractions and fleet ops needing FC action, with live countdowns
- **Three-button action column** per row: **Form-up** (primary, opens the *Send Broadcast* form pre-filled with urgent "forming up NOW" copy + PREPING embed — use when staging right now) · **Schedule** (opens the *Scheduled Broadcast* form pre-filled, defaults to timer minus your formup offset — use for advance planning) · **Details** (deep-links to the source plugin's detail page — MM extraction view or SM structure board)
- **Source plugin badge** on every row (`SM` indigo / `MM` teal / source-name fallback) so operators can scan the board and instantly tell which integration each opportunity comes from
- Sortable / filterable table: time, op type, source plugin badge, structure, system, severity, parties, broadcasts already scheduled, action buttons
- **📡 N scheduled** badge per op tells you when a formup broadcast has already been planned for this op (no duplicate FC effort). The count is **global** — every tier (FC and Fleet Coordinator) sees the same coordination signal
- Default 30-minute form-up lead time, configurable in Settings > Structure Timers (5 minutes to 12 hours)
- Per-event corporation/role visibility from Structure Manager is fully honored
- The sidebar entry is **conditional**: it only appears when both Manager Core and Structure Manager are installed (since the board is otherwise empty by design). The route still resolves either way for deep links, showing an empty-state with install instructions

**🛰️ Structure Timers on FC Opportunities**
- SeAT Broadcast subscribes to Structure Manager's `structure_manager.timer.*` events via Manager Core's EventBus
- Auto-detected reinforcement timers, anchoring timers, fuel-expiry timers, and manually entered fleet ops all flow onto the **FC Opportunities** board as they are created, updated, or dismissed (no manual entry, no syncing)
- Timers carry live countdowns, severity colour-coding, and op-type icons (⚔️ hostile, 🛡️ defense, generic for auto-detected timers); one-click **Form-up** button per row opens the Scheduled Broadcast form pre-filled for the op, and the **SM** deep-link button takes the FC straight to Structure Manager
- Per-timer corporation and role visibility from Structure Manager is fully honored on the board
- Elapsed timers drop off the active board automatically
- The Broadcasts Calendar intentionally stays broadcast-focused (scheduled + manual history only); planning happens on FC Opportunities and the resulting form-up pings naturally land back on the calendar

**⏰ Pre-Timer Reminder Pings**
- Any webhook can be flagged to "Receive structure timer alerts" on its edit page
- Flagged webhooks get an automatic rich-embed reminder at **T-24h** and **T-1h** before each timer
- Every alert is recorded in Broadcast History for auditability
- Replayed events cannot double-ping (atomic per-stage dedup)
- A master on/off toggle on the **Settings > Structure Timers** tab disables all pre-timer pings without un-flagging webhooks; the tab also shows Manager Core / Structure Manager detection status
- New per-webhook **Corporation Scope** field on the webhook edit page so multi-corp SeAT installs route alerts to the right channels: a scoped webhook only receives events for its corp; a webhook left as "Any corporation" still receives everything (backwards-compatible default). Existing webhooks keep their old behaviour until you opt in to per-corp routing.

**⛏️ Mining Extractions on FC Opportunities (Mining Manager v2.0.1+)**
- SeAT Broadcast subscribes to Mining Manager's `mining.extraction_*` event family via Manager Core's EventBus. Three lifecycle stages map onto a single board row per moon: `extraction_ready` opens the 48h fleet-able window, `extraction_unstable` recolours the row and fires a single pre-expiry alert, `extraction_expired` drops the row from the active board
- Moon extractions surface on the **FC Opportunities** board as a distinct ⛏️ mining category, alongside structure timers and manual fleet ops
- Multi-FC friendly: mining windows are 2-day events that welcome multiple formup broadcasts per moon (different timezones, fleet sizes, drops across the 48h window). The "📡 N scheduled" badge is informational only — never discourages further formups
- New per-webhook **"Receive mining extraction alerts"** flag on the webhook edit page (additive migration `000019`, backwards-compatible default false). Flagged webhooks get a single T-2h pre-expiry Discord embed with refinery, moon, jackpot status, and estimated value
- Master on/off toggle on **Settings > Structure Timers** lives alongside the structure-timer toggle: one place to manage every EventBus-driven alert. Mining Manager detection status row added to the tab
- Per-extraction corporation visibility from Mining Manager is fully honored on the board and on alert dispatch (corp-scoped webhooks only see their own corp's extractions)
- **Deep link to Mining Manager**: FC Opportunities mining rows get a small **MM** button in the action column that opens the per-extraction detail page (ore composition, countdowns, jackpot status). Driven by the `url` field in MM v2.0.1+ event payloads
- Standalone-safe: without Manager Core OR Mining Manager v2.0.1+, the subscription path is a silent no-op and the plugin behaves exactly as before
- Like structure timers, mining extractions are NOT shown on the Broadcasts Calendar — they live on the FC Opportunities board where FCs plan; formup pings created from there land on the calendar as normal scheduled broadcasts

**📡 Published Events (Manager Core EventBus)**
- SeAT Broadcast now **publishes** its own events to Manager Core's EventBus in addition to subscribing. Two events ship in v2.0.0:
  - **`pings.broadcast.sent`** — fired after every successful Discord broadcast (manual sends, scheduled fires, and webhook test sends). Payload includes `user_id`, `user_name`, `webhook_id/name`, `corporation_id` (if scoped), `broadcast_type`, `mention_type`, `message_summary` (truncated to 200 chars), `history_id`, plus `is_scheduled` / `is_structure_alert` flags so subscribers can tell apart manual broadcasts from system-driven ones
  - **`pings.formup.scheduled`** — fired when an FC creates a scheduled broadcast correlated with a tactical event (the form-up workflow). Payload includes the user info, the scheduled time, the tactical event snapshot (event_type, severity, structure, system, corporation, owner/attacker), and the webhook target
- These give other plugins (notably **HR Manager**) a clean signal of who is acting like an active fleet commander — without needing to peek at Pings' tables
- All events carry `source_plugin = seat-discord-pings`, `schema_version = 1`, a per-publish `event_id` (`pings-evt-{uuid}`), and a UTC `timestamp`
- Publishing is `class_exists`-guarded and exception-swallowing: a publish failure can never break a broadcast, and Manager Core remains entirely optional
- New `DiscordPings\Services\BroadcastEventPublisher` is the single dispatch point; mirrors the pattern used by Structure Manager's `TimerEventPublisher`

**🎯 Discord Roles Quick-Pick (connector-aware)**
- The Add Discord Role modal on **Settings > Discord Roles** now offers a "Quick-pick" dropdown when a SeAT Discord connector is installed alongside SeAT Broadcast. The picker reads from the shared `seat_connector_sets` table populated by **either** `warlof/seat-connector` + `warlof/seat-discord-connector` **or** zenobio93's maintained fork of the same packages — the resolver doesn't care which vendor is installed because the framework schema is identical. Pick a role from the list, the snowflake and the display name pre-fill into the form, click Save, done
- Also surfaces roles from the older standalone Warlof Discord connector (pre-framework era, `warlof_discord_connector_roles` table) when that's the only thing present
- Eliminates the Developer-Mode round-trip on initial setup (open Discord → enable Developer Mode → right-click role → Copy ID → switch tabs → paste). Manual snowflake entry remains the always-available fallback below the picker for installs without any connector
- Already-added roles are filtered out of the dropdown so operators never accidentally create duplicate `discord_roles` records
- Picker uses **table-presence detection** (not `class_exists`, mirroring the proven SM / MM pattern) — a connector plugin that ships via tarball or manual install still gets picked up
- The dropdown shows the source per role (`SeAT Connector` / `Warlof Discord (legacy)`) so operators see provenance at a glance
- New `DiscordPings\Services\DiscordRoleResolver` — sibling to the resolvers shipped in Structure Manager / Mining Manager / Corp Wallet Manager, tuned for SeAT Broadcast's provider role: the picker deliberately does NOT query the local `discord_roles` table (this plugin OWNS that table, so suggesting from it would be circular) — only connector-sourced roles are surfaced
- Standalone-safe: no connector plugin installed = the dropdown is hidden, the form falls back to manual input only, no errors

**🩺 Diagnostic Page**
- New admin-only `/discord-pings/diagnostic` page (NOT in sidebar; reach via direct URL) with six tabs per the family-wide diagnostic standard
- **Health Checks**: at-a-glance stat grid (webhook counts, 24h history, scheduled-pings due, tactical-event counts) plus integration-detection pills
- **Master Test**: runs pass/warn/fail checks for required tables, settings validity, cron processing, Manager Core subscription + plugin-registry self-registration, webhook URL encryption
- **System Validation**: PHP version, required SeAT classes, optional integration classes (MC EventBus / PluginBridge / SM TimerEventPublisher / fitting plugin), Guzzle
- **Settings Health**: every config-file and DB setting listed with current value, default, source, and a note — catches drift between config defaults and UI overrides
- **Data Integrity**: per-table row counts plus orphan / consistency checks (orphaned history, stale resolved timers past retention, scheduled pings stuck overdue past 15 minutes)
- **Broadcast Trace**: the plugin-specific Tier 3 debugging tab. Pick a scheduled-ping or tactical-event row ID and the page walks the dispatch pipeline showing each decision, recent PingHistory for the same (webhook, user), which webhooks WOULD receive a ping for a tactical event right now, and linked formup broadcasts
- New **Plugin Admin** permission (`discordpings.admin`) gates the diagnostic page

### 🔐 Permissions: two-tier model

- **`discordpings.send`** is now the FC tier. Holders can broadcast, schedule pings, see the Calendar, see FC Opportunities, and **manage their OWN scheduled pings** — but cannot touch other users' pings. Previously these surfaces were gated by `manage_scheduled` (so regular FCs were locked out of scheduling and the planner entirely)
- **`discordpings.manage_scheduled`** is now the "Fleet Coordinator" tier. Holders gain visibility and control over **OTHER users'** scheduled pings (the list/calendar views become full-org views) plus the bulk-clear operations on the Scheduled Broadcasts page. Existing `manage_scheduled` users see no behaviour change
- **Permission labels updated**: "Manage Scheduled Pings" → "Manage All Scheduled Pings" to make the per-user vs all-users distinction explicit. Existing role-permission assignments are unaffected (the permission key is unchanged; only the user-facing label changed)
- Backwards compatible: every user who previously had access keeps it. The change only adds new capability to existing `discordpings.send` holders
- Controller-level ownership checks (already present in edit/update/destroy) become live for tier-1 users: an FC trying to edit another user's ping gets a 403, exactly as designed
- The FC Opportunities "📡 N scheduled" badge stays **global** across both tiers — coordination signal works for everyone, content stays appropriately gated (tier-1 sees the count, doesn't see the underlying pings)
- **New Permissions section in Help & Documentation** (sidebar nav added). Includes a side-by-side capability table for the two tiers, five real-world "role recipes" (Solo FC / Regular FC / Fleet Coordinator / Auditor / Plugin Admin) showing the exact permission combinations to assign, and a full-reference list of all 10 permissions the plugin offers. Plus a clarifying note that the History pair (`view_history` / `view_all_history`) governs the calendar's manual-broadcast-history dots SEPARATELY from the scheduled-ping tier — operators can mix and match (e.g. own scheduled + everyone's history)

### 🆙 Version Status

- New **Version Status card** on the Help & Documentation Overview page shows the installed version, the latest release on Packagist, and an update-available badge if your install is behind. Includes a "View release notes" deep link and the docker compose upgrade recipe when an update is available
- Backed by a new `DiscordPings\Services\VersionChecker` that hits Packagist's public v2 API (`https://repo.packagist.org/p2/mattfalahe/seat-discord-pings.json`) on a 6-hour cache, with a 3-second timeout and full try/catch around the network call — a Packagist outage can never slow or break the Help page
- Four status states: ✓ Up to date (installed == latest), ⚠ Update available (installed < latest), 🚀 Pre-release / dev (installed > latest, useful when running from a dev branch), or — Unable to check (Packagist unreachable)
- Pure informational — there's no self-updater for a Composer plugin, so the card explains the standard upgrade path rather than offering a button. Visible to anyone with the `discordpings.view` permission

### 🚀 Reliability & Security

- Webhook URLs are now **encrypted at rest** (encrypted cast plus a backfill migration)
- Restored **TLS certificate verification** on all Discord requests
- Discord sends are **queued** and respect configured **per-webhook rate limits**
- Removed four ghost database tables left over from an earlier naming pass (row-count guarded)
- Consolidated the duplicated fitting-plugin probe and pruned dead code paths
- `SendScheduledPing` now differentiates rate-limited from hard-failed sends: rate-limited pings retry on the next cron tick (up to a 15-minute safety window before giving up); hard failures log a warning and advance the schedule (failure already recorded in History, operator can resend manually). Previously every non-success was silently marked as sent and the occurrence was lost

### 🎨 Interface

- **🕒 Auto-converted local time** alongside every EVE timestamp. Every EVE time display in the plugin (Calendar event modal, FC Opportunities board, Scheduled Broadcasts list, Broadcast History list + detail, scheduled-ping form prefill banner) now carries a `title=` tooltip with the full local date/time formatted via the browser's locale + timezone. High-priority surfaces also show a small "· HH:MM local" inline pill next to the EVE time. Works on every evergreen browser; no per-user config; server-side always emits UTC (single source of truth). New shared `eve-time.js` plus a `.eve-time[data-eve-time="..."]` convention that any future view can opt into with one line of Blade.
- **📅 Schedule form: Enter In toggle (EVE/UTC ↔ Local)**. The Scheduled Broadcast create and edit forms now have an "Enter time in: [EVE/UTC · My local]" toggle. EVE/UTC stays the default (existing FC muscle memory preserved) and the input means EVE time exactly as before. Switching to "My local" means the input is read as your browser-local time and converted to UTC on submit; the storage is always UTC. The confirmation box always shows BOTH the EVE and the local equivalent live as you type so you can see the conversion before clicking Schedule. Replaces the previous "Your Timezone" dropdown which had a DST bug (used fixed UTC offsets like "EST = UTC-5" that gave wrong local-time previews half the year when New York is actually EDT/UTC-4). The new path uses `Intl.DateTimeFormat()` against the browser-detected IANA timezone so DST transitions are handled correctly all year. The form also surfaces the detected timezone inline (`Browser timezone: Europe/Warsaw`) so operators can spot misconfigured machines at a glance.
- Ported the canonical design-system CSS; all views now use the `.discord-pings-wrapper` shell for consistent theming
- Help & Documentation overview gets a "What's New in v2.0.0" highlights box; new sections (Structure Timers, FC Opportunities) carry green "NEW in v2.0.0" badges in the sidebar and section headers, plus an indigo "Requires Manager Core" marker where appropriate. CSS cache buster bumped to `?v=2`.
- New **Routing Map** tab on the Settings page (7th tab): read-only snapshot of which flagged webhooks would receive a pre-timer ping right now, showing per-webhook corporation scope, active/disabled state, master-switch status, and integration-detection state. Plus an informational Calendar Ingest section. Catches "I configured this but nothing fires" misconfigurations at a glance.

### ⚠️ Notes & Limitations

- The Manager Core integration is **entirely optional**. Without Manager Core, SeAT Broadcast behaves exactly as before.
- Pre-timer pings ARE corporation-routed: see the Corporation Scope field on the webhook edit page. Default is "Any corporation" so an existing or freshly-created webhook receives everything until the operator narrows it down.
- Resolved (dismissed / elapsed) timers are pruned after 14 days, configurable via `discordpings.structure_events.retention_days`.

### 🔧 Internal Refactor

- The plugin's PHP namespace has been renamed from `MattFalahe\Seat\DiscordPings` to `DiscordPings` to match the convention used across the rest of the plugin family. **No public API change**: view aliases (`discordpings::`), route names, table names, the Packagist package name, and PluginBridge capability keys are all unchanged. If your install runs `php artisan route:cache`, run `php artisan route:clear` once after upgrading. Any queued jobs serialised before the upgrade will fail once when they dequeue under the new class names; drain the queue beforehand for zero failure noise.
- `AbstractSeatPlugin::getName()` now returns `'SeAT Broadcast'` (was `'Discord Pings'`) so the SeAT plugin admin list matches every other user-facing surface. Completes the rebrand started earlier.
- Major version bump (1.0.6 → 2.0.0) reflects the combined scope: a new EventBus integration, a new FC Opportunities surface, and the namespace rename. There is no separate 1.x release on the way.

### 📦 Upgrade

Eight new database migrations are included since v1.0.6 (`000012` encrypts existing webhook URLs, `000013` drops four ghost tables from an earlier naming pass, `000014` creates the tactical-events ingest table, `000015` adds the structure-alerts webhook flag, `000016` creates the UI settings key-value store, `000017` adds the scheduled-ping → tactical-event correlation column, `000018` adds the per-webhook corporation scope, `000019` adds the mining-alerts webhook flag). All are additive (`hasColumn` / `hasTable` guarded) and re-run safe. With the SeAT Docker stack, migrations run automatically on restart:

```
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
```

No configuration changes are required. To enable **pre-timer structure pings**, edit a webhook and tick "Receive structure timer alerts". To enable **pre-expiry mining alerts** (requires Mining Manager v2.0.1+), tick "Receive mining extraction alerts" on the same edit page. Master on/off toggles for both live on **Settings > Structure Timers**, along with the default form-up lead time.

---

## Version 1.0.6 — *March 2026*

### 🎉 New Features

**‼️ PREPING ‼️ Broadcast Type**
- Added a fourth broadcast type: ‼️ PREPING ‼️ — for staging fleets before ops begin
- Available in both Send Broadcast and Schedule forms
- Consistent naming and icon across all views, calendar, and history

**Configurable PAP Types**
- PAP types are no longer hardcoded — managed via Settings > PAP Types tab
- Add custom PAP types, set sort order, activate/deactivate entries
- Three defaults seeded automatically on install/update: Strategic, Peacetime, CTA
- Active types populate dropdowns on Send and Schedule forms

**Visual Color Picker**
- Embed color field now includes a colour swatch with a browser-native color picker
- Type a hex code or pick visually — both stay in sync
- Webhook auto-fill still works when switching webhooks

**Broadcasts Calendar — Manual History**
- Calendar now shows manually sent broadcasts from the Send Broadcast page alongside scheduled pings
- History entries render as a coloured dot + broadcast type icon + time + message (no full bar)
- `View Ping History` → own sent pings on calendar; `View All History` → all users' sent pings
- Sent scheduled pings remain visible on the calendar dimmed with strikethrough until deleted

**Edit Scheduled Broadcasts**
- Scheduled broadcasts can now be edited after creation
- Edit button (pencil) on each active ping in the list view
- Edit button in the calendar event popup for active scheduled pings
- All fields editable: time, webhook, message, repeat settings, mentions, fleet details
- Clicking Edit on a specific future calendar occurrence pre-fills that occurrence's time
- Recurring series: changes apply to all future occurrences from the new scheduled time; past sends are preserved in history
- Clear warning shown when editing a recurring ping that has already fired

**Bulk Clear Inactive Scheduled Pings**
- Two bulk-delete buttons on the Scheduled Broadcasts page (Manage Scheduled Pings permission required):
  - **Clear Inactive > 7 Days** — removes sent pings older than 7 days
  - **Clear Inactive > 30 Days** — removes sent pings older than 30 days
- Active upcoming pings are never affected

### ✨ Improvements

**Shared Scheduled Ping Visibility**
- Users with the Manage Scheduled Pings permission now see all users' scheduled broadcasts in both the list and calendar views
- "Created By" column added to the list view
- These users can edit or delete any scheduled broadcast to prevent overlaps

**Settings Page (renamed from Discord Configuration)**
- Discord Configuration renamed to Settings throughout
- Now contains five tabs: Webhooks, Discord Roles, Discord Channels, Staging Locations, PAP Types

**Broadcasts Calendar (renamed)**
- Calendar page renamed from "Scheduled Broadcasts Calendar" to "Broadcasts Calendar"
- Reflects that it now shows both scheduled and manually sent pings

**Calendar Event Rendering**
- Scheduled pings show a 📅 calendar icon + broadcast type icon + time + message
- Inactive (sent) scheduled pings shown dimmed with strikethrough
- All event types use explicit `eventContent` rendering for consistent display across all FullCalendar views

### 🐛 Bug Fixes

- Fixed inactive recurring pings generating ghost future occurrences on the calendar
- Fixed history events appearing as full colored bars (now rendered as dot + icon)
- Fixed empty message scheduled pings showing as invisible dots (now shows `(no message)`)

---

## Version 1.0.5 — *February 2026*

### 🎉 New Features

**Calendar View**
- Visual calendar for scheduled broadcasts
- Month, week, and list view options
- Click on events to view details
- Click on empty dates to create new scheduled pings
- Color-coded events by webhook
- Large calendar display (1600px height) for better visibility
- Event stacking in week view (up to 5 events side by side)

**Template Management**
- Create and manage personal broadcast templates
- Global templates for organization-wide use (admin permission required)
- Quick save templates directly from Send Broadcast page
- Doctrine dropdown integration (seat-fitting plugin)
- Staging location dropdown for quick selection
- Full field support: FC name, formup, doctrine, PAP type, comms, mentions, embed color
- Template fields automatically populate Send Broadcast form

**Help & Documentation Page**
- Comprehensive documentation with searchable interface
- Plugin info section with GitHub repository links
- Getting started guide with installation steps
- Feature descriptions and usage instructions
- Commands reference with examples
- FAQ and troubleshooting sections
- Pages guide explaining each plugin page

### 🐛 Bug Fixes

- Added DataTables sorting across all plugin tables

---

## Version 1.0.4 — *September 2025*

### 🎉 New Features

**Broadcast Type Selector**
- Added broadcast type selector with three options:
  - 📢 Fleet Broadcast (for fleet operations)
  - 📣 Announcement (for general announcements)
  - 💬 Message (for simple messages)
- Discord embeds now show appropriate title based on selected type
- Plugin is now more versatile for different types of communications

---

## Version 1.0.3 — *September 2025*

### ✨ Improvements

**Time Handling Rework**
- EVE time is now the primary input for scheduling
- Added timezone selector to confirm local time
- Improved time display throughout the interface

---

## Version 1.0.2 — *September 2025*

### 🎉 New Features

- Added staging locations management with quick dropdown selection
- Added seat-fitting integration for both CryptaTech and Denngarr versions
- Enhanced scheduled pings with all features from send ping (roles, channels, doctrines, stagings)
- Added EVE time display with local time reference
- Added working preview modal for Discord embeds
- Updated Discord Configuration interface with tabbed layout
- Added support for default staging locations

### 🐛 Bug Fixes

- Fixed embed color not being applied correctly
- Fixed doctrine links not showing in Discord embeds
- Combined Comms and Channel fields into single embed field
- Fixed webhook testing functionality
- Improved error handling and logging

---

## Version 1.0.1 — *September 2025*

### 🎉 New Features

- Added Discord role management and mentions
- Added Discord channel linking functionality
- Implemented unified Discord Configuration interface
- Added scheduled ping recurring options
- Added automatic history cleanup job
- Improved permission setup command with --grant-admin option
- Added role-based webhook restrictions
- Enhanced UI with role and channel dropdowns

### 🐛 Bug Fixes

- Fixed database migration issues
- Improved error handling and logging

---

## 🚀 Version 1.0.0 — *September 2025*

### Initial Release

- Core ping functionality with rich Discord embeds
- Webhook management with testing capability
- Scheduled pings with recurring options
- History tracking with resend capability
- Role-based access control
- Template system for quick pings
- Multiple webhook support for bulk sending

---

## 📦 Upcoming Features (Planned)

- Calendar event drag-and-drop rescheduling
- Skip single occurrence for recurring pings
- Ping analytics and statistics dashboard
- Alliance-wide broadcast support
- Mobile-responsive improvements
- Webhook health monitoring

---

For the latest updates, issues, or feature requests:
👉 [github.com/MattFalahe/SeAT-Discord-Pings](https://github.com/MattFalahe/SeAT-Discord-Pings)
