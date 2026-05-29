@extends('web::layouts.grids.12')

@section('title', 'Edit Scheduled Ping')
@section('page_header', 'Edit Scheduled Broadcast')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=2">
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
    @if($ping->repeat_interval && $ping->times_sent > 0)
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Recurring Series:</strong>
            This ping has already fired <strong>{{ $ping->times_sent }} time(s)</strong>.
            Editing will apply to <strong>all future occurrences</strong> starting from the new scheduled time you set below.
            Past occurrences are preserved in broadcast history and will not be affected.
        </div>
    @elseif($ping->is_active == false)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Note:</strong> This ping has already been sent (inactive). Saving will reschedule it as a new active ping.
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
                            <label>Webhook <span class="text-danger">*</span></label>
                            <select name="webhook_id" class="form-control" id="webhookSelect" required>
                                <option value="">Select Webhook...</option>
                                @foreach($webhooks as $webhook)
                                    <option value="{{ $webhook->id }}" data-color="{{ $webhook->embed_color }}"
                                        {{ old('webhook_id', $ping->webhook_id) == $webhook->id ? 'selected' : '' }}>
                                        {{ $webhook->name }}
                                        @if($webhook->channel_type) ({{ $webhook->channel_type }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
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
                    </div>
                    <div class="col-md-6">
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

                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required maxlength="2000">{{ old('message', $ping->message) }}</textarea>
                    <small class="form-text text-muted"><span id="charCount">0</span>/2000 characters</small>
                </div>

                <div class="form-group">
                    <label>Broadcast Type</label>
                    <select name="embed_type" class="form-control" id="embedType">
                        @php $currentType = old('embed_type', $ping->fields['embed_type'] ?? 'fleet'); @endphp
                        <option value="fleet"        {{ $currentType == 'fleet'        ? 'selected' : '' }}>📢 Fleet Broadcast</option>
                        <option value="announcement" {{ $currentType == 'announcement' ? 'selected' : '' }}>📣 Announcement</option>
                        <option value="message"      {{ $currentType == 'message'      ? 'selected' : '' }}>💬 Message</option>
                        <option value="prepping"     {{ $currentType == 'prepping'     ? 'selected' : '' }}>‼️ PREPING ‼️</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Mentions</label>
                    <select name="mention_type" class="form-control" id="mentionType">
                        @php $currentMention = old('mention_type', $ping->fields['mention_type'] ?? 'none'); @endphp
                        <option value="none"     {{ $currentMention == 'none'     ? 'selected' : '' }}>No Mention</option>
                        <option value="everyone" {{ $currentMention == 'everyone' ? 'selected' : '' }}>@everyone</option>
                        <option value="here"     {{ $currentMention == 'here'     ? 'selected' : '' }}>@here</option>
                        <option value="role"     {{ $currentMention == 'role'     ? 'selected' : '' }}>Discord Role</option>
                        <option value="custom"   {{ $currentMention == 'custom'   ? 'selected' : '' }}>Custom</option>
                    </select>
                </div>

                <div class="form-group" id="roleMentionDiv" style="{{ ($ping->fields['mention_type'] ?? '') == 'role' ? '' : 'display:none;' }}">
                    <label>Select Role to Mention</label>
                    <select name="role_mention" class="form-control">
                        <option value="">Select Role...</option>
                        @foreach($roles ?? [] as $role)
                            <option value="{{ $role->id }}" {{ old('role_mention', $ping->fields['role_mention'] ?? '') == $role->id ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="customMentionDiv" style="{{ ($ping->fields['mention_type'] ?? '') == 'custom' ? '' : 'display:none;' }}">
                    <label>Custom Mention</label>
                    <input type="text" name="custom_mention" class="form-control"
                           value="{{ old('custom_mention', $ping->fields['custom_mention'] ?? '') }}"
                           placeholder="@username or custom mention">
                </div>

                <div class="form-group">
                    <label>Discord Channel (Optional)</label>
                    <select name="channel_link" class="form-control" id="channelSelect">
                        <option value="">No Discord Channel...</option>
                        @foreach($channels ?? [] as $channel)
                            <option value="{{ $channel->id }}" {{ old('channel_link', $ping->fields['channel_link'] ?? '') == $channel->id ? 'selected' : '' }}>
                                #{{ $channel->name }} <small>({{ ucfirst($channel->channel_type) }})</small>
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Embed Color</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text p-1">
                                <input type="color" id="colorPicker"
                                       value="{{ old('embed_color', $ping->fields['embed_color'] ?? '#5865F2') }}"
                                       style="width:34px;height:34px;padding:2px;border:none;cursor:pointer;background:none;">
                            </span>
                        </div>
                        <input type="text" name="embed_color" class="form-control" id="embedColor"
                               value="{{ old('embed_color', $ping->fields['embed_color'] ?? '#5865F2') }}"
                               pattern="^#[0-9A-Fa-f]{6}$" placeholder="#RRGGBB">
                    </div>
                    <small class="form-text text-muted">Use the color picker or type a hex code directly</small>
                </div>
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i> Optional Fields
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> FC Name</label>
                            <input type="text" name="fc_name" class="form-control"
                                   value="{{ old('fc_name', $ping->fields['fc_name'] ?? '') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Formup Location</label>
                            <div class="input-group">
                                <input type="text" name="formup_location" id="formupLocation" class="form-control"
                                       placeholder="e.g., Jita 4-4"
                                       value="{{ old('formup_location', $ping->fields['formup_location'] ?? '') }}">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <h6 class="dropdown-header">Quick Select Staging</h6>
                                        @forelse($stagings ?? [] as $staging)
                                            <a class="dropdown-item staging-select" href="#"
                                               data-location="{{ $staging->getFullLocationString() }}">
                                                {{ $staging->name }}
                                                @if($staging->is_default)<span class="badge badge-primary ml-1">Default</span>@endif
                                                <br><small class="text-muted">{{ $staging->getFullLocationString() }}</small>
                                            </a>
                                        @empty
                                            <span class="dropdown-item text-muted">No staging locations configured</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> PAP Type</label>
                            <select name="pap_type" class="form-control">
                                <option value="">None</option>
                                @foreach($papTypes as $pap)
                                    <option value="{{ $pap->name }}" {{ old('pap_type', $ping->fields['pap_type'] ?? '') == $pap->name ? 'selected' : '' }}>
                                        {{ $pap->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-headset"></i> Comms</label>
                            <input type="text" name="comms" class="form-control" id="commsField"
                                   placeholder="e.g., Mumble Channel 3"
                                   value="{{ old('comms', $ping->fields['comms'] ?? '') }}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-rocket"></i> Doctrine</label>
                    @if(($hasFittingPlugin ?? false) && ($doctrines ?? collect())->count() > 0)
                        <div class="input-group">
                            <select name="doctrine_id" id="doctrineSelect" class="form-control">
                                <option value="">Select Doctrine...</option>
                                @foreach($doctrines as $doctrine)
                                    <option value="{{ $doctrine->id }}">{{ $doctrine->name }}</option>
                                @endforeach
                                <option value="custom">-- Manual Entry --</option>
                            </select>
                        </div>
                        <input type="text" name="doctrine" id="doctrineManual" class="form-control mt-2"
                               placeholder="Or type doctrine manually"
                               value="{{ old('doctrine', $ping->fields['doctrine'] ?? '') }}"
                               style="display: none;">
                    @else
                        <input type="text" name="doctrine" class="form-control"
                               placeholder="Doctrine name"
                               value="{{ old('doctrine', $ping->fields['doctrine'] ?? '') }}">
                    @endif
                </div>
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

    // Character counter — pre-fill count
    var msgLen = $('textarea[name="message"]').val().length;
    $('#charCount').text(msgLen);
    $('textarea[name="message"]').on('input', function() { $('#charCount').text($(this).val().length); });

    // Mention type
    $('#mentionType').change(function() {
        const val = $(this).val();
        $('#roleMentionDiv, #customMentionDiv').hide();
        if (val === 'role') $('#roleMentionDiv').show();
        else if (val === 'custom') $('#customMentionDiv').show();
    });

    // Staging
    $('.staging-select').click(function(e) {
        e.preventDefault();
        $('#formupLocation').val($(this).data('location'));
    });

    // Color picker sync
    $('#colorPicker').on('input change', function() { $('#embedColor').val($(this).val()); });
    $('#embedColor').on('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test($(this).val())) $('#colorPicker').val($(this).val());
    });
    $('#webhookSelect').change(function() {
        const color = $(this).find(':selected').data('color');
        if (color) { $('#embedColor').val(color); $('#colorPicker').val(color); }
    });

    @if(($hasFittingPlugin ?? false) && ($doctrines ?? collect())->count() > 0)
    $('#doctrineSelect').change(function() {
        if ($(this).val() === 'custom') {
            $('#doctrineManual').show(); $(this).prop('name', ''); $('#doctrineManual').prop('name', 'doctrine');
        } else {
            $('#doctrineManual').hide().val(''); $(this).prop('name', 'doctrine_id'); $('#doctrineManual').prop('name', '');
        }
    });
    // If existing doctrine is manual text, show the manual field
    @if(!empty($ping->fields['doctrine']))
        $('#doctrineManual').show().prop('name', 'doctrine');
        $('#doctrineSelect').prop('name', '');
    @endif
    @endif

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
