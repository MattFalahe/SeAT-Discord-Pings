@extends('web::layouts.grids.12')

@section('title', 'Edit Scheduled Ping')
@section('page_header', 'Edit Scheduled Broadcast')

@push('head')
<style>
    .time-display { font-size: 0.9em; color: #6c757d; }
    .eve-time { font-weight: bold; color: #007bff; }
    .local-time { font-weight: bold; color: #28a745; }
    .timezone-selector { margin-top: 10px; }
    .time-confirmation { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; margin-top: 10px; }
    .time-confirmation.repeat { background-color: #fff3cd; border-color: #ffc107; }
</style>
@endpush

@section('full')
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Schedule Settings</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Scheduled Date/Time (EVE Time / UTC) <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" id="scheduled_at_eve" class="form-control" required
                                   value="{{ old('scheduled_at', $ping->scheduled_at->utc()->format('Y-m-d\TH:i')) }}">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Enter the time in EVE Time (UTC)
                            </small>

                            <div class="timezone-selector">
                                <label>Your Timezone:</label>
                                <select id="timezone-offset" class="form-control form-control-sm">
                                    <option value="-12">UTC-12 (Baker Island)</option>
                                    <option value="-11">UTC-11 (American Samoa)</option>
                                    <option value="-10">UTC-10 (Hawaii)</option>
                                    <option value="-9">UTC-9 (Alaska)</option>
                                    <option value="-8">UTC-8 (PST - Los Angeles)</option>
                                    <option value="-7">UTC-7 (MST - Denver)</option>
                                    <option value="-6">UTC-6 (CST - Chicago)</option>
                                    <option value="-5">UTC-5 (EST - New York)</option>
                                    <option value="-4">UTC-4 (Atlantic)</option>
                                    <option value="-3">UTC-3 (Brazil)</option>
                                    <option value="-2">UTC-2 (Mid-Atlantic)</option>
                                    <option value="-1">UTC-1 (Azores)</option>
                                    <option value="0">UTC+0 (London/EVE Time)</option>
                                    <option value="1">UTC+1 (Berlin/Paris)</option>
                                    <option value="2">UTC+2 (Cairo/Athens)</option>
                                    <option value="3">UTC+3 (Moscow/Istanbul)</option>
                                    <option value="4">UTC+4 (Dubai)</option>
                                    <option value="5">UTC+5 (Pakistan)</option>
                                    <option value="5.5">UTC+5:30 (India)</option>
                                    <option value="6">UTC+6 (Kazakhstan)</option>
                                    <option value="7">UTC+7 (Bangkok)</option>
                                    <option value="8">UTC+8 (Singapore/Beijing/Perth)</option>
                                    <option value="9">UTC+9 (Tokyo/Seoul)</option>
                                    <option value="9.5">UTC+9:30 (Adelaide)</option>
                                    <option value="10">UTC+10 (Sydney)</option>
                                    <option value="11">UTC+11 (Solomon Islands)</option>
                                    <option value="12">UTC+12 (New Zealand)</option>
                                </select>
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
                            <label>Repeat Until (EVE Time / UTC)</label>
                            <input type="datetime-local" name="repeat_until" id="repeat_until_eve" class="form-control"
                                   value="{{ old('repeat_until', $ping->repeat_until ? $ping->repeat_until->utc()->format('Y-m-d\TH:i') : '') }}">
                            <small class="form-text text-muted">Leave empty for indefinite repeat</small>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Optional Fields</h3>
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
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="{{ route('discordpings.scheduled.calendar') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    function detectUserTimezone() {
        const now = new Date();
        const offsetHours = -now.getTimezoneOffset() / 60;
        $('#timezone-offset').val(offsetHours.toString());
        if ($('#timezone-offset').val() === null) {
            let closest = 0, minDiff = 24;
            $('#timezone-offset option').each(function() {
                const diff = Math.abs(parseFloat($(this).val()) - offsetHours);
                if (diff < minDiff) { minDiff = diff; closest = parseFloat($(this).val()); }
            });
            $('#timezone-offset').val(closest.toString());
        }
    }

    function formatDateTimeUTC(date) {
        return date.getUTCFullYear() + '-' +
            String(date.getUTCMonth() + 1).padStart(2,'0') + '-' +
            String(date.getUTCDate()).padStart(2,'0') + ' ' +
            String(date.getUTCHours()).padStart(2,'0') + ':' +
            String(date.getUTCMinutes()).padStart(2,'0');
    }

    function parseAsUTC(val) {
        if (!val) return null;
        const [datePart, timePart] = val.split('T');
        const [y, m, d] = datePart.split('-').map(Number);
        const [h, min] = timePart.split(':').map(Number);
        return new Date(Date.UTC(y, m - 1, d, h, min, 0, 0));
    }

    function updateTimeDisplay() {
        const eveDate = parseAsUTC($('#scheduled_at_eve').val());
        if (!eveDate || isNaN(eveDate)) { $('#eve-time-display, #local-time-display').text('--'); return; }
        $('#eve-time-display').text(formatDateTimeUTC(eveDate) + ' EVE');
        const offsetHours = parseFloat($('#timezone-offset').val());
        const localDate = new Date(eveDate.getTime() + offsetHours * 3600000);
        const offsetStr = offsetHours >= 0 ? 'UTC+' + offsetHours : 'UTC' + offsetHours;
        $('#local-time-display').text(formatDateTimeUTC(localDate) + ' (' + offsetStr + ')');
    }

    function updateRepeatDisplay() {
        const eveDate = parseAsUTC($('#repeat_until_eve').val());
        if (!eveDate || isNaN(eveDate)) { $('#repeat-time-confirmation').hide(); return; }
        $('#repeat-time-confirmation').show();
        $('#repeat-eve-display').text(formatDateTimeUTC(eveDate) + ' EVE');
        const offsetHours = parseFloat($('#timezone-offset').val());
        const localDate = new Date(eveDate.getTime() + offsetHours * 3600000);
        const offsetStr = offsetHours >= 0 ? 'UTC+' + offsetHours : 'UTC' + offsetHours;
        $('#repeat-local-display').text(formatDateTimeUTC(localDate) + ' (' + offsetStr + ')');
    }

    $('#scheduled_at_eve, #repeat_until_eve').on('change input', function() {
        $(this).attr('id') === 'scheduled_at_eve' ? updateTimeDisplay() : updateRepeatDisplay();
    });
    $('#timezone-offset').on('change', function() { updateTimeDisplay(); updateRepeatDisplay(); });

    detectUserTimezone();
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

    // Form submission — inject UTC hidden fields
    $('#scheduledPingForm').on('submit', function(e) {
        $('input[name="scheduled_at_utc"], input[name="repeat_until_utc"]').remove();
        const schedUTC = parseAsUTC($('#scheduled_at_eve').val());
        if (schedUTC) $('<input>').attr({type:'hidden', name:'scheduled_at_utc', value: schedUTC.toISOString()}).appendTo($(this));
        const repeatUTC = parseAsUTC($('#repeat_until_eve').val());
        if (repeatUTC) $('<input>').attr({type:'hidden', name:'repeat_until_utc', value: repeatUTC.toISOString()}).appendTo($(this));
    });
});
</script>
@endpush
