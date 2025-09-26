@extends('web::layouts.grids.3-9')

@section('title', 'Discord Pings')
@section('page_header', 'Discord Pings')

@push('head')
<style>
    .template-btn {
        margin-bottom: 5px;
        white-space: normal;
        text-align: left;
    }
    .color-preview {
        width: 30px;
        height: 30px;
        border-radius: 4px;
        display: inline-block;
        vertical-align: middle;
        margin-left: 10px;
        border: 1px solid #dee2e6;
    }
    .recent-ping {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .recent-ping:hover {
        background-color: rgba(0,123,255,0.1);
    }
</style>
@endpush

@section('left')
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-alt"></i> Quick Templates
            </h3>
        </div>
        <div class="card-body p-2">
            @foreach(config('discordpings.default_templates', []) as $key => $template)
                <button class="btn btn-sm btn-block btn-outline-primary template-btn" 
                        data-template="{{ $template }}">
                    {{ ucwords(str_replace('_', ' ', $key)) }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history"></i> Recent Pings
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @forelse($recentPings ?? [] as $ping)
                    <div class="list-group-item recent-ping p-2" 
                         data-message="{{ $ping->message }}"
                         data-fields='@json($ping->fields)'>
                        <small class="text-muted">
                            {{ $ping->created_at->diffForHumans() }}
                        </small>
                        <p class="mb-1 small">{{ Str::limit($ping->message, 50) }}</p>
                    </div>
                @empty
                    <div class="list-group-item">
                        <small class="text-muted">No recent pings</small>
                    </div>
                @endforelse
            </div>
        </div>
        @if(($recentPings ?? collect())->count() > 0)
            <div class="card-footer p-2">
                <a href="{{ route('discordpings.history') }}" class="btn btn-sm btn-block btn-outline-secondary">
                    View All History
                </a>
            </div>
        @endif
    </div>
@stop

@section('right')
    <form method="POST" action="{{ route('discordpings.send.post') }}" id="pingForm">
        @csrf
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paper-plane"></i> Send Ping
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-outline-info" id="multipleToggle">
                        <i class="fas fa-copy"></i> Multiple Webhooks
                    </button>
                </div>
            </div>
            <div class="card-body">
                {{-- Single Webhook Selection --}}
                <div class="form-group" id="singleWebhookDiv">
                    <label>Target Webhook <span class="text-danger">*</span></label>
                    <select name="webhook_id" class="form-control" id="webhookSelect" required>
                        <option value="">Select Channel...</option>
                        @foreach($webhooks ?? [] as $webhook)
                            <option value="{{ $webhook->id }}" data-color="{{ $webhook->embed_color }}">
                                {{ $webhook->name }}
                                @if($webhook->channel_type)
                                    ({{ $webhook->channel_type }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Multiple Webhooks Selection (hidden by default) --}}
                <div class="form-group" id="multipleWebhooksDiv" style="display: none;">
                    <label>Select Target Webhooks <span class="text-danger">*</span></label>
                    @foreach($webhooks ?? [] as $webhook)
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" 
                                   id="webhook_check_{{ $webhook->id }}"
                                   name="webhook_ids[]" 
                                   value="{{ $webhook->id }}">
                            <label class="custom-control-label" for="webhook_check_{{ $webhook->id }}">
                                {{ $webhook->name }}
                                @if($webhook->channel_type)
                                    <small class="text-muted">({{ $webhook->channel_type }})</small>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="3" required 
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
                                @if($role->color)
                                    <span style="color: {{ $role->color }}">●</span>
                                @endif
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
                    <label>Channel Link (Optional)</label>
                    <select name="channel_link" class="form-control">
                        <option value="">No Channel Link...</option>
                        @foreach($channels ?? [] as $channel)
                            <option value="{{ $channel->id }}">
                                #{{ $channel->name }} 
                                <small class="text-muted">({{ ucfirst($channel->channel_type) }})</small>
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Adds a clickable channel link to the Discord message
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
                <h3 class="card-title">
                    <i class="fas fa-list"></i> Optional Fields
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
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
                            <input type="text" name="formup_location" class="form-control" 
                                   value="{{ old('formup_location') }}">
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
                            <input type="text" name="comms" class="form-control" 
                                   value="{{ old('comms') }}">
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
                        <small class="form-text text-muted">
                            Select a doctrine from seat-fitting or choose "Manual Entry" to type your own
                        </small>
                    @else
                        {{-- Show text input if seat-fitting is not installed --}}
                        <input type="text" name="doctrine" class="form-control" 
                               placeholder="Void Rays (MWD kikis) (Boosts > Logi > Kikis > Slasher/Hyena/Keres)" 
                               value="{{ old('doctrine') }}">
                    @endif
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Ping
                </button>
                <button type="button" class="btn btn-warning" id="scheduleBtn">
                    <i class="fas fa-clock"></i> Schedule
                </button>
                <button type="button" class="btn btn-secondary float-right" id="previewBtn">
                    <i class="fas fa-eye"></i> Preview
                </button>
            </div>
            <!-- Preview Modal -->
            <div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Discord Embed Preview</h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div style="background-color: #36393f; padding: 20px; border-radius: 8px;">
                                <div style="background-color: #2f3136; border-left: 4px solid #5865F2; padding: 16px; border-radius: 4px;" id="embedPreview">
                                    <div style="color: #fff; font-weight: bold; margin-bottom: 8px;">📢 Fleet Broadcast</div>
                                    <div style="color: #dcddde; margin-bottom: 12px;" id="previewMessage"></div>
                                    <div id="previewFields" style="margin-top: 12px;"></div>
                                    <div style="color: #72767d; font-size: 12px; margin-top: 12px;" id="previewFooter"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
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

    // Template buttons
    $('.template-btn').click(function() {
        $('textarea[name="message"]').val($(this).data('template')).trigger('input');
    });

    // Recent ping click - populate form
    $('.recent-ping').click(function() {
        const message = $(this).data('message');
        const fields = $(this).data('fields');
        
        $('textarea[name="message"]').val(message).trigger('input');
        
        if (fields) {
            $.each(fields, function(key, value) {
                $(`[name="${key}"]`).val(value);
            });
        }
    });

    // Mention type change
    $('#mentionType').change(function() {
        const val = $(this).val();
        
        // Hide all mention divs
        $('#roleMentionDiv, #customMentionDiv').hide();
        
        // Show appropriate div
        if (val === 'role') {
            $('#roleMentionDiv').show();
        } else if (val === 'custom') {
            $('#customMentionDiv').show();
        }
    });

    // Toggle multiple webhooks
    $('#multipleToggle').click(function() {
        const isMultiple = $('#multipleWebhooksDiv').is(':visible');
        
        if (isMultiple) {
            // Switch to single
            $('#singleWebhookDiv').show();
            $('#multipleWebhooksDiv').hide();
            $('#webhookSelect').prop('required', true);
            $(this).html('<i class="fas fa-copy"></i> Multiple Webhooks');
            $('#pingForm').attr('action', '{{ route("discordpings.send.post") }}');
        } else {
            // Switch to multiple
            $('#singleWebhookDiv').hide();
            $('#multipleWebhooksDiv').show();
            $('#webhookSelect').prop('required', false);
            $(this).html('<i class="fas fa-bullhorn"></i> Single Webhook');
            $('#pingForm').attr('action', '{{ route("discordpings.send.multiple") }}');
        }
    });

    // Color preview
    function updateColorPreview() {
        $('#colorPreview').css('background-color', $('#embedColor').val());
    }
    
    $('#embedColor').on('input', updateColorPreview);
    
    // Update color when webhook changes
    $('#webhookSelect').change(function() {
        const color = $(this).find(':selected').data('color');
        if (color) {
            $('#embedColor').val(color);
            updateColorPreview();
        }
    });
    
    updateColorPreview();

    // Schedule button
    $('#scheduleBtn').click(function() {
        // Save form data to localStorage for scheduled page
        const formData = $('#pingForm').serializeArray();
        localStorage.setItem('pingFormData', JSON.stringify(formData));
        
        // Redirect to schedule page
        window.location.href = '{{ route("discordpings.scheduled.create") }}';
    });

    // Preview button
    $('#previewBtn').click(function(e) {
        e.preventDefault();
        
        // Get form values
        const message = $('textarea[name="message"]').val();
        const fcName = $('input[name="fc_name"]').val();
        const formup = $('input[name="formup_location"]').val();
        const papType = $('select[name="pap_type"]').val();
        const comms = $('input[name="comms"]').val();
        const doctrine = $('input[name="doctrine"]').val() || $('#doctrineSelect option:selected').text();
        const embedColor = $('#embedColor').val();
        const channelName = $('select[name="channel_link"] option:selected').text();
        
        // Update preview modal
        $('#previewMessage').text(message || 'No message entered');
        $('#embedPreview').css('border-left-color', embedColor);
        
        // Build fields HTML
        let fieldsHtml = '';
        if (fcName) {
            fieldsHtml += `<div style="margin-bottom: 8px;">
                <span style="color: #8e9297; font-weight: bold;">👤 FC Name</span><br>
                <span style="color: #dcddde;">${fcName}</span>
            </div>`;
        }
        if (formup) {
            fieldsHtml += `<div style="margin-bottom: 8px;">
                <span style="color: #8e9297; font-weight: bold;">📍 Formup Location</span><br>
                <span style="color: #dcddde;">${formup}</span>
            </div>`;
        }
        if (papType) {
            fieldsHtml += `<div style="margin-bottom: 8px;">
                <span style="color: #8e9297; font-weight: bold;">🎯 PAP Type</span><br>
                <span style="color: #dcddde;">${papType}</span>
            </div>`;
        }
        if (comms) {
            fieldsHtml += `<div style="margin-bottom: 8px;">
                <span style="color: #8e9297; font-weight: bold;">🎧 Comms</span><br>
                <span style="color: #dcddde;">${comms}</span>
            </div>`;
        }
        if (doctrine && doctrine !== 'Select Doctrine...') {
            fieldsHtml += `<div style="margin-bottom: 8px;">
                <span style="color: #8e9297; font-weight: bold;">🚀 Doctrine</span><br>
                <span style="color: #dcddde;">${doctrine}</span>
            </div>`;
        }
        if (channelName && channelName !== 'No Channel Link...') {
            fieldsHtml += `<div style="margin-bottom: 8px;">
                <span style="color: #8e9297; font-weight: bold;">💬 Channel</span><br>
                <span style="color: #5865F2;">${channelName}</span>
            </div>`;
        }
        
        $('#previewFields').html(fieldsHtml);
        
        // Update footer
        const userName = '{{ auth()->user()->name }}';
        const currentTime = new Date().toISOString().replace('T', ' ').substring(0, 19);
        $('#previewFooter').text(`This was a coord broadcast from ${userName} to discord at ${currentTime} EVE`);
        
        // Show modal
        $('#previewModal').modal('show');
    });

    @if(($hasFittingPlugin ?? false) && ($doctrines ?? collect())->count() > 0)
    // Handle doctrine dropdown change (only if seat-fitting is installed)
    $('#doctrineSelect').change(function() {
        if ($(this).val() === 'custom') {
            $('#doctrineManual').show().prop('required', false);
            $(this).prop('name', ''); // Remove name so it doesn't submit
            $('#doctrineManual').prop('name', 'doctrine'); // Set manual field name
        } else {
            $('#doctrineManual').hide().val('');
            $(this).prop('name', 'doctrine_id'); // Restore dropdown name
            $('#doctrineManual').prop('name', ''); // Remove manual field name
        }
    });
    @endif
});
</script>
@endpush
