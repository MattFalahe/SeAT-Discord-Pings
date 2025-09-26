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
        <form method="POST" action="{{ route('discordpings.webhooks.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           placeholder="Alliance Pings" value="{{ old('name') }}">
                </div>

                <div class="form-group">
                    <label>Webhook URL <span class="text-danger">*</span></label>
                    <input type="url" name="webhook_url" class="form-control" required 
                           placeholder="https://discord.com/api/webhooks/..." value="{{ old('webhook_url') }}">
                </div>

                <div class="form-group">
                    <label>Channel Type</label>
                    <input type="text" name="channel_type" class="form-control" 
                           placeholder="general, coord, strategic" value="{{ old('channel_type') }}">
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
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="enable_mentions" 
                               name="enable_mentions" value="1" {{ old('enable_mentions') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="enable_mentions">
                            Enable Mentions
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Webhook
                </button>
                <a href="{{ route('discordpings.webhooks') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    function updateColorPreview() {
        $('#colorPreview').css('background-color', $('input[name="embed_color"]').val());
    }
    
    $('input[name="embed_color"]').on('input', updateColorPreview);
    updateColorPreview();
});
</script>
@endpush
