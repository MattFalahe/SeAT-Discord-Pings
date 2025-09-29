@extends('web::layouts.grids.12')

@section('title', 'Schedule Discord Ping')
@section('page_header', 'Schedule Discord Ping')

@push('head')
<style>
    .color-preview {
        width: 30px;
        height: 30px;
        border-radius: 4px;
        display: inline-block;
        vertical-align: middle;
        margin-left: 10px;
        border: 1px solid #dee2e6;
    }
    .time-display {
        font-size: 0.9em;
        color: #6c757d;
    }
    .eve-time {
        font-weight: bold;
        color: #007bff;
    }
    .local-time {
        font-weight: bold;
        color: #28a745;
    }
    .timezone-selector {
        margin-top: 10px;
    }
    .time-confirmation {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 10px;
        margin-top: 10px;
    }
    .time-confirmation.repeat {
        background-color: #fff3cd;
        border-color: #ffc107;
    }
</style>
@endpush

@section('full')
    <form method="POST" action="{{ route('discordpings.scheduled.store') }}" id="scheduledPingForm">
        @csrf
        
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
                                   min="{{ now()->utc()->format('Y-m-d\TH:i') }}"
                                   value="{{ old('scheduled_at', now()->utc()->addHour()->format('Y-m-d\TH:i')) }}">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Enter the time in EVE Time (UTC)
                            </small>
                            
                            {{-- Timezone Selector --}}
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
                            <label>Repeat Until (EVE Time / UTC)</label>
                            <input type="datetime-local" name="repeat_until" id="repeat_until_eve" class="form-control"
                                   min="{{ now()->utc()->addDay()->format('Y-m-d\TH:i') }}">
                            <small class="form-text text-muted">
                                Leave empty for indefinite repeat
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
                              maxlength="2000">{{ old('message') }}</textarea>
                    <small class="form-text text-muted">
                        <span id="charCount">0</span>/2000 characters
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
                        <input type="text" name="embed_color" class="form-control" 
                               id="embedColor" value="#5865F2" pattern="^#[0-9A-Fa-f]{6}$">
                        <div class="color-preview" id="colorPreview"></div>
                    </div>
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
                                   value="{{ old('fc_name') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Formup Location</label>
                            <div class="input-group">
                                <input type="text" name="formup_location" id="formupLocation" class="form-control" 
                                       placeholder="e.g., Jita 4-4" value="{{ old('formup_location') }}">
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
                                <option value="Strategic">Strategic</option>
                                <option value="Peacetime">Peacetime</option>
                                <option value="CTA">CTA</option>
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
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-clock"></i> Schedule Ping
                </button>
                <a href="{{ route('discordpings.scheduled') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    // Auto-detect user's timezone offset
    function detectUserTimezone() {
        const now = new Date();
        const offsetMinutes = -now.getTimezoneOffset();
        const offsetHours = offsetMinutes / 60;
        
        // Try to select the closest offset in the dropdown
        $('#timezone-offset').val(offsetHours.toString());
        
        // If exact match not found, find closest
        if ($('#timezone-offset').val() === null) {
            let closest = 0;
            let minDiff = 24;
            $('#timezone-offset option').each(function() {
                const val = parseFloat($(this).val());
                const diff = Math.abs(val - offsetHours);
                if (diff < minDiff) {
                    minDiff = diff;
                    closest = val;
                }
            });
            $('#timezone-offset').val(closest.toString());
        }
    }
    
    // Format datetime for display
    function formatDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}`;
    }
    
    // Format UTC datetime for display
    function formatDateTimeUTC(date) {
        const year = date.getUTCFullYear();
        const month = String(date.getUTCMonth() + 1).padStart(2, '0');
        const day = String(date.getUTCDate()).padStart(2, '0');
        const hours = String(date.getUTCHours()).padStart(2, '0');
        const minutes = String(date.getUTCMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}`;
    }
    
    // Parse datetime-local as UTC (not local time)
    function parseAsUTC(datetimeLocalValue) {
        if (!datetimeLocalValue) return null;
        
        // Split the datetime-local value
        const [datePart, timePart] = datetimeLocalValue.split('T');
        const [year, month, day] = datePart.split('-').map(Number);
        const [hours, minutes] = timePart.split(':').map(Number);
        
        // Create date explicitly in UTC
        return new Date(Date.UTC(year, month - 1, day, hours, minutes, 0, 0));
    }
    
    // Update time displays based on EVE time input
    function updateTimeDisplay() {
        const eveTimeInput = $('#scheduled_at_eve').val();
        
        if (!eveTimeInput) {
            $('#eve-time-display').text('--');
            $('#local-time-display').text('--');
            return;
        }
        
        // Parse the input as UTC time directly
        const eveDate = parseAsUTC(eveTimeInput);
        
        if (!eveDate || isNaN(eveDate.getTime())) {
            $('#eve-time-display').text('Invalid date');
            $('#local-time-display').text('Invalid date');
            return;
        }
        
        // Display EVE time (which is what was entered)
        $('#eve-time-display').text(formatDateTimeUTC(eveDate) + ' EVE');
        
        // Calculate local time based on selected timezone
        const offsetHours = parseFloat($('#timezone-offset').val());
        const localDate = new Date(eveDate.getTime() + (offsetHours * 60 * 60 * 1000));
        
        const offsetString = offsetHours >= 0 ? `UTC+${offsetHours}` : `UTC${offsetHours}`;
        $('#local-time-display').text(formatDateTime(localDate) + ' (' + offsetString + ')');
    }
    
    // Update repeat until display
    function updateRepeatDisplay() {
        const repeatInput = $('#repeat_until_eve').val();
        
        if (!repeatInput) {
            $('#repeat-time-confirmation').hide();
            return;
        }
        
        $('#repeat-time-confirmation').show();
        
        // Parse as UTC time
        const eveDate = parseAsUTC(repeatInput);
        
        if (!eveDate || isNaN(eveDate.getTime())) {
            $('#repeat-eve-display').text('Invalid date');
            $('#repeat-local-display').text('Invalid date');
            return;
        }
        
        // Display EVE time
        $('#repeat-eve-display').text(formatDateTimeUTC(eveDate) + ' EVE');
        
        // Calculate local time based on selected timezone
        const offsetHours = parseFloat($('#timezone-offset').val());
        const localDate = new Date(eveDate.getTime() + (offsetHours * 60 * 60 * 1000));
        
        const offsetString = offsetHours >= 0 ? `UTC+${offsetHours}` : `UTC${offsetHours}`;
        $('#repeat-local-display').text(formatDateTime(localDate) + ' (' + offsetString + ')');
    }
    
    // Event listeners for time updates
    $('#scheduled_at_eve').on('change input', updateTimeDisplay);
    $('#repeat_until_eve').on('change input', updateRepeatDisplay);
    $('#timezone-offset').on('change', function() {
        updateTimeDisplay();
        updateRepeatDisplay();
    });
    
    // Initialize timezone detection and time display
    detectUserTimezone();
    updateTimeDisplay();
    
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

    // Color preview
    function updateColorPreview() {
        $('#colorPreview').css('background-color', $('#embedColor').val());
    }
    
    $('#embedColor').on('input', updateColorPreview);
    
    $('#webhookSelect').change(function() {
        const color = $(this).find(':selected').data('color');
        if (color) {
            $('#embedColor').val(color);
            updateColorPreview();
        }
    });
    
    updateColorPreview();

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
    
    // Handle form submission
    $('#scheduledPingForm').on('submit', function(e) {
        const scheduledInput = $('#scheduled_at_eve').val();
        const repeatInput = $('#repeat_until_eve').val();
        
        // Remove any existing hidden inputs
        $('input[name="scheduled_at_utc"]').remove();
        $('input[name="repeat_until_utc"]').remove();
        
        if (scheduledInput) {
            // Parse as UTC and convert to ISO string
            const utcDate = parseAsUTC(scheduledInput);
            if (utcDate) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'scheduled_at_utc',
                    value: utcDate.toISOString()
                }).appendTo($(this));
            }
        }
        
        if (repeatInput) {
            const utcDate = parseAsUTC(repeatInput);
            if (utcDate) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'repeat_until_utc',
                    value: utcDate.toISOString()
                }).appendTo($(this));
            }
        }
    });
});
</script>
@endpush
