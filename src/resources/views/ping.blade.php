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
    .recent-ping {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .recent-ping:hover {
        background-color: rgba(0,123,255,0.1);
    }
    .webhook-checkbox {
        margin-right: 5px;
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
</style>
@endpush

@section('left')
    {{-- Templates Card --}}
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-alt"></i> Quick Templates
            </h3>
        </div>
        <div class="card-body p-2">
            <button class="btn btn-sm btn-block btn-outline-primary template-btn" 
                    data-template="Many hands make light work, the more people join the faster we're done!">
                <i class="fas fa-users"></i> Standard CTA
            </button>
            <button class="btn btn-sm btn-block btn-outline-warning template-btn" 
                    data-template="EMERGENCY! Hostiles in staging! All hands on deck NOW!">
                <i class="fas fa-exclamation-triangle"></i> Emergency
            </button>
            <button class="btn btn-sm btn-block btn-outline-success template-btn" 
                    data-template="Mining fleet forming! Orca boosts available. Join for some chill mining.">
                <i class="fas fa-gem"></i> Mining Op
            </button>
            <button class="btn btn-sm btn-block btn-outline-info template-btn" 
                    data-template="Roam fleet forming in 30 minutes. Kitchen sink doctrine, bring what you can fly!">
                <i class="fas fa-rocket"></i> Roam Fleet
            </button>
            <button class="btn btn-sm btn-block btn-outline-danger template-btn" 
                    data-template="STRATOP! Maximum numbers needed. This is a critical timer. PAPs will be given.">
                <i class="fas fa-flag"></i> Strategic Op
            </button>
        </div>
    </div>

    {{-- Recent Pings Card --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history"></i> Recent Pings
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @forelse($recentPings as $ping)
                    <div class="list-group-item recent-ping p-2" 
                         data-message="{{ $ping->message }}"
                         data-fields='@json($ping->fields)'>
                        <div class="d-flex w-100 justify-content-between">
                            <small class="mb-1">
                                <strong>{{ Str::limit($ping->webhook->name, 20) }}</strong>
                            </small>
                            <small class="text-muted">
                                {{ $ping->created_at->diffForHumans() }}
                            </small>
                        </div>
                        <p class="mb-1 small">{{ Str::limit($ping->message, 50) }}</p>
                        @if($ping->status === 'failed')
                            <span class="badge badge-danger">Failed</span>
                        @endif
                    </div>
                @empty
                    <div class="list-group-item">
                        <small class="text-muted">No recent pings</small>
                    </div>
                @endforelse
            </div>
        </div>
        @if($recentPings->count() > 0)
            <div class="card-footer p-2">
                <a href="{{ route('discord.pings.history.index') }}" class="btn btn-sm btn-block btn-outline-secondary">
                    View All History
                </a>
            </div>
        @endif
    </div>
@stop

@section('right')
    <form method="POST" action="{{ route('discord.pings.send') }}" id="pingForm">
        @csrf
        
        {{-- Main Ping Card --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paper-plane"></i> Send Ping
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                {{-- Webhook Selection --}}
                <div class="form-group">
                    <label>Target Webhook(s) <span class="text-danger">*</span></label>
                    
                    {{-- Single webhook select --}}
                    <div id="singleWebhookDiv">
                        <select name="webhook_id" class="form-control" id="webhookSelect">
                            <option value="">Select Channel...</option>
                            @foreach($webhooks as $webhook)
                                <option value="{{ $webhook->id }}" 
                                        data-color="{{ $webhook->embed_color }}">
                                    {{ $webhook->name }}
                                    @if($webhook->channel_type)
                                        ({{ $webhook->channel_type }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    {{-- Multiple webhook checkboxes (hidden by default) --}}
                    <div id="multipleWebhookDiv" style="display: none;">
                        @foreach($webhooks as $webhook)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input webhook-checkbox" 
                                       id="webhook_{{ $webhook->id }}" 
                                       name="webhook_ids[]" 
                                       value="{{ $webhook->id }}">
                                <label class="custom-control-label" for="webhook_{{ $webhook->id }}">
                                    {{ $webhook->name }}
                                    @if($webhook->channel_type)
                                        <small class="text-muted">({{ $webhook->channel_type }})</small>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    
                    <small class="form-text text-muted">
                        <a href="#" id="toggleMultiple">Send to multiple webhooks</a>
                    </small>
                </div>

                {{-- Message --}}
                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="3" required 
                              placeholder="Many hands make light work, the more people join the faster we're done!"
                              maxlength="2000">{{ old('message') }}</textarea>
                    <small class="form-text text-muted">
                        <span id="charCount">0</span>/2000 characters
                    </small>
                </div>

                {{-- Mentions --}}
                <div class="form-group">
                    <label>Mentions</label>
                    <div class="input-group">
                        <select name="mention_type" class="form-control" id="mentionType">
                            <option value="none">No Mention</option>
                            <option value="everyone">@everyone</option>
                            <option value="here">@here</option>
                            <option value="custom">Custom Role/User</option>
                        </select>
                        <input type="text" name="custom_mention" class="form-control" 
                               id="customMention" placeholder="@role or @username" 
                               style="display: none;">
                    </div>
                </div>

                {{-- Embed Color --}}
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

        {{-- Optional Fields Card --}}
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
                                   placeholder="MedusaCascade4" value="{{ old('fc_name') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Formup Location</label>
                            <input type="text" name="formup_location" class="form-control" 
                                   placeholder="C-J6MT" value="{{ old('formup_location') }}">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> PAP Type</label>
                            <select name="pap_type" class="form-control">
                                <option value="">None</option>
                                <option value="Strategic" {{ old('pap_type') == 'Strategic' ? 'selected' : '' }}>
                                    Strategic
                                </option>
                                <option value="Peacetime" {{ old('pap_type') == 'Peacetime' ? 'selected' : '' }}>
                                    Peacetime
                                </option>
                                <option value="CTA" {{ old('pap_type') == 'CTA' ? 'selected' : '' }}>
                                    CTA (Call To Arms)
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-headset"></i> Comms</label>
                            <input type="text" name="comms" class="form-control" 
                                   placeholder="Op 3 https://gnf.lt/NOH1FNH.html" 
                                   value="{{ old('comms') }}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-rocket"></i> Doctrine</label>
                    <input type="text" name="doctrine" class="form-control" 
                           placeholder="Void Rays (MWD kikis) (Boosts > Logi > Kikis > Slasher/Hyena/Keres)" 
                           value="{{ old('doctrine') }}">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary" id="sendBtn">
                    <i class="fas fa-paper-plane"></i> Send Ping
                </button>
                <button type="button" class="btn btn-warning" id="scheduleBtn">
                    <i class="fas fa-clock"></i> Schedule
                </button>
                <button type="button" class="btn btn-secondary float-right" id="previewBtn">
                    <i class="fas fa-eye"></i> Preview
                </button>
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

    // Recent ping click
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

    // Toggle multiple webhooks
    $('#toggleMultiple').click(function(e) {
        e.preventDefault();
        const isMultiple = $('#multipleWebhookDiv').is(':visible');
        
        if (isMultiple) {
            $('#singleWebhookDiv').show();
            $('#multipleWebhookDiv').hide();
            $(this).text('Send to multiple webhooks');
            $('#pingForm').attr('action', '{{ route("discord.pings.send") }}');
        } else {
            $('#singleWebhookDiv').hide();
            $('#multipleWebhookDiv').show();
            $(this).text('Send to single webhook');
            $('#pingForm').attr('action', '{{ route("discord.pings.send.multiple") }}');
        }
    });

    // Mention type change
    $('#mentionType').change(function() {
        if ($(this).val() === 'custom') {
            $('#customMention').show();
        } else {
            $('#customMention').hide();
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

    // Schedule button
    $('#scheduleBtn').click(function() {
        // Save form data to localStorage
        const formData = $('#pingForm').serializeArray();
        localStorage.setItem('pingFormData', JSON.stringify(formData));
        
        // Redirect to schedule page
        window.location.href = '{{ route("discord.pings.scheduled.create") }}';
    });

    // Preview button
    $('#previewBtn').click(function() {
        const formData = $('#pingForm').serialize();
        
        // Open preview modal
        alert('Preview functionality would show Discord embed preview here');
    });
});
</script>
@endpush
