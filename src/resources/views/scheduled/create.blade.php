@extends('web::layouts.grids.12')

@section('title', 'Schedule Discord Ping')
@section('page_header', 'Schedule Discord Ping')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=2">
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

        @if(! empty($tacticalEventId) && ! empty($tacticalEvent))
            <input type="hidden" name="tactical_event_id" value="{{ $tacticalEventId }}">
            <div class="alert-pings-styled alert-pings-info" style="margin-bottom: 15px;">
                <i class="fas fa-satellite-dish"></i>
                <strong>Form-up for tactical op:</strong>
                {{ $tacticalEvent->title }}
                @if($tacticalEvent->system_name)
                    in <strong>{{ $tacticalEvent->system_name }}</strong>
                @endif
                @if($tacticalEvent->eve_time)
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
                            <label>Webhook <span class="text-danger">*</span></label>
                            <select name="webhook_id" class="form-control" id="webhookSelect" required>
                                <option value="">Select Webhook...</option>
                                @foreach($webhooks as $webhook)
                                    <option value="{{ $webhook->id }}" data-color="{{ $webhook->embed_color }}">
                                        {{ $webhook->name }}
                                        @if($webhook->channel_type)
                                            ({{ $webhook->channel_type }})
                                        @endif
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
                                <option value="">One-time (No Repeat)</option>
                                <option value="hourly">Hourly</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
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

                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required
                              maxlength="2000">{{ old('message', $prefill['message'] ?? '') }}</textarea>
                    <small class="form-text text-muted">
                        <span id="charCount">0</span>/2000 characters
                    </small>
                </div>
                
                {{-- Broadcast Type Selection --}}
                <div class="form-group">
                    <label>Broadcast Type</label>
                    <select name="embed_type" class="form-control" id="embedType">
                        @foreach(['fleet' => '📢 Fleet Broadcast', 'announcement' => '📣 Announcement', 'message' => '💬 Message', 'prepping' => '‼️ PREPING ‼️'] as $embedVal => $embedLabel)
                            <option value="{{ $embedVal }}" {{ old('embed_type', $prefill['embed_type'] ?? 'fleet') === $embedVal ? 'selected' : '' }}>{{ $embedLabel }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Choose the type of broadcast to display in Discord
                    </small>
                </div>

                <div class="form-group">
                    <label>Mentions</label>
                    <select name="mention_type" class="form-control" id="mentionType">
                        <option value="none">No Mention</option>
                        <option value="everyone">@everyone</option>
                        <option value="here">@here</option>
                        <option value="role">Discord Role</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>

                {{-- Role Mention Dropdown (hidden by default) --}}
                <div class="form-group" id="roleMentionDiv" style="display: none;">
                    <label>Select Role to Mention</label>
                    <select name="role_mention" class="form-control">
                        <option value="">Select Role...</option>
                        @foreach($roles ?? [] as $role)
                            <option value="{{ $role->id }}">
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Custom Mention Input (hidden by default) --}}
                <div class="form-group" id="customMentionDiv" style="display: none;">
                    <label>Custom Mention</label>
                    <input type="text" name="custom_mention" class="form-control" 
                           placeholder="@username or custom mention">
                </div>

                {{-- Channel Link Dropdown --}}
                <div class="form-group">
                    <label>Discord Channel (Optional)</label>
                    <select name="channel_link" class="form-control" id="channelSelect">
                        <option value="">No Discord Channel...</option>
                        @foreach($channels ?? [] as $channel)
                            <option value="{{ $channel->id }}">
                                #{{ $channel->name }} 
                                <small class="text-muted">({{ ucfirst($channel->channel_type) }})</small>
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i> Select a pre-configured Discord channel. 
                        <strong>If you select a channel here, leave the Comms field empty</strong> to avoid duplicate entries.
                    </small>
                </div>

                <div class="form-group">
                    <label>Embed Color</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text p-1">
                                <input type="color" id="colorPicker" value="#5865F2"
                                       style="width:34px;height:34px;padding:2px;border:none;cursor:pointer;background:none;">
                            </span>
                        </div>
                        <input type="text" name="embed_color" class="form-control"
                               id="embedColor" value="#5865F2" pattern="^#[0-9A-Fa-f]{6}$" placeholder="#RRGGBB">
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
                                   value="{{ old('fc_name', $prefill['fc_name'] ?? '') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Formup Location</label>
                            <div class="input-group">
                                <input type="text" name="formup_location" id="formupLocation" class="form-control"
                                       placeholder="e.g., Jita 4-4" value="{{ old('formup_location', $prefill['formup_location'] ?? '') }}">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                                            data-toggle="dropdown">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <h6 class="dropdown-header">Quick Select Staging</h6>
                                        @forelse($stagings ?? [] as $staging)
                                            <a class="dropdown-item staging-select" href="#" 
                                               data-location="{{ $staging->getFullLocationString() }}">
                                                {{ $staging->name }}
                                                @if($staging->is_default)
                                                    <span class="badge badge-primary ml-1">Default</span>
                                                @endif
                                                <br>
                                                <small class="text-muted">{{ $staging->getFullLocationString() }}</small>
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
                                    <option value="{{ $pap->name }}">{{ $pap->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-headset"></i> Comms</label>
                            <input type="text" name="comms" class="form-control" id="commsField"
                                   placeholder="e.g., Mumble Channel 3 or TeamSpeak Room 5"
                                   value="{{ old('comms') }}">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> For voice comms or custom channels. 
                                <strong class="text-warning">Leave empty if Discord Channel is selected above</strong>.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-rocket"></i> Doctrine</label>
                    @if(($hasFittingPlugin ?? false) && ($doctrines ?? collect())->count() > 0)
                        {{-- Show dropdown if seat-fitting is installed --}}
                        <div class="input-group">
                            <select name="doctrine_id" id="doctrineSelect" class="form-control">
                                <option value="">Select Doctrine...</option>
                                @foreach($doctrines as $doctrine)
                                    <option value="{{ $doctrine->id }}">
                                        {{ $doctrine->name }}
                                    </option>
                                @endforeach
                                <option value="custom">-- Manual Entry --</option>
                            </select>
                        </div>
                        <input type="text" name="doctrine" id="doctrineManual" class="form-control mt-2" 
                               placeholder="Or type doctrine manually" 
                               value="{{ old('doctrine') }}"
                               style="display: none;">
                    @else
                        {{-- Show text input if seat-fitting is not installed --}}
                        <input type="text" name="doctrine" class="form-control" 
                               placeholder="Doctrine name" 
                               value="{{ old('doctrine') }}">
                    @endif
                </div>
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
    
    // Character counter
    $('textarea[name="message"]').on('input', function() {
        $('#charCount').text($(this).val().length);
    });
    
    // Load saved form data from localStorage if coming from send page
    const savedData = localStorage.getItem('pingFormData');
    if (savedData) {
        const formData = JSON.parse(savedData);
        formData.forEach(function(field) {
            if (field.name !== '_token' && field.name !== 'webhook_id') {
                $(`[name="${field.name}"]`).val(field.value);
            }
        });
        localStorage.removeItem('pingFormData');
        
        // Trigger character count update
        $('textarea[name="message"]').trigger('input');
    }

    // Mention type change
    $('#mentionType').change(function() {
        const val = $(this).val();
        $('#roleMentionDiv, #customMentionDiv').hide();
        
        if (val === 'role') {
            $('#roleMentionDiv').show();
        } else if (val === 'custom') {
            $('#customMentionDiv').show();
        }
    });

    // Staging selection
    $('.staging-select').click(function(e) {
        e.preventDefault();
        const location = $(this).data('location');
        $('#formupLocation').val(location);
    });

    // Channel/Comms conflict handling
    $('#channelSelect').change(function() {
        if ($(this).val()) {
            $('#commsField').attr('placeholder', 'Leave empty - Discord channel already selected');
            $('#commsField').parent().find('.text-warning').addClass('text-danger font-weight-bold');
            
            if ($('#commsField').val()) {
                if (confirm('You have selected a Discord channel. Clear the Comms field to avoid duplication?')) {
                    $('#commsField').val('');
                }
            }
        } else {
            $('#commsField').attr('placeholder', 'e.g., Mumble Channel 3 or TeamSpeak Room 5');
            $('#commsField').parent().find('.text-warning').removeClass('text-danger font-weight-bold');
        }
    });
    
    $('#commsField').on('input', function() {
        if ($('#channelSelect').val() && $(this).val()) {
            $(this).parent().find('small').html(
                '<i class="fas fa-exclamation-triangle text-warning"></i> ' +
                '<strong class="text-danger">Warning: Discord channel already selected. This may create duplicate entries!</strong>'
            );
        } else if (!$('#channelSelect').val()) {
            $(this).parent().find('small').html(
                '<i class="fas fa-info-circle"></i> For voice comms or custom channels. ' +
                '<strong class="text-warning">Leave empty if Discord Channel is selected above</strong>.'
            );
        }
    });

    // Color picker sync
    $('#colorPicker').on('input change', function() {
        $('#embedColor').val($(this).val());
    });

    $('#embedColor').on('input', function() {
        const val = $(this).val();
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            $('#colorPicker').val(val);
        }
    });

    $('#webhookSelect').change(function() {
        const color = $(this).find(':selected').data('color');
        if (color) {
            $('#embedColor').val(color);
            $('#colorPicker').val(color);
        }
    });

    @if(($hasFittingPlugin ?? false) && ($doctrines ?? collect())->count() > 0)
    // Handle doctrine dropdown
    $('#doctrineSelect').change(function() {
        if ($(this).val() === 'custom') {
            $('#doctrineManual').show();
            $(this).prop('name', '');
            $('#doctrineManual').prop('name', 'doctrine');
        } else {
            $('#doctrineManual').hide().val('');
            $(this).prop('name', 'doctrine_id');
            $('#doctrineManual').prop('name', '');
        }
    });
    @endif
    
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
