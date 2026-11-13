@extends('web::layouts.grids.12')

@section('title', 'Schedule Discord Ping')
@section('page_header', 'Schedule Discord Ping')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=3">
<script src="{{ asset('vendor/discordpings/js/eve-time.js') }}?v=1" defer></script>
<style>
    /* Page-specific only — chrome lives in canonical CSS */
    .time-display {
        font-size: 0.9em;
        color: var(--pings-text-muted);
    }
    .eve-time {
        font-weight: bold;
        color: var(--pings-info);
    }
    .local-time {
        font-weight: bold;
        color: var(--pings-success);
    }
    .timezone-selector {
        margin-top: 10px;
    }
    .time-confirmation {
        background-color: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--pings-border);
        border-radius: 4px;
        padding: 10px;
        margin-top: 10px;
        color: var(--pings-text-light);
    }
    .time-confirmation.repeat {
        background-color: rgba(255, 193, 7, 0.1);
        border-color: var(--pings-warning);
    }
</style>
@endpush

@section('full')
<div class="discord-pings-wrapper">
    <form method="POST" action="{{ route('discordpings.scheduled.store') }}" id="scheduledPingForm">
        @csrf

        @if (! empty($tacticalEventId) && ! empty($tacticalEvent))
            <input type="hidden" name="tactical_event_id" value="{{ $tacticalEventId }}">
            <div class="alert-pings-styled alert-pings-info" style="margin-bottom: 15px;">
                <i class="fas fa-satellite-dish"></i>
                <strong>Form-up for tactical op:</strong>
                {{ $tacticalEvent->title }}
                @if ($tacticalEvent->system_name)
                    in <strong>{{ $tacticalEvent->system_name }}</strong>
                @endif
                @if ($tacticalEvent->eve_time)
                    , timer at
                    <strong>
                        <span class="eve-time" data-eve-time="{{ $tacticalEvent->eve_time->toIso8601String() }}" data-show-local>
                            {{ $tacticalEvent->eve_time->format('Y-m-d H:i') }} EVE
                        </span>
                    </strong>
                @endif
                . The form below has been pre-filled; review and adjust before scheduling.
            </div>
        @endif

        @if (! empty($pingRequest))
            {{-- Checked again when the form comes back, so this only says which request it means. --}}
            <input type="hidden" name="answers_request" value="{{ $pingRequest->id }}">
            <div class="alert-pings-styled alert-pings-info" style="margin-bottom: 15px;">
                <i class="fas fa-hand-paper"></i>
                <strong>Answering:</strong>
                {{ $pingRequest->fields['button'] ?? 'a ping request' }},
                {{ $pingRequest->isEmergencyRequest() ? 'an emergency' : 'a request' }} from
                <strong>{{ $pingRequest->fields['main'] ?? $pingRequest->user_name }}</strong>.
                It counts as answered when this broadcast goes out.
            </div>
        @endif

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock"></i> Schedule Settings
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label id="schedTimeLabel">
                                Scheduled Date/Time <span class="text-muted" id="schedModeHint">(EVE Time / UTC)</span>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" name="scheduled_at" id="scheduled_at_eve" class="form-control" required
                                   min="{{ now()->utc()->format('Y-m-d\TH:i') }}"
                                   value="{{ old('scheduled_at', ($prefill['scheduled_at'] ?? null) ?? now()->utc()->addHour()->format('Y-m-d\TH:i')) }}">
                            <small class="form-text text-muted" id="schedModeHelp">
                                <i class="fas fa-info-circle"></i> Enter the time in EVE Time (UTC)
                            </small>

                            {{-- Time input mode toggle --}}
                            <div class="time-mode-toggle" style="margin-top: 0.75rem;">
                                <label style="font-size: 0.85em; margin-right: 0.5rem;">Enter time in:</label>
                                <div class="btn-group btn-group-toggle btn-group-sm" data-toggle="buttons">
                                    <label class="btn btn-outline-secondary active">
                                        <input type="radio" name="time_input_mode" value="eve" checked> EVE / UTC
                                    </label>
                                    <label class="btn btn-outline-secondary">
                                        <input type="radio" name="time_input_mode" value="local"> My local time
                                    </label>
                                </div>
                                <small class="form-text text-muted" style="margin-top: 0.35rem;">
                                    <i class="fas fa-globe"></i>
                                    Browser timezone: <code id="detected-tz">detecting…</code>
                                </small>
                            </div>

                            {{-- Time Confirmation Box --}}
                            <div class="time-confirmation" id="time-confirmation">
                                <strong>📅 Scheduled Time Confirmation:</strong><br>
                                <span class="eve-time">EVE Time: <span id="eve-time-display">--</span></span><br>
                                <span class="local-time">Your Local Time: <span id="local-time-display">--</span></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Repeat Interval</label>
                            <select name="repeat_interval" class="form-control">
                                <option value="">One-time (No Repeat)</option>
                                <option value="hourly">Hourly</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="repeatTimeLabel">
                                Repeat Until <span class="text-muted" id="repeatModeHint">(EVE Time / UTC)</span>
                            </label>
                            <input type="datetime-local" name="repeat_until" id="repeat_until_eve" class="form-control"
                                   min="{{ now()->utc()->addDay()->format('Y-m-d\TH:i') }}">
                            <small class="form-text text-muted">
                                Leave empty for indefinite repeat. Uses the same input mode as the scheduled time above.
                            </small>
                            <div class="time-confirmation repeat" id="repeat-time-confirmation" style="display: none;">
                                <strong>🔄 Repeat Until:</strong><br>
                                <span class="eve-time">EVE Time: <span id="repeat-eve-display">--</span></span><br>
                                <span class="local-time">Your Local Time: <span id="repeat-local-display">--</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paper-plane"></i> Broadcast
                </h3>
            </div>
            <div class="card-body">
                @include('discordpings::partials.broadcast-main')
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i> Optional Fields
                </h3>
            </div>
            <div class="card-body">
                @include('discordpings::partials.broadcast-optional')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-pings-primary">
                    <i class="fas fa-clock"></i> Schedule Ping
                </button>
                <a href="{{ route('discordpings.scheduled') }}" class="btn btn-pings-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@stop

@push('javascript')
@php
    // A form that came back with errors keeps what was typed. Otherwise it
    // starts from Settings' defaults and whatever the page was opened for.
    $oldInput = collect(old())->except(['_token', '_method'])->all();
    $formValues = $oldInput !== [] ? $oldInput : ($prefill ?? []);
@endphp
@include('discordpings::partials.broadcast-scripts', ['suggestions' => $suggestions ?? []])
<script>
$(document).ready(function() {
    // ============================================================
    // Time input mode: EVE/UTC (default) vs Local
    //
    // The visible datetime-local input is interpreted differently
    // based on the toggle:
    //   - EVE mode: value is UTC ("18:00" means 18:00 EVE)
    //   - Local mode: value is browser-local ("18:00" means 18:00
    //     wherever the browser is)
    //
    // On submit, JS computes the UTC equivalent and posts it via
    // the scheduled_at_utc / repeat_until_utc hidden fields — the
    // server is mode-agnostic and always receives UTC.
    //
    // Local-mode parsing uses the native Date constructor (which
    // treats "YYYY-MM-DDTHH:mm" as browser-local per spec) and the
    // Intl.DateTimeFormat for display — both honour the browser's
    // IANA timezone, so DST is handled correctly all year.
    // ============================================================

    // Surface the detected IANA timezone for transparency.
    try {
        var detectedTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        $('#detected-tz').text(detectedTz || 'unknown');
    } catch (e) {
        $('#detected-tz').text('unknown');
    }

    function getInputMode() {
        var checked = document.querySelector('input[name="time_input_mode"]:checked');
        return checked ? checked.value : 'eve';
    }

    function formatDateTimeUTC(date) {
        return date.getUTCFullYear() + '-' +
            String(date.getUTCMonth() + 1).padStart(2, '0') + '-' +
            String(date.getUTCDate()).padStart(2, '0') + ' ' +
            String(date.getUTCHours()).padStart(2, '0') + ':' +
            String(date.getUTCMinutes()).padStart(2, '0');
    }

    // Browser-locale + browser-TZ format — DST safe.
    function formatLocal(date) {
        try {
            return new Intl.DateTimeFormat(undefined, {
                year:   'numeric',
                month:  '2-digit',
                day:    '2-digit',
                hour:   '2-digit',
                minute: '2-digit',
                timeZoneName: 'short',
            }).format(date);
        } catch (e) {
            // Old browser fallback — show local-tz UTC offset in minutes.
            return date.toString();
        }
    }

    // Parse the input value AS UTC (EVE mode).
    function parseAsUTC(value) {
        if (!value) return null;
        var parts = value.split('T');
        if (parts.length !== 2) return null;
        var d = parts[0].split('-').map(Number);
        var t = parts[1].split(':').map(Number);
        return new Date(Date.UTC(d[0], d[1] - 1, d[2], t[0] || 0, t[1] || 0, 0, 0));
    }

    // Parse the input value according to the active mode.
    // Returns a Date pinned to a UTC instant (which is what the
    // submit handler needs for toISOString()).
    function parseInputByMode(value) {
        if (!value) return null;
        if (getInputMode() === 'local') {
            // Native Date constructor on "YYYY-MM-DDTHH:mm" treats
            // the string as LOCAL time per ECMAScript spec. The
            // resulting Date is still an absolute UTC instant.
            var d = new Date(value);
            return isNaN(d.getTime()) ? null : d;
        }
        return parseAsUTC(value);
    }

    function applyModeLabels() {
        var mode = getInputMode();
        if (mode === 'local') {
            $('#schedModeHint').text('(your local time)');
            $('#schedModeHelp').html('<i class="fas fa-info-circle"></i> Enter the time in <strong>your local time</strong> &mdash; we convert to EVE on submit.');
            $('#repeatModeHint').text('(your local time)');
        } else {
            $('#schedModeHint').text('(EVE Time / UTC)');
            $('#schedModeHelp').html('<i class="fas fa-info-circle"></i> Enter the time in EVE Time (UTC).');
            $('#repeatModeHint').text('(EVE Time / UTC)');
        }
    }

    function updateTimeDisplay() {
        var input = $('#scheduled_at_eve').val();
        if (!input) {
            $('#eve-time-display, #local-time-display').text('--');
            return;
        }
        var utcDate = parseInputByMode(input);
        if (!utcDate || isNaN(utcDate.getTime())) {
            $('#eve-time-display, #local-time-display').text('Invalid date');
            return;
        }
        $('#eve-time-display').text(formatDateTimeUTC(utcDate) + ' EVE');
        $('#local-time-display').text(formatLocal(utcDate));
    }

    function updateRepeatDisplay() {
        var input = $('#repeat_until_eve').val();
        if (!input) {
            $('#repeat-time-confirmation').hide();
            return;
        }
        $('#repeat-time-confirmation').show();
        var utcDate = parseInputByMode(input);
        if (!utcDate || isNaN(utcDate.getTime())) {
            $('#repeat-eve-display, #repeat-local-display').text('Invalid date');
            return;
        }
        $('#repeat-eve-display').text(formatDateTimeUTC(utcDate) + ' EVE');
        $('#repeat-local-display').text(formatLocal(utcDate));
    }

    // Event wiring
    $('#scheduled_at_eve').on('change input', updateTimeDisplay);
    $('#repeat_until_eve').on('change input', updateRepeatDisplay);
    $('input[name="time_input_mode"]').on('change', function() {
        applyModeLabels();
        updateTimeDisplay();
        updateRepeatDisplay();
    });

    applyModeLabels();
    updateTimeDisplay();
    updateRepeatDisplay();

    BroadcastForm.init('#scheduledPingForm', @json($formValues));

    // What Send's Schedule button brought along: every box, as the FC left it.
    // Read once and cleared, and only if it's fresh, so an old one never
    // turns up in a form opened some other way later.
    function carriedFromSend() {
        var saved;

        try {
            saved = localStorage.getItem('pingFormData');
            localStorage.removeItem('pingFormData');
            saved = saved ? JSON.parse(saved) : null;
        } catch (e) {
            return null;
        }

        if (saved && saved.v === 2 && saved.values && typeof saved.values === 'object') {
            return Date.now() - (saved.at || 0) < 10 * 60 * 1000 ? saved.values : null;
        }

        // A Send page loaded before this version stored the boxes as name and value pairs.
        if ($.isArray(saved)) {
            var values = {};

            saved.forEach(function (box) {
                if (!box || typeof box.name !== 'string' || box.name === '_token') {
                    return;
                }

                if (box.name === 'webhook_ids[]') {
                    (values.webhook_ids = values.webhook_ids || []).push(box.value);
                } else {
                    values[box.name] = box.value;
                }
            });

            return values;
        }

        return null;
    }

    var carried = carriedFromSend();
    if (carried) {
        BroadcastForm.fill(carried);
    }

    // Handle form submission — emit canonical UTC ISO via the
    // scheduled_at_utc / repeat_until_utc hidden fields regardless
    // of whether the user entered EVE or local time. The server side
    // never sees the input mode — it always reads UTC.
    $('#scheduledPingForm').on('submit', function() {
        var scheduledInput = $('#scheduled_at_eve').val();
        var repeatInput    = $('#repeat_until_eve').val();

        $('input[name="scheduled_at_utc"]').remove();
        $('input[name="repeat_until_utc"]').remove();

        if (scheduledInput) {
            var utcDate = parseInputByMode(scheduledInput);
            if (utcDate) {
                $('<input>').attr({
                    type:  'hidden',
                    name:  'scheduled_at_utc',
                    value: utcDate.toISOString(),
                }).appendTo($(this));
            }
        }

        if (repeatInput) {
            var utcRepeat = parseInputByMode(repeatInput);
            if (utcRepeat) {
                $('<input>').attr({
                    type:  'hidden',
                    name:  'repeat_until_utc',
                    value: utcRepeat.toISOString(),
                }).appendTo($(this));
            }
        }
    });
});
</script>
@endpush
