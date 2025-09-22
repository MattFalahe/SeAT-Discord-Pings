@extends('web::layouts.grids.4-8')

@section('title', 'Create Discord Webhook')
@section('page_header', 'Create Discord Webhook')

@section('left')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Instructions</h3>
        </div>
        <div class="card-body">
            <ol class="mb-0">
                <li>Go to your Discord channel</li>
                <li>Click the gear icon (Edit Channel)</li>
                <li>Navigate to <strong>Integrations</strong></li>
                <li>Click <strong>Webhooks</strong></li>
                <li>Click <strong>New Webhook</strong></li>
                <li>Give it a name</li>
                <li>Click <strong>Copy Webhook URL</strong></li>
                <li>Paste the URL below</li>
            </ol>
        </div>
    </div>
@stop

@section('right')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Webhook Details</h3>
        </div>
        <form method="POST" action="{{ route('discord.pings.webhooks.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           placeholder="Alliance Pings" value="{{ old('name') }}">
                    <small class="form-text text-muted">A friendly name to identify this webhook</small>
                </div>

                <div class="form-group">
                    <label>Webhook URL <span class="text-danger">*</span></label>
                    <input type="url" name="webhook_url" class="form-control" required 
                           placeholder="https://discord.com/api/webhooks/..." value="{{ old('webhook_url') }}">
                    <small class="form-text text-muted">The Discord webhook URL</small>
                </div>

                <div class="form-group">
                    <label>Channel Type</label>
                    <input type="text" name="channel_type" class="form-control" 
                           placeholder="general, coord, strategic" value="{{ old('channel_type') }}">
                    <small class="form-text text-muted">Optional: Categorize this webhook</small>
                </div>

                <div class="form-group">
                    <label>Default Embed Color</label>
                    <div class="input-group">
                        <input type="text" name="embed_color" class="form-control" 
                               value="{{ old('embed_color', '#5865F2') }}" 
                               pattern="^#[0-9A-Fa-f]{6}$" required>
                        <div class="input-group-append">
                            <div class="color-preview" id="colorPreview" 
                                 style="width: 40px; border: 1px solid #dee2e6;"></div>
                        </div>
                    </div>
                    <small class="form-text text-muted">Default color for embeds sent to this webhook</small>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="enable_mentions" 
                               name="enable_mentions" {{ old('enable_mentions') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="enable_mentions">
                            Enable Mentions
                        </label>
                    </div>
                    <small class="form-text text-muted">Allow @everyone and @here mentions</small>
                </div>

                <div class="form-group" id="default_mention_group" style="display: none;">
                    <label>Default Mention</label>
                    <input type="text" name="default_mention" class="form-control" 
                           placeholder="@everyone, @here, or role ID" value="{{ old('default_mention') }}">
                    <small class="form-text text-muted">Default mention to use (optional)</small>
                </div>

                <div class="form-group">
                    <label>Role Restrictions</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px;">
                        @foreach($roles as $role)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" 
                                       id="role_{{ $role->id }}" 
                                       name="role_ids[]" 
                                       value="{{ $role->id }}"
                                       {{ in_array($role->id, old('role_ids', [])) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="role_{{ $role->id }}">
                                    {{ $role->title }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="form-text text-muted">Leave empty to allow all users</small>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Webhook
                </button>
                <a href="{{ route('discord.pings.webhooks.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    // Color preview
    function updateColorPreview() {
        $('#colorPreview').css('background-color', $('input[name="embed_color"]').val());
    }
    
    $('input[name="embed_color"]').on('input', updateColorPreview);
    updateColorPreview();

    // Toggle default mention field
    $('#enable_mentions').change(function() {
        if ($(this).is(':checked')) {
            $('#default_mention_group').show();
        } else {
            $('#default_mention_group').hide();
        }
    }).trigger('change');
});
</script>
@endpush
