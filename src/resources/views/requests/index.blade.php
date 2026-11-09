@extends('web::layouts.grids.12')

@section('title', 'Ping Requests')
@section('page_header', 'Ping Requests')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=2">
<style>
    .pr-notice { border-left: 4px solid var(--pings-info); }
    .pr-card { height: 100%; margin-bottom: 0; }
    .pr-card.pr-emergency { border-left: 4px solid var(--pings-danger); }
    .pr-notifies { font-size: 0.875rem; margin-bottom: 8px; }
    .pr-plain { white-space: pre-wrap; font-size: 0.875rem; }
    .pr-press-row { display: flex; gap: 6px; margin-bottom: 6px; }
    .pr-press-row .js-pr-press { flex: 1; text-align: left; white-space: normal; }
    .pr-press-row .btn:disabled { cursor: not-allowed; }
    .pr-status { min-height: 1.5rem; font-size: 0.875rem; margin-top: 8px; }
    .pr-status.pr-ok { color: var(--pings-success) !important; }
    .pr-status.pr-bad { color: var(--pings-danger) !important; }
    .pr-preview { white-space: pre-wrap; background: rgba(0, 0, 0, 0.25); border: 1px solid var(--pings-border); border-radius: 4px; padding: 10px; font-size: 0.875rem; }
    .pr-who { font-size: 0.875rem; }
</style>
@endpush

@section('full')
<div class="discord-pings-wrapper">
    @php
        $who = $identity->mainName . ', ' . ($identity->corporation ?: 'unknown corporation');
        if ($identity->connectorInstalled) {
            $who .= $identity->discordId
                ? ', Discord linked' . ($identity->discordName ? ' as ' . $identity->discordName : '')
                : ', Discord not linked';
        }
    @endphp
    {{-- A card and not the tinted alert: this sits right on the page, and that tint goes pale on a light theme. --}}
    <div class="card card-dark pr-notice">
        <div class="card-body">
            <i class="fas fa-info-circle"></i>
            A red button posts to Discord the moment you press it. The others show you what will be posted first.
            Every message says who sent it: <strong>{{ $who }}</strong>
        </div>
    </div>

    <div class="row">
        @forelse($cards as $card)
            <div class="col-xl-4 col-lg-6 col-12 mb-3">
                <div class="card card-dark pr-card {{ $card['emergency'] ? 'pr-emergency' : '' }}" data-card="{{ $card['id'] }}">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas {{ $card['emergency'] ? 'fa-exclamation-triangle' : 'fa-hand-paper' }}"></i>
                            {{ $card['name'] }}
                        </h3>
                        <div class="card-tools">
                            @if($card['emergency'])
                                <span class="badge badge-danger">Emergency</span>
                            @else
                                <span class="badge badge-info">Request</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if($card['pingNotice'] !== '')
                            <p class="pr-notifies"><i class="fas fa-bell"></i> Notifies: {{ $card['pingNotice'] }}</p>
                        @endif

                        @if($card['plain'] !== '')
                            <p class="pr-plain text-muted">{{ $card['plain'] }}</p>
                        @endif

                        @foreach($card['presets'] as $preset)
                            <div class="pr-press-row">
                                <button type="button" class="btn {{ $card['emergency'] ? 'btn-danger' : 'btn-pings-primary' }} js-pr-press"
                                        data-button-id="{{ $card['id'] }}" data-preset-id="{{ $preset['id'] }}" title="{{ $preset['preview'] }}">
                                    <i class="fas {{ $card['emergency'] ? 'fa-bolt' : 'fa-paper-plane' }}"></i> {{ $preset['name'] }}
                                </button>
                                <button type="button" class="btn btn-pings-secondary js-pr-edit-preset" data-button-id="{{ $card['id'] }}" data-preset-id="{{ $preset['id'] }}" title="Edit this saved form">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-pings-secondary js-pr-delete-preset" data-preset-id="{{ $preset['id'] }}" title="Delete this saved form">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        @endforeach

                        <div class="pr-press-row">
                            <button type="button" class="btn {{ $card['emergency'] ? 'btn-danger' : 'btn-pings-primary' }} js-pr-press" data-button-id="{{ $card['id'] }}">
                                @if($card['emergency'])
                                    <i class="fas fa-bolt"></i> {{ count($card['presets']) > 0 ? 'Send with no saved form' : 'Send now' }}
                                @else
                                    <i class="fas fa-paper-plane"></i> Fill in and send
                                @endif
                            </button>
                        </div>

                        <button type="button" class="btn btn-sm btn-pings-secondary js-pr-add-preset" data-button-id="{{ $card['id'] }}">
                            <i class="fas fa-plus"></i> Save a form
                        </button>

                        <div class="pr-status" role="status"></div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card card-dark">
                    <div class="card-body text-center text-muted">
                        There are no buttons for you yet. Your admins add them in Settings.
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history"></i> Your last requests</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Button</th>
                            <th>Saved form</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent as $history)
                            <tr>
                                <td>
                                    <span class="eve-time" data-eve-time="{{ $history->created_at->utc()->toIso8601String() }}">
                                        {{ $history->created_at->utc()->format('Y-m-d H:i:s') }} EVE
                                    </span>
                                </td>
                                <td>{{ $history->fields['button'] ?? '' }}</td>
                                <td>{{ $history->fields['saved_form'] ?? '' }}</td>
                                <td>
                                    @if($history->status === 'sent')
                                        <span class="badge badge-success">Delivered</span>
                                    @elseif($history->status === 'blocked')
                                        <span class="badge badge-secondary">Cooldown</span>
                                    @elseif($history->status === 'rate_limited')
                                        <span class="badge badge-warning">Too many requests</span>
                                    @else
                                        <span class="badge badge-danger">Not delivered</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Nothing sent yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Both move to the body before they open, so they carry the wrapper class with them. --}}
<div class="modal fade discord-pings-wrapper" id="prSendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prSendTitle"></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="prSendFields"></div>
                <label class="mt-2">What will be posted</label>
                <div class="pr-preview" id="prSendPreview"></div>
                <div class="pr-who text-muted mt-2">
                    Sent by {{ $identity->mainName }}, {{ $identity->corporation ?: 'unknown corporation' }}
                </div>
                <small class="text-muted d-block mt-2">Changes here are for this send only. Your saved form stays as it is.</small>
                <div class="pr-status" id="prSendStatus" role="status"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-pings-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-pings-primary" id="prSendConfirm"><i class="fas fa-paper-plane"></i> Send</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade discord-pings-wrapper" id="prPresetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prPresetTitle"></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="prPresetName">Name for this saved form</label>
                    <input type="text" class="form-control" id="prPresetName" maxlength="64" placeholder="e.g. Rorqual on my alt">
                </div>
                <div id="prPresetFields"></div>
                <div class="pr-status" id="prPresetStatus" role="status"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-pings-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-pings-primary" id="prPresetSave"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>
@stop

@push('javascript')
<script src="{{ asset('vendor/discordpings/js/eve-time.js') }}?v=1" defer></script>
<script>
    window.PING_REQUESTS = {
        cards: @json($cards),
        csrf: @json(csrf_token()),
        pressUrl: @json(url('discord-pings/requests/__ID__/press')),
        storePresetUrl: @json(url('discord-pings/requests/__ID__/presets')),
        presetUrl: @json(url('discord-pings/requests/presets/__ID__'))
    };
</script>
<script src="{{ asset('vendor/discordpings/js/ping-requests.js') }}?v=1"></script>
@endpush
