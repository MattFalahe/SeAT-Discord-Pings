@extends('web::layouts.grids.12')

@section('title', 'FC Opportunities')
@section('page_header', 'FC Opportunities')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=3">
<style>
    /* Page-specific only — chrome lives in canonical CSS */
    .ops-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }
    .ops-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        font-size: 0.85rem;
        color: #d1d5db;
    }
    .ops-countdown {
        display: inline-block;
        font-variant-numeric: tabular-nums;
        font-size: 0.85rem;
        color: #9ca3af;
    }
    .ops-countdown.imminent { color: #ffc107; font-weight: 600; }
    .ops-countdown.now      { color: #dc3545; font-weight: 600; }
    .ops-countdown.elapsed  { color: #6b7280; text-decoration: line-through; }
    .ops-eve-time {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        color: #e2e8f0;
    }
    .op-type-icon { font-size: 0.95rem; margin-right: 4px; }
    #opportunitiesTable a { color: #a5b4fc; }
    #opportunitiesTable .badge { font-size: 0.78rem; }
    #requestsTable .badge { font-size: 0.78rem; }
    .ops-ago { font-size: 0.8rem; color: #9ca3af; }
    .ops-request-text { white-space: pre-wrap; font-size: 0.85rem; max-width: 480px; }
    .ops-answered { font-size: 0.85rem; white-space: nowrap; }
    .ops-answered-at { font-size: 0.8rem; color: #9ca3af; }
</style>
@endpush

@section('full')
<div class="discord-pings-wrapper">
    @if($requestsInUse ?? false)
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-hand-paper"></i> Member requests from the last {{ $requestHours }} hours
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="requestsTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sent (EVE)</th>
                                <th>Request</th>
                                <th>From</th>
                                <th>What they wrote</th>
                                <th>Answered by</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($requests as $memberRequest)
                            @php
                                $sentIso = $memberRequest->created_at->utc()->toIso8601String();
                                $requestButton = (string) ($memberRequest->fields['button'] ?? '');
                                $wrote = trim((string) $memberRequest->message);
                            @endphp
                            <tr>
                                <td data-order="{{ $memberRequest->created_at->timestamp }}">
                                    <span class="ops-eve-time eve-time" data-eve-time="{{ $sentIso }}" data-show-local>
                                        {{ $memberRequest->created_at->utc()->format('Y-m-d H:i') }} EVE
                                    </span>
                                    <br><span class="ops-ago" data-sent-at="{{ $sentIso }}"></span>
                                </td>
                                <td>
                                    @include('discordpings::requests.partials.type-badge', ['row' => $memberRequest])
                                    {{ $requestButton }}
                                </td>
                                <td>
                                    <strong>{{ $memberRequest->fields['main'] ?? $memberRequest->user_name }}</strong>
                                    @if(! empty($memberRequest->fields['corporation']))
                                        <br><small>{{ $memberRequest->fields['corporation'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($wrote !== '' && $wrote !== $requestButton)
                                        <div class="ops-request-text">{{ Str::limit($wrote, 300) }}</div>
                                    @else
                                        <span class="text-muted" style="font-size: 0.85rem;">Nothing filled in</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($requestAnswers === null)
                                        <span class="text-muted" style="font-size: 0.85rem;">Couldn't check</span>
                                    @elseif (empty($requestAnswers[$memberRequest->id]))
                                        <span class="text-muted" style="font-size: 0.85rem;">Nobody yet</span>
                                    @else
                                        @foreach ($requestAnswers[$memberRequest->id] as $answer)
                                            <div class="ops-answered">
                                                <i class="fas fa-user-check text-success"></i>
                                                <strong>{{ $answer['name'] }}</strong>
                                                <span class="ops-answered-at eve-time" data-eve-time="{{ gmdate('Y-m-d\TH:i:s\Z', $answer['at']) }}" data-show-local>{{ gmdate('H:i', $answer['at']) }} EVE</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{-- Form-up: the Send form, starting from what the member wrote. --}}
                                    <a href="{{ route('discordpings.send', ['ping_request_id' => $memberRequest->id]) }}"
                                       class="btn btn-sm btn-pings-primary"
                                       title="Open the Send Broadcast form with this request filled in">
                                        <i class="fas fa-paper-plane"></i> Form-up
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-bullseye"></i> FC Opportunities
            </h3>
            <div class="card-tools">
                <form method="GET" action="{{ route('discordpings.opportunities') }}"
                      class="form-inline" style="display: inline-flex; gap: 6px; margin-right: 10px;">
                    <label for="rangeSelect" class="mb-0" style="font-size: 0.85rem;">Range:</label>
                    <select id="rangeSelect" name="range" class="form-control form-control-sm"
                            onchange="this.form.submit()">
                        @foreach([1, 2, 3, 7, 14, 30] as $d)
                            <option value="{{ $d }}" {{ ($rangeDays ?? 7) == $d ? 'selected' : '' }}>
                                Next {{ $d }} {{ $d === 1 ? 'day' : 'days' }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('discordpings.scheduled.calendar') }}" class="btn btn-sm btn-pings-secondary">
                    <i class="fas fa-calendar-alt"></i> Calendar
                </a>
            </div>
        </div>
        <div class="card-body">

            {{-- Integration status pills --}}
            <div class="ops-meta-row">
                <span class="ops-pill">
                    <i class="fas fa-{{ ($managerCoreInstalled ?? false) ? 'check text-success' : 'times text-danger' }}"></i>
                    Manager Core
                </span>
                <span class="ops-pill">
                    <i class="fas fa-{{ ($structureManagerInstalled ?? false) ? 'check text-success' : 'times text-danger' }}"></i>
                    Structure Manager
                </span>
                <span class="ops-pill">
                    <i class="fas fa-{{ ($miningManagerInstalled ?? false) ? 'check text-success' : 'times text-danger' }}"></i>
                    Mining Manager
                </span>
                <span class="ops-pill">
                    <i class="fas fa-list-ol"></i>
                    {{ $events->count() }} {{ $events->count() === 1 ? 'op' : 'ops' }} in window
                </span>
            </div>

            @if (! ($managerCoreInstalled ?? false))
                <div class="alert-pings-styled alert-pings-info">
                    <i class="fas fa-info-circle"></i>
                    Install Manager Core to populate this board. With Structure Manager or Mining Manager
                    installed too, structure timers and moon extractions appear here as they are created.
                </div>
            @elseif (! ($structureManagerInstalled ?? false) && ! ($miningManagerInstalled ?? false) && $events->isEmpty())
                <div class="alert-pings-styled alert-pings-info">
                    <i class="fas fa-info-circle"></i>
                    Install Structure Manager or Mining Manager to fill this board. Structure timers come
                    from one and moon extractions from the other.
                </div>
            @elseif($events->isEmpty())
                <div class="alert-pings-styled alert-pings-info">
                    <i class="fas fa-check-circle"></i>
                    No upcoming ops in the selected window. Once Structure Manager has a timer in range,
                    it will appear here automatically.
                </div>
            @else
                <div class="table-responsive">
                    <table id="opportunitiesTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Time (EVE)</th>
                                <th>Countdown</th>
                                <th>Op</th>
                                <th>Structure / System</th>
                                <th>Severity</th>
                                <th>Parties</th>
                                <th>Broadcasts</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($events as $event)
                            @php
                                $opIconMap = ['hostile_op' => '⚔️', 'defense_op' => '🛡️'];
                                $catIconMap = ['fuel' => '⛽', 'tactical' => '⚔️', 'lifecycle' => '🏗️', 'mining' => '⛏️'];
                                if ($event->is_manual && isset($opIconMap[$event->event_type])) {
                                    $opIcon = $opIconMap[$event->event_type];
                                } elseif ($event->is_manual) {
                                    $opIcon = '📌';
                                } else {
                                    $opIcon = $catIconMap[$event->category_group] ?? '🛰️';
                                }
                                $opLabel = match ($event->event_type) {
                                    'hostile_op'      => 'Hostile Op',
                                    'defense_op'      => 'Defense Op',
                                    'moon_extraction' => 'Mining Extraction',
                                    default           => trim(ucwords(str_replace('_', ' ', (string) $event->event_type))) ?: 'Timer',
                                };
                                $sevClass = ['critical' => 'badge-danger', 'warning' => 'badge-warning', 'info' => 'badge-info'][$event->severity] ?? 'badge-info';
                                $eveTimeIso = optional($event->eve_time)->toIso8601String();
                                $pingCount = $event->scheduledPings->count();
                                // Source-plugin badge — surfaces which upstream plugin
                                // produced the event. Helps operators triage which
                                // tool to dig into when something looks odd.
                                $sourceMeta = match ($event->source_plugin) {
                                    'structure-manager' => ['label' => 'SM', 'title' => 'Source: Structure Manager', 'bg' => '#667eea', 'fg' => '#ffffff'],
                                    'mining-manager'    => ['label' => 'MM', 'title' => 'Source: Mining Manager',    'bg' => '#1abc9c', 'fg' => '#ffffff'],
                                    null, ''            => ['label' => '—',  'title' => 'Source unknown',             'bg' => '#6c757d', 'fg' => '#ffffff'],
                                    default             => ['label' => strtoupper(substr($event->source_plugin, 0, 3)), 'title' => 'Source: ' . $event->source_plugin, 'bg' => '#6c757d', 'fg' => '#ffffff'],
                                };
                                // Details link is whatever the source plugin published
                                // as the per-event detail URL ($event->url). Label
                                // mirrors the source badge for visual consistency.
                                $detailsHref = $event->url;
                                $detailsTitle = match ($event->source_plugin) {
                                    'structure-manager' => 'Open this op in Structure Manager',
                                    'mining-manager'    => 'Open this extraction in Mining Manager',
                                    default             => 'Open source details',
                                };
                            @endphp
                            <tr>
                                <td data-order="{{ $eveTimeIso ?? '' }}">
                                    @if($event->eve_time)
                                        <span class="ops-eve-time eve-time" data-eve-time="{{ $eveTimeIso }}" data-show-local>
                                            {{ $event->eve_time->format('Y-m-d H:i') }} EVE
                                        </span>
                                    @else
                                        <span class="ops-eve-time">—</span>
                                    @endif
                                </td>
                                <td data-order="{{ $eveTimeIso ?? '' }}">
                                    <span class="ops-countdown" data-eve-time="{{ $eveTimeIso }}">—</span>
                                </td>
                                <td>
                                    <span class="op-type-icon">{{ $opIcon }}</span>
                                    {{ $opLabel }}
                                    @if($event->is_manual)
                                        <span class="badge badge-secondary" style="font-size: 0.7rem;">Manual</span>
                                    @endif
                                    <span class="badge"
                                          style="font-size: 0.7rem; background-color: {{ $sourceMeta['bg'] }}; color: {{ $sourceMeta['fg'] }};"
                                          title="{{ $sourceMeta['title'] }}">
                                        {{ $sourceMeta['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $event->structure_name ?? '—' }}</strong>
                                    @if($event->structure_type)
                                        <br><small class="text-muted">{{ $event->structure_type }}</small>
                                    @endif
                                    @if($event->system_name)
                                        <br><small>
                                            {{ $event->system_name }}@if($event->system_security !== null) ({{ number_format((float) $event->system_security, 1) }})@endif
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $sevClass }}">
                                        {{ ucfirst($event->severity ?? 'info') }}
                                    </span>
                                </td>
                                <td>
                                    @if($event->owner_corporation_name)
                                        <small>🚩 {{ $event->owner_corporation_name }}</small>
                                    @endif
                                    @if($event->attacker_corporation_name)
                                        <br><small>⚔️ {{ $event->attacker_corporation_name }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($pingCount > 0)
                                        <span class="badge badge-success" title="Form-up broadcasts already scheduled for this op">
                                            📡 {{ $pingCount }} scheduled
                                        </span>
                                    @else
                                        <span class="text-muted" style="font-size: 0.85rem;">None yet</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{-- Form-up: immediate Send (use when fleet is staging RIGHT NOW). --}}
                                    <a href="{{ route('discordpings.send', ['tactical_event_id' => $event->id]) }}"
                                       class="btn btn-sm btn-pings-primary"
                                       title="Open the Send Broadcast form pre-filled for immediate dispatch (forming up now)">
                                        <i class="fas fa-paper-plane"></i> Form-up
                                    </a>
                                    {{-- Schedule: queue a broadcast for a future time (default = timer minus formup offset). --}}
                                    <a href="{{ route('discordpings.scheduled.create', ['tactical_event_id' => $event->id]) }}"
                                       class="btn btn-sm btn-pings-secondary"
                                       title="Open the Scheduled Broadcast form pre-filled for a future formup time">
                                        <i class="fas fa-clock"></i> Schedule
                                    </a>
                                    {{-- Details: deep link into the source plugin (Structure Manager / Mining Manager). --}}
                                    @if($detailsHref)
                                        <a href="{{ $detailsHref }}" target="_blank"
                                           class="btn btn-sm btn-pings-secondary"
                                           title="{{ $detailsTitle }}">
                                            <i class="fas fa-info-circle"></i> Details
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</div>
@stop

@push('javascript')
<script src="{{ asset('vendor/discordpings/js/vendor/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/discordpings/js/vendor/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('vendor/discordpings/js/eve-time.js') }}?v=1" defer></script>
<script>
$(document).ready(function() {
    // DataTable — default sort by time ascending (column 0).
    if ($('#opportunitiesTable').length) {
        $('#opportunitiesTable').DataTable({
            pageLength: 25,
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [5, 6, 7] }
            ],
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Filter ops...'
            }
        });
    }

    if ($('#requestsTable').length) {
        $('#requestsTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [3, 4, 5] }
            ],
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Filter requests...',
                lengthMenu: 'Show _MENU_ requests',
                emptyTable: @json('No member requests in the last ' . $requestHours . ' hours.')
            },
            // Only the rows on the page are in the document, so each page is brought up to date as it draws.
            drawCallback: function () {
                if (window.EveTime) {
                    window.EveTime.convert(this.api().table().node());
                }
                updateAgo();
            }
        });
    }

    function updateAgo() {
        var now = Date.now();
        $('.ops-ago').each(function () {
            var sent = Date.parse($(this).data('sent-at'));
            if (isNaN(sent)) {
                $(this).text('');
                return;
            }
            var minutes = Math.max(0, Math.floor((now - sent) / 60000));
            if (minutes < 1) {
                $(this).text('just now');
            } else if (minutes < 60) {
                $(this).text(minutes + 'm ago');
            } else {
                $(this).text(Math.floor(minutes / 60) + 'h ' + (minutes % 60) + 'm ago');
            }
        });
    }

    // Live countdown updates every 30 seconds.
    function updateCountdowns() {
        var now = Date.now();
        $('.ops-countdown').each(function() {
            var iso = $(this).data('eve-time');
            if (!iso) {
                $(this).text('—');
                return;
            }
            var t = Date.parse(iso);
            if (isNaN(t)) {
                $(this).text('—');
                return;
            }
            var diffSec = Math.round((t - now) / 1000);
            $(this).removeClass('imminent now elapsed');
            if (diffSec <= -60) {
                var elapsedMin = Math.abs(Math.round(diffSec / 60));
                if (elapsedMin >= 60) {
                    $(this).text('elapsed ' + Math.floor(elapsedMin / 60) + 'h ' + (elapsedMin % 60) + 'm');
                } else {
                    $(this).text('elapsed ' + elapsedMin + 'm');
                }
                $(this).addClass('elapsed');
            } else if (diffSec <= 60) {
                $(this).text('NOW');
                $(this).addClass('now');
            } else {
                var totalMin = Math.round(diffSec / 60);
                var h = Math.floor(totalMin / 60);
                var m = totalMin % 60;
                var d = Math.floor(h / 24);
                var hh = h % 24;
                var text;
                if (d > 0) {
                    text = 'in ' + d + 'd ' + hh + 'h';
                } else if (h > 0) {
                    text = 'in ' + h + 'h ' + m + 'm';
                } else {
                    text = 'in ' + m + 'm';
                }
                $(this).text(text);
                if (diffSec <= 60 * 60) {
                    $(this).addClass('imminent');
                }
            }
        });
    }
    updateCountdowns();
    setInterval(updateCountdowns, 30 * 1000);
    setInterval(updateAgo, 30 * 1000);
});
</script>
@endpush
