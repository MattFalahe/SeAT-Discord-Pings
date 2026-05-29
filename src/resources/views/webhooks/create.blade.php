@extends('web::layouts.grids.4-8')

@section('title', 'Create Discord Webhook')
@section('page_header', 'Create Discord Webhook')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=2">
@endpush

@section('left')
<div class="discord-pings-wrapper">
    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle"></i> Instructions
            </h3>
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
</div>
@stop

@section('right')
<div class="discord-pings-wrapper">
    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-link"></i> Webhook Details
            </h3>
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
                                 style="width: 40px; border: 1px solid rgba(255, 255, 255, 0.15);"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="receives_structure_alerts"
                               name="receives_structure_alerts" value="1"
                               {{ old('receives_structure_alerts') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="receives_structure_alerts">
                            Receive structure timer alerts
                        </label>
                    </div>
                    <small class="text-muted">
                        When enabled, this webhook receives pre-timer reminder pings (T-24h and
                        T-1h) for structure timers ingested from Manager Core. Requires Manager
                        Core and Structure Manager to be installed.
                    </small>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="receives_mining_alerts"
                               name="receives_mining_alerts" value="1"
                               {{ old('receives_mining_alerts') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="receives_mining_alerts">
                            Receive mining extraction alerts
                        </label>
                    </div>
                    <small class="text-muted">
                        When enabled, this webhook receives a single pre-expiry alert (T-2h to the
                        end of the fleet-able window) for each moon extraction. Requires Manager
                        Core and Mining Manager v2.0.1+ to be installed.
                    </small>
                </div>

                <div class="form-group">
                    <label>Corporation Scope</label>
                    <select name="corporation_id" class="form-control">
                        <option value="">Any corporation (receives all events)</option>
                        @foreach(($corporations ?? collect()) as $corp)
                            <option value="{{ $corp->corporation_id }}"
                                {{ (string) old('corporation_id') === (string) $corp->corporation_id ? 'selected' : '' }}>
                                {{ $corp->name }}{{ $corp->ticker ? ' [' . $corp->ticker . ']' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">
                        When set, this webhook only receives structure-timer alerts for the chosen
                        corporation (events from other corps are skipped). Leave as "Any corporation"
                        on single-corp / single-alliance installs or for an org-wide ops channel.
                    </small>
                </div>

            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-pings-primary">
                    <i class="fas fa-save"></i> Create Webhook
                </button>
                <a href="{{ route('discordpings.webhooks') }}" class="btn btn-pings-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
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
