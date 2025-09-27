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
</style>
@endpush

@section('full')
    <form method="POST" action="{{ route('discordpings.scheduled.store') }}">
        @csrf
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Schedule Settings</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Scheduled Date/Time (Your Local Time) <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control" required
                                   min="{{ now()->format('Y-m-d\TH:i') }}"
                                   value="{{ old('scheduled_at', now()->addHour()->format('Y-m-d\TH:i')) }}">
                            <small class="form-text">
                                <div class="time-display">
                                    <i class="fas fa-info-circle"></i> You are entering time in your local timezone<br>
                                    <strong>EVE Time:</strong> <span class="eve-time" id="eve-time-display">Calculating...</span><br>
                                    <strong>Your Time:</strong> <span id="local-time-display">Calculating...</span>
                                </div>
                            </small>
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
                            <label>Repeat Until (Your Local Time)</label>
                            <input type="datetime-local" name="repeat_until" id="repeat_until" class="form-control"
                                   min="{{ now()->addDay()->format('Y-m-d\TH:i') }}">
                            <small class="form-text text-muted">
                                Leave empty for indefinite repeat<br>
                                <span id="repeat-eve-time" class="eve-time"></span>
                            </small>
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

    // Corrected EVE Time handling
    function updateTimeDisplays() {
        const input = $('#scheduled_at').val();
        if (!input) return;
        
        // User enters local time
        const localDate = new Date(input);
        
        // Convert to UTC (EVE Time) - this is the correct way
        const eveDate = new Date(localDate.toUTCString());
        
        // Format displays
        const eveTimeString = eveDate.getUTCFullYear() + '-' +
                              String(eveDate.getUTCMonth() + 1).padStart(2, '0') + '-' +
                              String(eveDate.getUTCDate()).padStart(2, '0') + ' ' +
                              String(eveDate.getUTCHours()).padStart(2, '0') + ':' +
                              String(eveDate.getUTCMinutes()).padStart(2, '0') + ':' +
                              String(eveDate.getUTCSeconds()).padStart(2, '0');
        
        $('#eve-time-display').text(eveTimeString + ' EVE');
        $('#local-time-display').text(localDate.toLocaleString() + ' (your timezone)');
    }
    
    function updateRepeatTimeDisplay() {
        const input = $('#repeat_until').val();
        if (!input) {
            $('#repeat-eve-time').text('');
            return;
        }
        
        const localDate = new Date(input);
        const eveTimeString = localDate.getUTCFullYear() + '-' +
                              String(localDate.getUTCMonth() + 1).padStart(2, '0') + '-' +
                              String(localDate.getUTCDate()).padStart(2, '0') + ' ' +
                              String(localDate.getUTCHours()).padStart(2, '0') + ':' +
                              String(localDate.getUTCMinutes()).padStart(2, '0') + ':' +
                              String(localDate.getUTCSeconds()).padStart(2, '0');
        
        $('#repeat-eve-time').html('EVE Time: ' + eveTimeString + ' EVE');
    }

    
    // Convert local time input to EVE time (UTC) on form submission
    $('form').submit(function(e) {
        const scheduledInput = $('#scheduled_at');
        const repeatInput = $('#repeat_until');
        
        if (scheduledInput.val()) {
            // User entered local time, convert to UTC for backend
            const localDate = new Date(scheduledInput.val());
            const utcString = localDate.toISOString().slice(0, 16);
            
            // Create a hidden input with the UTC time
            $('<input>').attr({
                type: 'hidden',
                name: 'scheduled_at_utc',
                value: utcString
            }).appendTo($(this));
            
            // Keep original for display
            scheduledInput.attr('name', 'scheduled_at_local');
        }
        
        if (repeatInput.val()) {
            const localDate = new Date(repeatInput.val());
            const utcString = localDate.toISOString().slice(0, 16);
            
            $('<input>').attr({
                type: 'hidden',
                name: 'repeat_until_utc',
                value: utcString
            }).appendTo($(this));
            
            repeatInput.attr('name', 'repeat_until_local');
        }
    });
    
    $('#scheduled_at').on('change input', updateTimeDisplays);
    $('#repeat_until').on('change input', updateRepeatTimeDisplay);
    
    // Initial update
    updateTimeDisplays();
});
</script>
@endpush
