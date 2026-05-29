@extends('web::layouts.grids.12')

@section('title', 'Settings')
@section('page_header', 'Settings')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=2">
<style>
    /* Page-specific only — chrome lives in canonical CSS */
    .color-badge {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 3px;
        vertical-align: middle;
        margin-right: 5px;
    }
    .copy-btn {
        cursor: pointer;
    }
</style>
@endpush

@section('full')
<div class="discord-pings-wrapper">
    <div class="card card-dark">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#webhooks-tab">
                        <i class="fas fa-link"></i> Webhooks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#roles-tab">
                        <i class="fas fa-user-tag"></i> Discord Roles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#channels-tab">
                        <i class="fas fa-hashtag"></i> Discord Channels
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#stagings-tab">
                        <i class="fas fa-map-marker-alt"></i> Staging Locations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#pap-types-tab">
                        <i class="fas fa-tag"></i> PAP Types
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#structure-timers-tab">
                        <i class="fas fa-satellite-dish"></i> Structure Timers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#routing-map-tab">
                        <i class="fas fa-route"></i> Routing Map
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                {{-- Webhooks Tab --}}
                <div class="tab-pane fade show active" id="webhooks-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>Webhook Configuration</h4>
                        <a href="{{ route('discordpings.webhooks.create') }}" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Webhook
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table id="configWebhooksTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Channel Type</th>
                                    <th>Color</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($webhooks as $webhook)
                                    <tr>
                                        <td>{{ $webhook->name }}</td>
                                        <td>{{ $webhook->channel_type ?? 'General' }}</td>
                                        <td>
                                            <span class="color-badge" style="background-color: {{ $webhook->embed_color }}"></span>
                                            {{ $webhook->embed_color }}
                                        </td>
                                        <td>
                                            @if($webhook->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info test-webhook" data-id="{{ $webhook->id }}" title="Test">
                                                    <i class="fas fa-vial"></i>
                                                </button>
                                                <a href="{{ route('discordpings.webhooks.edit', $webhook->id) }}"
                                                   class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" action="{{ route('discordpings.webhooks.destroy', $webhook->id) }}"
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Roles Tab --}}
                <div class="tab-pane fade" id="roles-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>Discord Roles</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addRoleModal">
                            <i class="fas fa-plus"></i> Add Role
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="configRolesTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role ID</th>
                                    <th>Mention</th>
                                    <th>Color</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($roles as $role)
                                    <tr>
                                        <td>{{ $role->name }}</td>
                                        <td>
                                            <code>{{ $role->role_id }}</code>
                                            <i class="fas fa-copy copy-btn ml-1"
                                               data-copy="{{ $role->role_id }}"
                                               title="Copy ID"></i>
                                        </td>
                                        <td>
                                            <code>{{ $role->getMentionString() }}</code>
                                            <i class="fas fa-copy copy-btn ml-1"
                                               data-copy="{{ $role->getMentionString() }}"
                                               title="Copy mention"></i>
                                        </td>
                                        <td>
                                            @if($role->color)
                                                <span class="color-badge" style="background-color: {{ $role->color }}"></span>
                                                {{ $role->color }}
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($role->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info toggle-role"
                                                        data-id="{{ $role->id }}"
                                                        title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <form method="POST" action="{{ route('discordpings.config.roles.destroy', $role->id) }}"
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Channels Tab --}}
                <div class="tab-pane fade" id="channels-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>Discord Channels</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addChannelModal">
                            <i class="fas fa-plus"></i> Add Channel
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="configChannelsTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Channel Link</th>
                                    <th>Mention</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($channels as $channel)
                                    <tr>
                                        <td>{{ $channel->name }}</td>
                                        <td>
                                            <span class="badge badge-secondary">{{ ucfirst($channel->channel_type) }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ $channel->getChannelLink() }}" target="_blank">
                                                Open in Discord <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <code>{{ $channel->getMentionString() }}</code>
                                            <i class="fas fa-copy copy-btn ml-1"
                                               data-copy="{{ $channel->getMentionString() }}"
                                               title="Copy mention"></i>
                                        </td>
                                        <td>
                                            @if($channel->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info toggle-channel"
                                                        data-id="{{ $channel->id }}"
                                                        title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <form method="POST" action="{{ route('discordpings.config.channels.destroy', $channel->id) }}"
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Staging Locations Tab --}}
                <div class="tab-pane fade" id="stagings-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>Staging Locations</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addStagingModal">
                            <i class="fas fa-plus"></i> Add Staging
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="configStagingsTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>System</th>
                                    <th>Structure</th>
                                    <th>Default</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stagings ?? [] as $staging)
                                    <tr>
                                        <td>{{ $staging->name }}</td>
                                        <td>{{ $staging->system_name }}</td>
                                        <td>{{ $staging->structure_name ?? '-' }}</td>
                                        <td>
                                            @if($staging->is_default)
                                                <span class="badge badge-primary">Default</span>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary set-default-staging"
                                                        data-id="{{ $staging->id }}">
                                                    Set Default
                                                </button>
                                            @endif
                                        </td>
                                        <td>
                                            @if($staging->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info toggle-staging"
                                                        data-id="{{ $staging->id }}"
                                                        title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <form method="POST" action="{{ route('discordpings.config.stagings.destroy', $staging->id) }}"
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- PAP Types Tab --}}
                <div class="tab-pane fade" id="pap-types-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>PAP Types</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addPapTypeModal">
                            <i class="fas fa-plus"></i> Add PAP Type
                        </button>
                    </div>
                    <p class="text-muted">Manage the PAP type options available when composing broadcasts. These will appear in the PAP Type dropdown across all broadcast forms.</p>
                    <div class="table-responsive">
                        <table id="configPapTypesTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Sort Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($papTypes as $papType)
                                    <tr>
                                        <td>{{ $papType->name }}</td>
                                        <td>{{ $papType->sort_order }}</td>
                                        <td>
                                            @if($papType->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info toggle-pap-type"
                                                        data-id="{{ $papType->id }}"
                                                        title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <form method="POST" action="{{ route('discordpings.config.pap-types.destroy', $papType->id) }}"
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Delete this PAP type?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Structure Timers Tab --}}
                <div class="tab-pane fade" id="structure-timers-tab">
                    <h4>Structure Timer Integration</h4>
                    <p class="text-muted">
                        When Manager Core and Structure Manager are installed, structure timers and
                        fleet ops appear automatically on the Broadcasts Calendar, and flagged webhooks
                        can send pre-timer reminder pings. This integration is optional.
                    </p>

                    {{-- Integration status --}}
                    <div class="mb-3">
                        <span class="badge {{ $managerCoreInstalled ? 'badge-success' : 'badge-secondary' }}">
                            <i class="fas fa-{{ $managerCoreInstalled ? 'check' : 'times' }}"></i>
                            Manager Core {{ $managerCoreInstalled ? 'detected' : 'not installed' }}
                        </span>
                        <span class="badge {{ $structureManagerInstalled ? 'badge-success' : 'badge-secondary' }}">
                            <i class="fas fa-{{ $structureManagerInstalled ? 'check' : 'times' }}"></i>
                            Structure Manager {{ $structureManagerInstalled ? 'detected' : 'not installed' }}
                        </span>
                        <span class="badge {{ ($miningManagerInstalled ?? false) ? 'badge-success' : 'badge-secondary' }}">
                            <i class="fas fa-{{ ($miningManagerInstalled ?? false) ? 'check' : 'times' }}"></i>
                            Mining Manager {{ ($miningManagerInstalled ?? false) ? 'detected' : 'not installed' }}
                        </span>
                    </div>

                    @if(! ($managerCoreInstalled ?? false) || ! ($structureManagerInstalled ?? false))
                        <div class="alert-pings-styled alert-pings-info">
                            <i class="fas fa-info-circle"></i>
                            Install Manager Core and Structure Manager to activate this integration.
                            Structure timers will then flow onto the calendar automatically.
                            Install Mining Manager (v2.0.1+) too to also surface moon extractions.
                        </div>
                    @endif

                    {{-- Settings form --}}
                    <form method="POST" action="{{ route('discordpings.config.structure-timers') }}">
                        @csrf
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="structure_alerts_enabled"
                                       name="structure_alerts_enabled" value="1"
                                       {{ ($structureAlertsEnabled ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="structure_alerts_enabled">
                                    Send pre-timer reminder pings (structure timers)
                                </label>
                            </div>
                            <small class="text-muted">
                                When enabled, webhooks flagged with "Receive structure timer alerts" (on the
                                Webhooks tab) get an automatic Discord reminder at T-24h and T-1h before each
                                structure timer. Turn this off to stop all pre-timer pings without un-flagging
                                individual webhooks. The calendar keeps showing timers either way.
                            </small>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="mining_alerts_enabled"
                                       name="mining_alerts_enabled" value="1"
                                       {{ ($miningAlertsEnabled ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="mining_alerts_enabled">
                                    Send pre-expiry alerts (mining extractions)
                                </label>
                            </div>
                            <small class="text-muted">
                                When enabled, webhooks flagged with "Receive mining extraction alerts" get a
                                single Discord alert at T-2h before the fleet-able window closes on each moon
                                extraction. Requires Mining Manager v2.0.1+. Turn off to silence mining alerts
                                without un-flagging webhooks; mining extractions still appear on the calendar.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="formup_offset_minutes">Default form-up lead time (minutes)</label>
                            <input type="number" class="form-control" id="formup_offset_minutes"
                                   name="formup_offset_minutes" min="5" max="720" step="5"
                                   value="{{ $formupOffsetMinutes ?? 30 }}"
                                   style="max-width: 200px;">
                            <small class="text-muted">
                                When an FC clicks "Schedule formup ping" from a tactical event (Calendar modal
                                or FC Opportunities board), the broadcast is pre-scheduled this many minutes
                                before the timer. Defaults to 30. Range: 5 minutes to 12 hours.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-pings-primary">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </form>
                </div>

                {{-- Routing Map Tab --}}
                <div class="tab-pane fade" id="routing-map-tab">
                    <h4>Notification Routing Map</h4>
                    <p class="text-muted">
                        Read-only snapshot of where each event the plugin reacts to fires. Use this
                        to confirm "what pings where" at a glance without clicking through every
                        webhook. Updates live as you flag webhooks or change scope.
                    </p>

                    {{-- Integration status pills --}}
                    <div class="mb-3">
                        <span class="badge {{ ($managerCoreInstalled ?? false) ? 'badge-success' : 'badge-secondary' }}">
                            <i class="fas fa-{{ ($managerCoreInstalled ?? false) ? 'check' : 'times' }}"></i>
                            Manager Core {{ ($managerCoreInstalled ?? false) ? 'detected' : 'not installed' }}
                        </span>
                        <span class="badge {{ ($structureManagerInstalled ?? false) ? 'badge-success' : 'badge-secondary' }}">
                            <i class="fas fa-{{ ($structureManagerInstalled ?? false) ? 'check' : 'times' }}"></i>
                            Structure Manager {{ ($structureManagerInstalled ?? false) ? 'detected' : 'not installed' }}
                        </span>
                        <span class="badge {{ ($miningManagerInstalled ?? false) ? 'badge-success' : 'badge-secondary' }}">
                            <i class="fas fa-{{ ($miningManagerInstalled ?? false) ? 'check' : 'times' }}"></i>
                            Mining Manager {{ ($miningManagerInstalled ?? false) ? 'detected' : 'not installed' }}
                        </span>
                        <span class="badge {{ ($structureAlertsEnabled ?? true) ? 'badge-success' : 'badge-warning' }}">
                            <i class="fas fa-{{ ($structureAlertsEnabled ?? true) ? 'check' : 'pause' }}"></i>
                            Pre-timer pings master switch {{ ($structureAlertsEnabled ?? true) ? 'ON' : 'OFF' }}
                        </span>
                        <span class="badge {{ ($miningAlertsEnabled ?? true) ? 'badge-success' : 'badge-warning' }}">
                            <i class="fas fa-{{ ($miningAlertsEnabled ?? true) ? 'check' : 'pause' }}"></i>
                            Mining alerts master switch {{ ($miningAlertsEnabled ?? true) ? 'ON' : 'OFF' }}
                        </span>
                    </div>

                    {{-- Pre-Timer Reminder Pings --}}
                    <h5 class="mt-4"><i class="fas fa-bell"></i> Pre-Timer Reminder Pings</h5>
                    <p class="text-muted">
                        Webhooks flagged with "Receive structure timer alerts" fire automatically at
                        T-24h and T-1h before each Structure Manager timer event. Corp-scoped webhooks
                        only receive alerts for their corporation; "Any corporation" webhooks receive
                        everything.
                    </p>

                    @if(($alertWebhooks ?? collect())->isEmpty())
                        <div class="alert-pings-styled alert-pings-info">
                            <i class="fas fa-info-circle"></i>
                            No webhooks are flagged to receive structure timer alerts. Edit a webhook
                            on the Webhooks tab and tick "Receive structure timer alerts" to route
                            pre-timer reminders here.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Webhook</th>
                                        <th>Corporation Scope</th>
                                        <th>State</th>
                                        <th>Receives pre-timer pings?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($alertWebhooks as $w)
                                    @php
                                        $masterOn  = (bool) ($structureAlertsEnabled ?? true);
                                        $mcOk      = (bool) ($managerCoreInstalled ?? false);
                                        $smOk      = (bool) ($structureManagerInstalled ?? false);
                                        $active    = (bool) $w->is_active;
                                        $effective = $masterOn && $active && $mcOk && $smOk;
                                        $corpName  = $w->corporation_id
                                            ? (($routingCorpNames ?? [])[$w->corporation_id] ?? null)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="color-badge" style="background-color: {{ $w->embed_color }}"></span>
                                            <strong>{{ $w->name }}</strong>
                                            @if($w->channel_type)
                                                <br><small class="text-muted">{{ $w->channel_type }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($w->corporation_id)
                                                <span class="badge badge-info">
                                                    {{ $corpName ?: 'Corp #' . $w->corporation_id }}
                                                </span>
                                                <br><small class="text-muted">only this corp's events</small>
                                            @else
                                                <span class="badge badge-secondary">Any corporation</span>
                                                <br><small class="text-muted">receives all events</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(! $active)
                                                <span class="badge badge-danger">Webhook disabled</span>
                                            @elseif(! $masterOn)
                                                <span class="badge badge-warning">Master switch OFF</span>
                                            @elseif(! $mcOk || ! $smOk)
                                                <span class="badge badge-secondary">Integration dormant</span>
                                            @else
                                                <span class="badge badge-success">Active</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($effective)
                                                <span class="badge badge-success">Yes</span>
                                            @else
                                                <span class="badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        @php
                            $deliveringCount = $alertWebhooks->filter(function ($w) use ($structureAlertsEnabled, $managerCoreInstalled, $structureManagerInstalled) {
                                return $w->is_active
                                    && ($structureAlertsEnabled ?? true)
                                    && ($managerCoreInstalled ?? false)
                                    && ($structureManagerInstalled ?? false);
                            })->count();
                        @endphp
                        <p class="text-muted mt-3" style="font-size: 0.9rem;">
                            <strong>Summary:</strong>
                            {{ $alertWebhooks->count() }} webhook{{ $alertWebhooks->count() === 1 ? '' : 's' }}
                            flagged for structure alerts;
                            {{ $deliveringCount }} actively delivering right now.
                            @if($deliveringCount === 0 && $alertWebhooks->count() > 0)
                                <span class="text-warning">No pings will fire until the issues in the State column are resolved.</span>
                            @endif
                        </p>
                    @endif

                    {{-- Pre-Expiry Mining Alerts --}}
                    <h5 class="mt-4"><i class="fas fa-gem"></i> Pre-Expiry Mining Alerts</h5>
                    <p class="text-muted">
                        Webhooks flagged with "Receive mining extraction alerts" fire a single
                        T-2h Discord embed before each moon extraction's fleet-able window closes.
                        Corp-scoped webhooks only receive alerts for their corporation; "Any
                        corporation" webhooks receive everything.
                    </p>

                    @if(($miningAlertWebhooks ?? collect())->isEmpty())
                        <div class="alert-pings-styled alert-pings-info">
                            <i class="fas fa-info-circle"></i>
                            No webhooks are flagged to receive mining extraction alerts. Edit a
                            webhook on the Webhooks tab and tick "Receive mining extraction alerts"
                            to route pre-expiry reminders here.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Webhook</th>
                                        <th>Corporation Scope</th>
                                        <th>State</th>
                                        <th>Receives mining alerts?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($miningAlertWebhooks as $w)
                                    @php
                                        $masterOn  = (bool) ($miningAlertsEnabled ?? true);
                                        $mcOk      = (bool) ($managerCoreInstalled ?? false);
                                        $mmOk      = (bool) ($miningManagerInstalled ?? false);
                                        $active    = (bool) $w->is_active;
                                        $effective = $masterOn && $active && $mcOk && $mmOk;
                                        $corpName  = $w->corporation_id
                                            ? (($routingCorpNames ?? [])[$w->corporation_id] ?? null)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="color-badge" style="background-color: {{ $w->embed_color }}"></span>
                                            <strong>{{ $w->name }}</strong>
                                            @if($w->channel_type)
                                                <br><small class="text-muted">{{ $w->channel_type }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($w->corporation_id)
                                                <span class="badge badge-info">
                                                    {{ $corpName ?: 'Corp #' . $w->corporation_id }}
                                                </span>
                                                <br><small class="text-muted">only this corp's extractions</small>
                                            @else
                                                <span class="badge badge-secondary">Any corporation</span>
                                                <br><small class="text-muted">receives all extractions</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(! $active)
                                                <span class="badge badge-danger">Webhook disabled</span>
                                            @elseif(! $masterOn)
                                                <span class="badge badge-warning">Master switch OFF</span>
                                            @elseif(! $mcOk || ! $mmOk)
                                                <span class="badge badge-secondary">Integration dormant</span>
                                            @else
                                                <span class="badge badge-success">Active</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($effective)
                                                <span class="badge badge-success">Yes</span>
                                            @else
                                                <span class="badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        @php
                            $miningDeliveringCount = $miningAlertWebhooks->filter(function ($w) use ($miningAlertsEnabled, $managerCoreInstalled, $miningManagerInstalled) {
                                return $w->is_active
                                    && ($miningAlertsEnabled ?? true)
                                    && ($managerCoreInstalled ?? false)
                                    && ($miningManagerInstalled ?? false);
                            })->count();
                        @endphp
                        <p class="text-muted mt-3" style="font-size: 0.9rem;">
                            <strong>Summary:</strong>
                            {{ $miningAlertWebhooks->count() }} webhook{{ $miningAlertWebhooks->count() === 1 ? '' : 's' }}
                            flagged for mining alerts;
                            {{ $miningDeliveringCount }} actively delivering right now.
                            @if($miningDeliveringCount === 0 && $miningAlertWebhooks->count() > 0)
                                <span class="text-warning">No alerts will fire until the issues in the State column are resolved.</span>
                            @endif
                        </p>
                    @endif

                    {{-- Planning Data Ingest (informational) --}}
                    <h5 class="mt-4"><i class="fas fa-satellite-dish"></i> Planning Data Ingest <small class="text-muted">(informational)</small></h5>
                    <p class="text-muted">
                        Structure timer and mining extraction events ingest into the local
                        <code>discord_tactical_events</code> table and surface on the
                        <strong>FC Opportunities</strong> board (the planning surface). They are
                        not displayed on the Broadcasts Calendar, which stays focused on actual
                        broadcasts. Not a notification (no Discord delivery); listed here for
                        completeness of the event routing surface.
                    </p>
                    <div class="mb-3">
                        <span class="badge badge-secondary">
                            <i class="fas fa-arrow-right"></i>
                            Source: <code>structure_manager.timer.*</code> via Manager Core EventBus
                        </span>
                        <span class="badge badge-secondary">
                            <i class="fas fa-arrow-right"></i>
                            Destination: FC Opportunities (<code>discord_tactical_events</code>)
                        </span>
                        <span class="badge {{ ($managerCoreInstalled ?? false) && ($structureManagerInstalled ?? false) ? 'badge-success' : 'badge-warning' }}">
                            <i class="fas fa-{{ ($managerCoreInstalled ?? false) && ($structureManagerInstalled ?? false) ? 'check' : 'pause' }}"></i>
                            {{ ($managerCoreInstalled ?? false) && ($structureManagerInstalled ?? false) ? 'Active' : 'Inactive — needs Manager Core + Structure Manager' }}
                        </span>
                    </div>
                    <div class="mb-3">
                        <span class="badge badge-secondary">
                            <i class="fas fa-arrow-right"></i>
                            Source: <code>mining.extraction_*</code> via Manager Core EventBus
                        </span>
                        <span class="badge badge-secondary">
                            <i class="fas fa-arrow-right"></i>
                            Destination: FC Opportunities (<code>discord_tactical_events</code>, category=mining)
                        </span>
                        <span class="badge {{ ($managerCoreInstalled ?? false) && ($miningManagerInstalled ?? false) ? 'badge-success' : 'badge-warning' }}">
                            <i class="fas fa-{{ ($managerCoreInstalled ?? false) && ($miningManagerInstalled ?? false) ? 'check' : 'pause' }}"></i>
                            {{ ($managerCoreInstalled ?? false) && ($miningManagerInstalled ?? false) ? 'Active' : 'Inactive — needs Manager Core + Mining Manager v2.0.1+' }}
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Add PAP Type Modal --}}
    <div class="modal fade" id="addPapTypeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('discordpings.config.pap-types.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add PAP Type</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>PAP Type Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required
                                   placeholder="e.g., Roam, Deployment, Home Defence">
                            <small class="form-text text-muted">Must be unique. This name will appear in the PAP Type dropdown.</small>
                        </div>
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                            <small class="form-text text-muted">Lower numbers appear first in the dropdown.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-pings-primary">
                            <i class="fas fa-plus"></i> Add PAP Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Role Modal --}}
    <div class="modal fade" id="addRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('discordpings.config.roles.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Discord Role</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @if(($connectorAvailable ?? false) && count($connectorRoles ?? []) > 0)
                            {{-- Connector-sourced picker: lets operators short-circuit the
                                 Discord Developer-Mode round-trip when warlof/seat-connector
                                 or the legacy connector is installed alongside SeAT Broadcast.
                                 The resolver only lists roles NOT already added, so picking
                                 one won't create duplicates. --}}
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-bolt"></i> Quick-pick from
                                    <span class="text-muted">{{ $connectorProviderLabel }}</span>
                                </label>
                                <select id="rolePickerSelect" class="form-control">
                                    <option value="">— Pick a role to pre-fill, or leave blank and type manually below —</option>
                                    @foreach($connectorRoles as $r)
                                        <option
                                            value="{{ $r['id'] }}"
                                            data-name="{{ $r['name'] }}"
                                            data-source="{{ $r['source'] }}">
                                            {{ $r['name'] }}
                                            ({{ $r['id'] }})
                                            — {{ \DiscordPings\Services\DiscordRoleResolver::providerShortLabel($r['source']) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    {{ count($connectorRoles) }} role{{ count($connectorRoles) === 1 ? '' : 's' }} available from your connector plugin. Already-added roles are hidden so you do not create duplicates.
                                </small>
                            </div>
                        @endif

                        <div class="form-group">
                            <label>Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="addRoleName" class="form-control" required
                                   placeholder="e.g., Fleet Commanders">
                            <small class="form-text text-muted">A friendly name for this role</small>
                        </div>
                        <div class="form-group">
                            <label>Role ID or Mention <span class="text-danger">*</span></label>
                            <input type="text" name="role_id" id="addRoleSnowflake" class="form-control" required
                                   placeholder="e.g., 123456789 or <@&123456789>">
                            <small class="form-text text-muted">
                                Copy from Discord: Right-click role → Copy ID, or copy a role mention.
                                @if(($connectorAvailable ?? false))
                                    Or use the quick-pick dropdown above.
                                @endif
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Color (Optional)</label>
                            <input type="text" name="color" class="form-control" 
                                   pattern="^#[0-9A-Fa-f]{6}$" 
                                   placeholder="#5865F2">
                            <small class="form-text text-muted">Hex color code for display</small>
                        </div>
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="Optional description for this role"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-pings-primary">
                            <i class="fas fa-plus"></i> Add Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Channel Modal --}}
    <div class="modal fade" id="addChannelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('discordpings.config.channels.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Discord Channel</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Channel Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="e.g., fleet-formup">
                            <small class="form-text text-muted">A friendly name for this channel</small>
                        </div>
                        <div class="form-group">
                            <label>Channel URL <span class="text-danger">*</span></label>
                            <input type="url" name="channel_url" class="form-control" required 
                                   placeholder="https://discord.com/channels/123456/789012">
                            <small class="form-text text-muted">
                                Right-click channel in Discord → Copy Link
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Channel Type</label>
                            <select name="channel_type" class="form-control">
                                <option value="text">Text Channel</option>
                                <option value="voice">Voice Channel</option>
                                <option value="announcement">Announcement Channel</option>
                                <option value="forum">Forum Channel</option>
                                <option value="stage">Stage Channel</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="Optional description for this channel"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-pings-primary">
                            <i class="fas fa-plus"></i> Add Channel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Staging Modal --}}
    <div class="modal fade" id="addStagingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('discordpings.config.stagings.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Staging Location</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Location Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="e.g., Home Staging">
                            <small class="form-text text-muted">A friendly name for this staging</small>
                        </div>
                        <div class="form-group">
                            <label>System Name <span class="text-danger">*</span></label>
                            <input type="text" name="system_name" class="form-control" required 
                                   placeholder="e.g., Jita">
                            <small class="form-text text-muted">The EVE system name</small>
                        </div>
                        <div class="form-group">
                            <label>Structure Name (Optional)</label>
                            <input type="text" name="structure_name" class="form-control" 
                                   placeholder="e.g., 4-4 CNAP">
                            <small class="form-text text-muted">Station or citadel name</small>
                        </div>
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="Optional notes about this staging"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" 
                                       id="is_default" name="is_default" value="1">
                                <label class="custom-control-label" for="is_default">
                                    Set as default staging location
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-pings-primary">
                            <i class="fas fa-plus"></i> Add Staging
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@stop

@push('javascript')
<script src="{{ asset('vendor/discordpings/js/vendor/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/discordpings/js/vendor/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize DataTables on all config tables
    var dtOptions = {
        pageLength: 25,
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search...',
            emptyTable: 'No entries configured'
        },
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    };

    $('#configWebhooksTable').DataTable(dtOptions);
    $('#configRolesTable').DataTable(dtOptions);
    $('#configChannelsTable').DataTable(dtOptions);
    $('#configStagingsTable').DataTable(dtOptions);
    $('#configPapTypesTable').DataTable(dtOptions);

    // Add Role modal: connector quick-pick pre-fills name + snowflake when
    // the operator selects one of the suggested roles. Manual entry still
    // works as the always-available fallback (just don't pick from the
    // dropdown).
    $('#rolePickerSelect').on('change', function() {
        var $option = $(this).find(':selected');
        var snowflake = $option.val();
        var name = $option.data('name');
        if (snowflake) {
            $('#addRoleSnowflake').val(snowflake);
            if (name && !$('#addRoleName').val()) {
                $('#addRoleName').val(name);
            }
        }
    });

    // Copy to clipboard functionality
    $('.copy-btn').click(function() {
        const textToCopy = $(this).data('copy');
        const $btn = $(this);
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            $btn.removeClass('fa-copy').addClass('fa-check text-success');
            setTimeout(function() {
                $btn.removeClass('fa-check text-success').addClass('fa-copy');
            }, 2000);
        });
    });
    
    // Toggle role status
    $('.toggle-role').click(function() {
        const roleId = $(this).data('id');
        const $btn = $(this);
        
        $.post(`{{ url('discord-pings/config/roles') }}/${roleId}/toggle`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            location.reload();
        })
        .fail(function() {
            alert('Failed to toggle role status');
        });
    });
    
    // Toggle channel status
    $('.toggle-channel').click(function() {
        const channelId = $(this).data('id');
        const $btn = $(this);
        
        $.post(`{{ url('discord-pings/config/channels') }}/${channelId}/toggle`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            location.reload();
        })
        .fail(function() {
            alert('Failed to toggle channel status');
        });
    });

    // Toggle staging status
    $('.toggle-staging').click(function() {
        const stagingId = $(this).data('id');
        
        $.post(`{{ url('discord-pings/config/stagings') }}/${stagingId}/toggle`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            location.reload();
        })
        .fail(function() {
            alert('Failed to toggle staging status');
        });
    });
    
    // Set default staging
    $('.set-default-staging').click(function() {
        const stagingId = $(this).data('id');
        
        $.post(`{{ url('discord-pings/config/stagings') }}/${stagingId}/default`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            location.reload();
        })
        .fail(function() {
            alert('Failed to set default staging');
        });
    });

    // Toggle PAP type status
    $('.toggle-pap-type').click(function() {
        const papTypeId = $(this).data('id');

        $.post(`{{ url('discord-pings/config/pap-types') }}/${papTypeId}/toggle`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            location.reload();
        })
        .fail(function() {
            alert('Failed to toggle PAP type status');
        });
    });

    // Test webhook
    $('.test-webhook').click(function() {
        const webhookId = $(this).data('id');
        const button = $(this);
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.post(`{{ url('discord-pings/webhooks') }}/${webhookId}/test`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            button.removeClass('btn-info').addClass('btn-success');
            button.html('<i class="fas fa-check"></i>');
            alert('Test successful! Check your Discord channel.');
        })
        .fail(function() {
            button.removeClass('btn-info').addClass('btn-danger');
            button.html('<i class="fas fa-times"></i>');
            alert('Test failed! Please check the webhook URL.');
        })
        .always(function() {
            setTimeout(function() {
                button.prop('disabled', false)
                      .removeClass('btn-success btn-danger')
                      .addClass('btn-info')
                      .html('<i class="fas fa-vial"></i>');
            }, 3000);
        });
    });
    
    // Remember active tab
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        localStorage.setItem('activeDiscordConfigTab', $(e.target).attr('href'));
    });
    
    // Restore active tab
    var activeTab = localStorage.getItem('activeDiscordConfigTab');
    if (activeTab) {
        $(`a[href="${activeTab}"]`).tab('show');
    }
});
</script>
@endpush
