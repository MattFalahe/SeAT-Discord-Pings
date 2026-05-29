@extends('web::layouts.grids.12')

@section('title', 'SeAT Broadcast — Diagnostic')
@section('page_header', 'SeAT Broadcast — Diagnostic')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=2">
<style>
    /* Diagnostic chrome — scoped to .discord-pings-wrapper.diagnostic-page so
       it cannot leak into other plugin views. Mirrors the canonical primitives
       from feedback_plugin_diagnostic_standard.md (Structure Manager reference).
    */

    .discord-pings-wrapper.diagnostic-page .diag-tabs {
        display: flex; gap: 0; border-bottom: 2px solid #454d55;
        margin: 1.5rem 0 1.5rem 0; padding: 0; list-style: none; flex-wrap: wrap;
    }
    .discord-pings-wrapper.diagnostic-page .diag-tab {
        padding: 0.6rem 1.2rem; color: #8b95a5; cursor: pointer;
        border-bottom: 3px solid transparent; font-weight: 500;
        font-size: 0.9rem; transition: all 0.15s; user-select: none;
        display: flex; align-items: center; gap: 0.5rem;
    }
    .discord-pings-wrapper.diagnostic-page .diag-tab:hover {
        color: #c2c7d0; border-bottom-color: #3a4049;
    }
    .discord-pings-wrapper.diagnostic-page .diag-tab.active {
        color: #17a2b8; border-bottom-color: #17a2b8;
    }
    .discord-pings-wrapper.diagnostic-page .diag-tab-pane { display: none; }
    .discord-pings-wrapper.diagnostic-page .diag-tab-pane.active { display: block; }

    /* Mandatory per-tab intro box (per the standard). */
    .discord-pings-wrapper.diagnostic-page .diag-tab-intro {
        padding: 0.85rem 1.1rem; background: rgba(99, 102, 241, 0.08);
        border-left: 3px solid #6366f1; border-radius: 5px;
        margin-bottom: 1.25rem; color: #c2c7d0; font-size: 0.92rem; line-height: 1.5;
    }
    .discord-pings-wrapper.diagnostic-page .diag-tab-intro strong { color: #c7d2fe; }
    .discord-pings-wrapper.diagnostic-page .diag-tab-intro p { margin-bottom: 0.4rem; }
    .discord-pings-wrapper.diagnostic-page .diag-tab-intro p:last-child { margin-bottom: 0; }
    .discord-pings-wrapper.diagnostic-page .diag-tab-intro code {
        color: #a5b4fc; background: rgba(0, 0, 0, 0.25);
        padding: 0 0.25rem; border-radius: 3px;
    }

    .discord-pings-wrapper.diagnostic-page .diag-section {
        background: #2a2f3a; border: 1px solid #454d55;
        border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden;
    }
    .discord-pings-wrapper.diagnostic-page .diag-section-header {
        padding: 0.8rem 1.2rem; background: #343a45;
        border-bottom: 1px solid #454d55;
        display: flex; align-items: center; justify-content: space-between;
    }
    .discord-pings-wrapper.diagnostic-page .diag-section-title {
        margin: 0; font-size: 1.05rem; font-weight: 600; color: #fff;
    }
    .discord-pings-wrapper.diagnostic-page .diag-section-body {
        padding: 1.2rem; color: #c2c7d0;
    }

    /* SEMANTIC status badges — do not recolour. */
    .discord-pings-wrapper.diagnostic-page .diag-badge {
        font-size: 0.78rem; font-weight: 700; padding: 0.25rem 0.55rem;
        border-radius: 0.25rem; letter-spacing: 0.04em; text-transform: uppercase;
    }
    .discord-pings-wrapper.diagnostic-page .diag-badge.ok    { background: #1c6f3e; color: #d4f4e2; }
    .discord-pings-wrapper.diagnostic-page .diag-badge.warn  { background: #7a5a0f; color: #fff1c7; }
    .discord-pings-wrapper.diagnostic-page .diag-badge.error { background: #7a1d2b; color: #fbd5db; }
    .discord-pings-wrapper.diagnostic-page .diag-badge.info  { background: #1d4d7a; color: #d0e4fb; }

    .discord-pings-wrapper.diagnostic-page .diag-detail-table {
        width: 100%; margin-top: 0.8rem; font-size: 0.85rem; border-collapse: collapse;
    }
    .discord-pings-wrapper.diagnostic-page .diag-detail-table th,
    .discord-pings-wrapper.diagnostic-page .diag-detail-table td {
        padding: 0.45rem 0.7rem; border-bottom: 1px solid #3a3f4a;
        color: #c2c7d0; text-align: left; vertical-align: top;
    }
    .discord-pings-wrapper.diagnostic-page .diag-detail-table th {
        color: #8b95a5; font-weight: 600;
        text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;
    }
    .discord-pings-wrapper.diagnostic-page .diag-detail-table tr.row-warn td  { background: rgba(122, 90, 15, 0.08); }
    .discord-pings-wrapper.diagnostic-page .diag-detail-table tr.row-error td { background: rgba(122, 29, 43, 0.10); }

    .discord-pings-wrapper.diagnostic-page .diag-kv {
        display: grid; grid-template-columns: max-content 1fr;
        gap: 0.4rem 1rem; font-size: 0.9rem;
    }
    .discord-pings-wrapper.diagnostic-page .diag-kv dt { color: #8b95a5; font-weight: 500; }
    .discord-pings-wrapper.diagnostic-page .diag-kv dd {
        margin: 0; color: #e2e8f0;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .discord-pings-wrapper.diagnostic-page .diag-summary {
        padding: 1rem 1.25rem; border-radius: 8px;
        margin-bottom: 1.5rem; border: 1px solid #454d55;
        background: #2a2f3a; color: #e2e8f0;
    }
    .discord-pings-wrapper.diagnostic-page .diag-summary.ok    { border-left: 4px solid #28a745; }
    .discord-pings-wrapper.diagnostic-page .diag-summary.warn  { border-left: 4px solid #ffc107; }
    .discord-pings-wrapper.diagnostic-page .diag-summary.error { border-left: 4px solid #dc3545; }

    .discord-pings-wrapper.diagnostic-page .diag-stat-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
    }
    .discord-pings-wrapper.diagnostic-page .diag-stat {
        background: #2a2f3a; border: 1px solid #454d55;
        border-radius: 6px; padding: 0.8rem 1rem;
    }
    .discord-pings-wrapper.diagnostic-page .diag-stat-label {
        color: #8b95a5; font-size: 0.78rem; text-transform: uppercase;
        letter-spacing: 0.04em; margin-bottom: 0.2rem;
    }
    .discord-pings-wrapper.diagnostic-page .diag-stat-value {
        color: #fff; font-size: 1.4rem; font-weight: 600;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .discord-pings-wrapper.diagnostic-page .diag-stat-sub {
        color: #94a3b8; font-size: 0.78rem; margin-top: 0.15rem;
    }
</style>
@endpush

@section('full')
<div class="discord-pings-wrapper diagnostic-page">
    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-stethoscope"></i> SeAT Broadcast Diagnostic
                <small class="text-muted" style="font-weight: 400; margin-left: 0.5rem;">
                    admin-only · not in sidebar
                </small>
            </h3>
            <div class="card-tools">
                <a href="{{ route('discordpings.config') }}" class="btn btn-sm btn-pings-secondary">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </div>
        <div class="card-body">

            {{-- Tab navigation --}}
            <ul class="diag-tabs">
                <li class="diag-tab {{ $activeTab === 'health' ? 'active' : '' }}" data-tab="health">
                    <i class="fas fa-heart"></i> Health Checks
                </li>
                <li class="diag-tab {{ $activeTab === 'master' ? 'active' : '' }}" data-tab="master">
                    <i class="fas fa-clipboard-check"></i> Master Test
                </li>
                <li class="diag-tab {{ $activeTab === 'system' ? 'active' : '' }}" data-tab="system">
                    <i class="fas fa-cube"></i> System Validation
                </li>
                <li class="diag-tab {{ $activeTab === 'settings' ? 'active' : '' }}" data-tab="settings">
                    <i class="fas fa-sliders-h"></i> Settings Health
                </li>
                <li class="diag-tab {{ $activeTab === 'data' ? 'active' : '' }}" data-tab="data">
                    <i class="fas fa-database"></i> Data Integrity
                </li>
                <li class="diag-tab {{ $activeTab === 'trace' ? 'active' : '' }}" data-tab="trace">
                    <i class="fas fa-search"></i> Broadcast Trace
                </li>
            </ul>

            {{-- Health Checks --}}
            <div class="diag-tab-pane {{ $activeTab === 'health' ? 'active' : '' }}" data-pane="health">
                <div class="diag-tab-intro">
                    <p><strong>What this tab does:</strong> At-a-glance counts and integration-detect flags for the plugin's main objects (webhooks, history, scheduled, tactical events). Read-only; no checks run here.</p>
                    <p><strong>When to use:</strong> Daily eyeball check, or as the first stop when "is this plugin alive?" comes up.</p>
                </div>

                <div class="diag-stat-grid">
                    <div class="diag-stat"><div class="diag-stat-label">Webhooks</div><div class="diag-stat-value">{{ $healthChecks['webhooks_total'] }}</div><div class="diag-stat-sub">{{ $healthChecks['webhooks_active'] }} active · {{ $healthChecks['webhooks_with_alerts'] }} structure-alert · {{ $healthChecks['webhooks_with_mining'] }} mining-alert · {{ $healthChecks['webhooks_corp_scoped'] }} corp-scoped</div></div>
                    <div class="diag-stat"><div class="diag-stat-label">History (24h)</div><div class="diag-stat-value">{{ $healthChecks['history_24h'] }}</div><div class="diag-stat-sub">{{ $healthChecks['history_failed_24h'] }} failed · {{ $healthChecks['history_rate_limited_24h'] }} rate-limited</div></div>
                    <div class="diag-stat"><div class="diag-stat-label">Scheduled Pings</div><div class="diag-stat-value">{{ $healthChecks['scheduled_active'] }}</div><div class="diag-stat-sub">{{ $healthChecks['scheduled_due_now'] }} due right now</div></div>
                    <div class="diag-stat"><div class="diag-stat-label">Tactical Events</div><div class="diag-stat-value">{{ $healthChecks['tactical_events_active'] }}</div><div class="diag-stat-sub">{{ $healthChecks['tactical_events_total'] }} total · {{ $healthChecks['tactical_events_mining'] }} mining · {{ $healthChecks['tactical_events_24h'] }} touched 24h</div></div>
                    <div class="diag-stat"><div class="diag-stat-label">Discord Roles</div><div class="diag-stat-value">{{ $healthChecks['roles_total'] }}</div></div>
                    <div class="diag-stat"><div class="diag-stat-label">Discord Channels</div><div class="diag-stat-value">{{ $healthChecks['channels_total'] }}</div></div>
                    <div class="diag-stat"><div class="diag-stat-label">Staging Locations</div><div class="diag-stat-value">{{ $healthChecks['stagings_total'] }}</div></div>
                    <div class="diag-stat"><div class="diag-stat-label">PAP Types</div><div class="diag-stat-value">{{ $healthChecks['pap_types_total'] }}</div></div>
                </div>

                <div class="diag-section" style="margin-top: 1.25rem;">
                    <div class="diag-section-header">
                        <h4 class="diag-section-title">Integration Status</h4>
                    </div>
                    <div class="diag-section-body">
                        <dl class="diag-kv">
                            <dt>Manager Core</dt>
                            <dd><span class="diag-badge {{ $healthChecks['mc_installed'] ? 'ok' : 'info' }}">{{ $healthChecks['mc_installed'] ? 'detected' : 'not installed' }}</span></dd>
                            <dt>Structure Manager</dt>
                            <dd><span class="diag-badge {{ $healthChecks['sm_installed'] ? 'ok' : 'info' }}">{{ $healthChecks['sm_installed'] ? 'detected' : 'not installed' }}</span></dd>
                            <dt>Mining Manager (v2.0.1+)</dt>
                            <dd><span class="diag-badge {{ $healthChecks['mm_installed'] ? 'ok' : 'info' }}">{{ $healthChecks['mm_installed'] ? 'detected' : 'not installed' }}</span></dd>
                            <dt>Fitting plugin</dt>
                            <dd>{{ $healthChecks['fitting_plugin'] ?: 'none detected' }}</dd>
                            <dt>Pre-timer pings master toggle (structure)</dt>
                            <dd><span class="diag-badge {{ $healthChecks['master_toggle'] ? 'ok' : 'warn' }}">{{ $healthChecks['master_toggle'] ? 'enabled' : 'disabled' }}</span></dd>
                            <dt>Pre-expiry alerts master toggle (mining)</dt>
                            <dd><span class="diag-badge {{ $healthChecks['mining_master_toggle'] ? 'ok' : 'warn' }}">{{ $healthChecks['mining_master_toggle'] ? 'enabled' : 'disabled' }}</span></dd>
                            <dt>Plugin version (config)</dt>
                            <dd>{{ $healthChecks['plugin_version'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Master Test --}}
            <div class="diag-tab-pane {{ $activeTab === 'master' ? 'active' : '' }}" data-pane="master">
                <div class="diag-tab-intro">
                    <p><strong>What this tab does:</strong> Runs a battery of validation checks: required tables exist, settings within valid ranges, cron is processing scheduled pings, Manager Core integration is wired correctly, webhook URLs are encrypted at rest.</p>
                    <p><strong>When to use:</strong> When something feels off and you need a deep diagnostic. Each check returns <code>ok</code> / <code>warn</code> / <code>error</code> with a one-line detail.</p>
                </div>

                <div class="diag-summary {{ $masterTest['failures'] > 0 ? 'error' : ($masterTest['warnings'] > 0 ? 'warn' : 'ok') }}">
                    <strong>Results:</strong>
                    {{ $masterTest['passed'] }} passed
                    @if($masterTest['warnings'] > 0), <strong style="color: #ffc107;">{{ $masterTest['warnings'] }} warning(s)</strong>@endif
                    @if($masterTest['failures'] > 0), <strong style="color: #dc3545;">{{ $masterTest['failures'] }} failure(s)</strong>@endif
                </div>

                <div class="diag-section">
                    <div class="diag-section-body">
                        <table class="diag-detail-table">
                            <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
                            <tbody>
                            @foreach($masterTest['checks'] as $c)
                                <tr class="row-{{ $c['status'] === 'error' ? 'error' : ($c['status'] === 'warn' ? 'warn' : 'ok') }}">
                                    <td>{{ $c['name'] }}</td>
                                    <td><span class="diag-badge {{ $c['status'] }}">{{ $c['status'] }}</span></td>
                                    <td>{{ $c['detail'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- System Validation --}}
            <div class="diag-tab-pane {{ $activeTab === 'system' ? 'active' : '' }}" data-pane="system">
                <div class="diag-tab-intro">
                    <p><strong>What this tab does:</strong> Verifies the hardcoded constants and dependencies the plugin assumes: PHP version, required SeAT classes, optional integration classes (Manager Core / Structure Manager / fitting plugin), Guzzle.</p>
                    <p><strong>When to use:</strong> After a SeAT or PHP upgrade, when integration features stop working, or when troubleshooting a fresh install.</p>
                </div>

                <div class="diag-section">
                    <div class="diag-section-body">
                        <table class="diag-detail-table">
                            <thead><tr><th>Item</th><th>Value</th><th>Status</th><th>Note</th></tr></thead>
                            <tbody>
                            @foreach($systemValidation as $item)
                                <tr class="row-{{ $item['status'] === 'error' ? 'error' : ($item['status'] === 'warn' ? 'warn' : 'ok') }}">
                                    <td><code>{{ $item['name'] }}</code></td>
                                    <td>{{ $item['value'] }}</td>
                                    <td><span class="diag-badge {{ $item['status'] }}">{{ $item['status'] }}</span></td>
                                    <td>{{ $item['note'] ?? '' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Settings Health --}}
            <div class="diag-tab-pane {{ $activeTab === 'settings' ? 'active' : '' }}" data-pane="settings">
                <div class="diag-tab-intro">
                    <p><strong>What this tab does:</strong> Lists every plugin setting with its current value, default, and where it lives (config file vs <code>discord_settings</code> table). Catches drift between config defaults and operator-edited UI overrides.</p>
                    <p><strong>When to use:</strong> When a feature behaves unexpectedly and you're not sure whether a setting changed, or to confirm a UI edit actually landed in the DB.</p>
                </div>

                <div class="diag-section">
                    <div class="diag-section-body">
                        <table class="diag-detail-table">
                            <thead><tr><th>Setting key</th><th>Current value</th><th>Default</th><th>Source</th><th>Note</th></tr></thead>
                            <tbody>
                            @foreach($settingsHealth as $row)
                                <tr>
                                    <td><code>{{ $row['key'] }}</code></td>
                                    <td><strong>{{ $row['value'] }}</strong></td>
                                    <td>{{ $row['default'] }}</td>
                                    <td>{{ $row['source'] }}</td>
                                    <td>{{ $row['note'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Data Integrity --}}
            <div class="diag-tab-pane {{ $activeTab === 'data' ? 'active' : '' }}" data-pane="data">
                <div class="diag-tab-intro">
                    <p><strong>What this tab does:</strong> Per-table row counts plus orphan / consistency checks — finds rows pointing at deleted parents, stale data past retention, and scheduled pings stuck overdue.</p>
                    <p><strong>When to use:</strong> After a manual DB intervention, when row counts look surprisingly low or high, or to confirm the cleanup job is actually pruning resolved tactical events.</p>
                </div>

                <div class="diag-section">
                    <div class="diag-section-header">
                        <h4 class="diag-section-title">Tables</h4>
                    </div>
                    <div class="diag-section-body">
                        <table class="diag-detail-table">
                            <thead><tr><th>Table</th><th>Purpose</th><th>Rows</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($dataIntegrity['tables'] as $t)
                                <tr class="{{ ! $t['exists'] ? 'row-error' : '' }}">
                                    <td><code>{{ $t['table'] }}</code></td>
                                    <td>{{ $t['label'] }}</td>
                                    <td>{{ $t['exists'] ? number_format((int) $t['rows']) : '—' }}</td>
                                    <td><span class="diag-badge {{ $t['exists'] ? 'ok' : 'error' }}">{{ $t['exists'] ? 'present' : 'missing' }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="diag-section">
                    <div class="diag-section-header">
                        <h4 class="diag-section-title">Issues</h4>
                    </div>
                    <div class="diag-section-body">
                        @if(empty($dataIntegrity['issues']))
                            <p class="diag-msg"><span class="diag-badge ok">clean</span> No data-integrity issues detected.</p>
                        @else
                            <table class="diag-detail-table">
                                <thead><tr><th>Severity</th><th>Detail</th></tr></thead>
                                <tbody>
                                @foreach($dataIntegrity['issues'] as $issue)
                                    <tr class="row-{{ $issue['severity'] === 'error' ? 'error' : ($issue['severity'] === 'warn' ? 'warn' : '') }}">
                                        <td><span class="diag-badge {{ $issue['severity'] === 'warn' ? 'warn' : ($issue['severity'] === 'error' ? 'error' : 'info') }}">{{ $issue['severity'] }}</span></td>
                                        <td>{{ $issue['detail'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Broadcast Trace --}}
            <div class="diag-tab-pane {{ $activeTab === 'trace' ? 'active' : '' }}" data-pane="trace">
                <div class="diag-tab-intro">
                    <p><strong>What this tab does:</strong> Walks a single scheduled ping or tactical event through the dispatch pipeline, showing each decision and its current state. The most powerful debugging surface — pick a specific row and see why it would (or wouldn't) fire.</p>
                    <p><strong>When to use:</strong> When an operator reports "my scheduled broadcast didn't send" or "this op didn't ping anyone" — paste the id and trace it end-to-end.</p>
                </div>

                <form method="GET" action="{{ route('discordpings.diagnostic') }}" class="diag-section">
                    <div class="diag-section-body">
                        <input type="hidden" name="diag_tab" value="trace">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label>Trace kind</label>
                                <select name="trace_kind" class="form-control">
                                    <option value="scheduled" {{ $traceKind === 'scheduled' ? 'selected' : '' }}>Scheduled ping</option>
                                    <option value="tactical"  {{ $traceKind === 'tactical' ? 'selected' : '' }}>Tactical event</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Row ID</label>
                                <input type="number" name="trace_id" class="form-control" value="{{ $traceId }}" placeholder="e.g. 42" min="1">
                            </div>
                            <div class="form-group col-md-3">
                                <button type="submit" class="btn btn-pings-primary">
                                    <i class="fas fa-search"></i> Trace
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                @if($broadcastTrace !== null)
                    @if(! $broadcastTrace['found'])
                        <div class="diag-summary error">
                            No {{ $broadcastTrace['kind'] === 'tactical' ? 'tactical event' : 'scheduled ping' }} with id <code>{{ $broadcastTrace['id'] }}</code> found.
                        </div>
                    @else
                        <div class="diag-section">
                            <div class="diag-section-header">
                                <h4 class="diag-section-title">
                                    Trace: {{ $broadcastTrace['kind'] === 'tactical' ? 'Tactical event' : 'Scheduled ping' }} #{{ $broadcastTrace['id'] }}
                                </h4>
                            </div>
                            <div class="diag-section-body">
                                <table class="diag-detail-table">
                                    <thead><tr><th>Step</th><th>Status</th><th>Detail</th></tr></thead>
                                    <tbody>
                                    @foreach($broadcastTrace['steps'] as $step)
                                        <tr class="row-{{ $step['status'] === 'error' ? 'error' : ($step['status'] === 'warn' ? 'warn' : '') }}">
                                            <td>{{ $step['label'] }}</td>
                                            <td><span class="diag-badge {{ $step['status'] }}">{{ $step['status'] }}</span></td>
                                            <td>{{ $step['detail'] }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if($broadcastTrace['kind'] === 'scheduled' && ! empty($broadcastTrace['history']) && $broadcastTrace['history']->isNotEmpty())
                            <div class="diag-section">
                                <div class="diag-section-header">
                                    <h4 class="diag-section-title">Recent PingHistory rows for the same (webhook, user) — last 7 days</h4>
                                </div>
                                <div class="diag-section-body">
                                    <table class="diag-detail-table">
                                        <thead><tr><th>History ID</th><th>When</th><th>Status</th><th>Error</th></tr></thead>
                                        <tbody>
                                        @foreach($broadcastTrace['history'] as $h)
                                            <tr class="row-{{ $h->status === 'failed' ? 'error' : ($h->status === 'rate_limited' ? 'warn' : '') }}">
                                                <td>#{{ $h->id }}</td>
                                                <td>{{ $h->created_at }}</td>
                                                <td><span class="diag-badge {{ $h->status === 'sent' ? 'ok' : ($h->status === 'failed' ? 'error' : 'warn') }}">{{ $h->status }}</span></td>
                                                <td>{{ $h->error_message ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($broadcastTrace['kind'] === 'tactical')
                            @php($isMining = (bool) ($broadcastTrace['isMining'] ?? false))
                            <div class="diag-section">
                                <div class="diag-section-header">
                                    <h4 class="diag-section-title">
                                        @if($isMining)
                                            Webhooks that WOULD receive a T-2h mining alert for this extraction right now
                                        @else
                                            Webhooks that WOULD receive a pre-timer ping for this event right now
                                        @endif
                                    </h4>
                                </div>
                                <div class="diag-section-body">
                                    @if($broadcastTrace['candidates']->isEmpty())
                                        <p class="diag-msg"><span class="diag-badge warn">none</span>
                                            @if($isMining)
                                                No webhooks would receive a mining alert for this extraction. At least one webhook must be active, flagged "Receive mining extraction alerts", and corp-scoped to this extraction's corporation (or "Any corporation").
                                            @else
                                                No webhooks would receive a ping for this event. Check the webhook list: at least one must be active, flagged "Receive structure timer alerts", and corp-scoped to this event's corporation (or "Any corporation").
                                            @endif
                                        </p>
                                    @else
                                        <table class="diag-detail-table">
                                            <thead><tr><th>Webhook ID</th><th>Name</th><th>Corp scope</th></tr></thead>
                                            <tbody>
                                            @foreach($broadcastTrace['candidates'] as $w)
                                                <tr>
                                                    <td>#{{ $w->id }}</td>
                                                    <td>{{ $w->name }}</td>
                                                    <td>{{ $w->corporation_id ?: 'Any corporation' }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>

                            <div class="diag-section">
                                <div class="diag-section-header">
                                    <h4 class="diag-section-title">Linked formup broadcasts (scheduled pings tied to this op)</h4>
                                </div>
                                <div class="diag-section-body">
                                    @if($broadcastTrace['linked']->isEmpty())
                                        <p class="diag-msg"><span class="diag-badge info">none</span> No FC has scheduled a form-up broadcast for this op yet.</p>
                                    @else
                                        <table class="diag-detail-table">
                                            <thead><tr><th>Ping ID</th><th>Scheduled at</th><th>Active?</th><th>Times sent</th></tr></thead>
                                            <tbody>
                                            @foreach($broadcastTrace['linked'] as $p)
                                                <tr>
                                                    <td>#{{ $p->id }}</td>
                                                    <td>{{ $p->scheduled_at }}</td>
                                                    <td>{{ $p->is_active ? 'yes' : 'no' }}</td>
                                                    <td>{{ $p->times_sent }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endif
                @else
                    <div class="diag-summary">
                        <strong>No trace yet.</strong> Pick a kind and enter a row ID above. Tip: row IDs come from the
                        <a href="{{ route('discordpings.scheduled') }}">Scheduled Broadcasts</a> page (scheduled),
                        the <a href="{{ route('discordpings.scheduled.calendar') }}">Broadcasts Calendar</a> URL parameters
                        (tactical), or direct DB inspection.
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    // Tab switching — URL-deep-link aware. Default landing = Health Checks.
    // Form submits on the Broadcast Trace tab already carry ?diag_tab=trace,
    // so they land back on the right tab after redirect.
    $('.diag-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.diag-tab').removeClass('active');
        $('.diag-tab-pane').removeClass('active');
        $(this).addClass('active');
        $('.diag-tab-pane[data-pane="' + tab + '"]').addClass('active');
    });
});
</script>
@endpush
