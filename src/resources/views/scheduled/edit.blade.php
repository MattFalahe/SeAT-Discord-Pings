@extends('web::layouts.grids.12')

@section('title', 'Edit Scheduled Ping')
@section('page_header', 'Edit Scheduled Broadcast')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=4">
<style>
    /* Page-specific only — chrome lives in canonical CSS */
    .time-display { font-size: 0.9em; color: var(--pings-text-muted); }
    .eve-time { font-weight: bold; color: var(--pings-info); }
    .local-time { font-weight: bold; color: var(--pings-success); }
    .timezone-selector { margin-top: 10px; }
    .time-confirmation {
        background-color: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--pings-border);
        border-radius: 4px;
        padding: 10px;
        margin-top: 10px;
        color: var(--pings-text-light);
    }
    .time-confirmation.repeat { background-color: rgba(255, 193, 7, 0.1); border-color: var(--pings-warning); }
</style>
@endpush

@section('full')
<div class="discord-pings-wrapper">
    @if ($ping->repeat_interval && $ping->times_sent > 0)
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Recurring Series:</strong>
            This ping has already fired <strong>{{ $ping->times_sent }} time(s)</strong>.
            Editing will apply to <strong>all future occurrences</strong> starting from the new scheduled time you set below.
            Past occurrences are preserved in broadcast history and will not be affected.
        </div>
    @elseif ($ping->is_active == false)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Note:</strong> This ping has already been sent (inactive). Saving will reschedule it as a new active ping.
        </div>
    @endif

    @if (! empty($answering))
        <div class="alert-pings-styled alert-pings-info" style="margin-bottom: 15px;">
            <i class="fas fa-hand-paper"></i>
            This broadcast answers {{ $answering->fields['button'] ?? 'a ping request' }} from
            <strong>{{ $answering->fields['main'] ?? $answering->user_name }}</strong>.
            The request counts as answered when it goes out.
        </div>
    @endif

    <form method="POST" action="{{ route('discordpings.scheduled.update', $ping->id) }}" id="scheduledPingForm">
        @csrf
        @method('PUT')

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
                                   value="{{ old('scheduled_at', $ping->scheduled_at->utc()->format('Y-m-d\TH:i')) }}">
                            <small class="form-text text-muted" id="schedModeHelp">
                                <i class="fas fa-info-circle"></i> Enter the time in EVE Time (UTC). The stored time is always EVE; switching to "My local time" only changes how you enter it.
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
                                <option value="" {{ old('repeat_interval', $ping->repeat_interval) == '' ? 'selected' : '' }}>One-time (No Repeat)</option>
                                <option value="hourly"  {{ old('repeat_interval', $ping->repeat_interval) == 'hourly'  ? 'selected' : '' }}>Hourly</option>
                                <option value="daily"   {{ old('repeat_interval', $ping->repeat_interval) == 'daily'   ? 'selected' : '' }}>Daily</option>
                                <option value="weekly"  {{ old('repeat_interval', $ping->repeat_interval) == 'weekly'  ? 'selected' : '' }}>Weekly</option>
                                <option value="monthly" {{ old('repeat_interval', $ping->repeat_interval) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="repeatTimeLabel">
                                Repeat Until <span class="text-muted" id="repeatModeHint">(EVE Time / UTC)</span>
                            </label>
                            <input type="datetime-local" name="repeat_until" id="repeat_until_eve" class="form-control"
                                   value="{{ old('repeat_until', $ping->repeat_until ? $ping->repeat_until->utc()->format('Y-m-d\TH:i') : '') }}">
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
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="{{ route('discordpings.scheduled.calendar') }}" class="btn btn-pings-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@stop

@push('javascript')
@php
    // A form that came back with errors keeps what was typed, otherwise the broadcast as saved.
    $oldInput = collect(old())->except(['_token', '_method'])->all();
    $formValues = $oldInput !== []
        ? $oldInput
        : array_merge($ping->fields ?? [], ['webhook_id' => $ping->webhook_id, 'message' => $ping->message]);
@endphp
@include('discordpings::partials.broadcast-scripts', ['suggestions' => $suggestions ?? []])
<script>
$(document).ready(function() {
    // ============================================================
    // Time input mode: EVE/UTC (default) vs Local.
    // See scheduled/create.blade.php for the full rationale comment.
    // The edit form intentionally defaults to EVE — the stored
    // scheduled_at is UTC, the input is pre-filled with that UTC
    // string, so EVE mode is the consistent interpretation.
    // Switching to Local mode reinterprets the (now stale) input
    // value as local — operator should retype if they want that.
    // ============================================================

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
            return date.toString();
        }
    }

    function parseAsUTC(value) {
        if (!value) return null;
        var parts = value.split('T');
        if (parts.length !== 2) return null;
        var d = parts[0].split('-').map(Number);
        var t = parts[1].split(':').map(Number);
        return new Date(Date.UTC(d[0], d[1] - 1, d[2], t[0] || 0, t[1] || 0, 0, 0));
    }

    function parseInputByMode(value) {
        if (!value) return null;
        if (getInputMode() === 'local') {
            var d = new Date(value);
            return isNaN(d.getTime()) ? null : d;
        }
        return parseAsUTC(value);
    }

    function applyModeLabels() {
        var mode = getInputMode();
        if (mode === 'local') {
            $('#schedModeHint').text('(your local time)');
            $('#schedModeHelp').html('<i class="fas fa-info-circle"></i> Enter the time in <strong>your local time</strong> &mdash; we convert to EVE on submit. The stored time is always EVE.');
            $('#repeatModeHint').text('(your local time)');
        } else {
            $('#schedModeHint').text('(EVE Time / UTC)');
            $('#schedModeHelp').html('<i class="fas fa-info-circle"></i> Enter the time in EVE Time (UTC). The stored time is always EVE; switching to "My local time" only changes how you enter it.');
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

    $('#scheduled_at_eve, #repeat_until_eve').on('change input', function() {
        $(this).attr('id') === 'scheduled_at_eve' ? updateTimeDisplay() : updateRepeatDisplay();
    });
    $('input[name="time_input_mode"]').on('change', function() {
        applyModeLabels();
        updateTimeDisplay();
        updateRepeatDisplay();
    });

    applyModeLabels();
    updateTimeDisplay();
    if ($('#repeat_until_eve').val()) updateRepeatDisplay();

    // fill() sets the saved colour after the webhook, so the webhook's own colour doesn't cover it.
    BroadcastForm.init('#scheduledPingForm', @json($formValues));

    // Form submission — emit canonical UTC ISO via the hidden
    // fields. Mode-aware: EVE input passed through as-is, Local
    // input converted to UTC. Server always receives UTC.
    $('#scheduledPingForm').on('submit', function() {
        $('input[name="scheduled_at_utc"], input[name="repeat_until_utc"]').remove();

        var schedUTC = parseInputByMode($('#scheduled_at_eve').val());
        if (schedUTC) {
            $('<input>').attr({
                type:  'hidden',
                name:  'scheduled_at_utc',
                value: schedUTC.toISOString(),
            }).appendTo($(this));
        }

        var repeatUTC = parseInputByMode($('#repeat_until_eve').val());
        if (repeatUTC) {
            $('<input>').attr({
                type:  'hidden',
                name:  'repeat_until_utc',
                value: repeatUTC.toISOString(),
            }).appendTo($(this));
        }
    });
});
</script>
@endpush
