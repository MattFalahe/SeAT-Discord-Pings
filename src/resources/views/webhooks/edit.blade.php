@extends('web::layouts.grids.4-8')

@section('title', 'Edit Discord Webhook')
@section('page_header', 'Edit Discord Webhook')

@section('left')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Instructions</h3>
        </div>
        <div class="card-body">
            <p>Update the webhook settings as needed.</p>
            <p>The webhook URL can be changed if the Discord webhook was recreated.</p>
        </div>
    </div>
@stop

@section('right')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit Webhook</h3>
        </div>
        <form method="POST" action="{{ route('discordpings.webhooks.update', $webhook->id) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           value="{{ old('name', $webhook->name) }}">
                </div>

                <div class="form-group">
                    <label>Webhook URL <span class="text-danger">*</span></label>
                    <input type="url" name="webhook_url" class="form-control" required 
                           value="{{ old('webhook_url', $webhook->webhook_url) }}">
                </div>

                <div class="form-group">
                    <label>Channel Type</label>
                    <input type="text" name="channel_type" class="form-control" 
                           value="{{ old('channel_type', $webhook->channel_type) }}">
                </div>

                <div class="form-group">
                    <label>Default Embed Color</label>
                    <input type="text" name="embed_color" class="form-control" 
                           value="{{ old('embed_color', $webhook->embed_color) }}" 
                           pattern="^#[0-9A-Fa-f]{6}$" required>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="enable_mentions" 
                               name="enable_mentions" value="1"
                               {{ old('enable_mentions', $webhook->enable_mentions) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="enable_mentions">
                            Enable Mentions
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="is_active" 
                               name="is_active" value="1"
                               {{ old('is_active', $webhook->is_active) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_active">
                            Active
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Webhook
                </button>
                <a href="{{ route('discordpings.webhooks') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
@stop
