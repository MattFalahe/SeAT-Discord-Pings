@extends('web::layouts.grids.12')

@section('title', 'Ping Requests')
@section('page_header', 'Ping Requests')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=4">
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
    .pr-yesno .btn { min-width: 64px; }
    .pr-active-intro { font-size: 0.875rem; margin-bottom: 12px; }
    .pr-active-item { height: 100%; padding: 10px 12px; border: 1px solid var(--pings-border); border-left: 4px solid var(--pings-danger); border-radius: 4px; }
    .pr-active-item.pr-closed { opacity: 0.6; }
    .pr-active-head { display: flex; align-items: flex-start; gap: 8px; }
    .pr-active-name { flex: 1 1 auto; min-width: 0; font-size: 1rem; overflow-wrap: anywhere; }
    .pr-active-form { margin-left: 6px; font-size: 0.875rem; }
    /* The plugin's buttons go full width on a phone, and this one shares its row with the name. */
    .pr-active-head .pr-active-close { flex: none; width: auto; margin: 0; }
    .pr-active-when { display: flex; flex-wrap: wrap; gap: 2px 12px; margin: 2px 0 10px; font-size: 0.875rem; }
    .pr-active-handled { margin: -4px 0 10px; padding: 6px 10px; border-left: 3px solid #17a2b8; background: rgba(23, 162, 184, 0.12); border-radius: 3px; font-size: 0.875rem; }
    .pr-handled-badge { margin-left: 4px; }
    .pr-active-left { font-variant-numeric: tabular-nums; }
    .pr-active-item .form-group { margin-bottom: 10px; }
    .pr-suggests { position: relative; }
    /* Solid on purpose, it sits over the boxes below it and a light theme would wash out anything see-through. */
    .pr-suggest-menu { position: absolute; left: 0; right: 0; top: 100%; z-index: 1060; max-height: 260px; overflow-y: auto; background: #1e222b; border: 1px solid #454d55; border-radius: 4px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.45); }
    .pr-suggest-group { padding: 6px 10px 2px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #8b95a5; }
    .pr-suggest-item { padding: 5px 10px; font-size: 0.875rem; color: #fff; cursor: pointer; }
    .pr-suggest-item:hover, .pr-suggest-item.pr-active { background: #4c5fd5; }
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

    {{-- Kept on the server until each one closes, so a reload or another tab finds them again. --}}
    <div class="card card-dark pr-active-requests {{ count($active) > 0 ? '' : 'd-none' }}" id="prActive">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-satellite-dish"></i> Active requests</h3>
        </div>
        <div class="card-body pb-0">
            <p class="pr-active-intro">
                Add what you know as it happens. Each answer updates your message on Discord without pinging anyone again.
                A request stays here for 10 minutes after it went out, even if you reload the page, or until you close it.
                Pressing a red button again always sends a new message.
            </p>
            <div class="row" id="prActiveList"></div>
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
                                    <i class="fas fa-bolt"></i> Send now, fill in after
                                @else
                                    <i class="fas fa-paper-plane"></i> Fill in and send
                                @endif
                            </button>
                        </div>

                        @if($card['canSave'])
                            <button type="button" class="btn btn-sm btn-pings-secondary js-pr-add-preset" data-button-id="{{ $card['id'] }}">
                                <i class="fas fa-plus"></i> Save a form
                            </button>
                        @endif

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
            <h3 class="card-title"><i class="fas fa-history"></i> Your requests from the last {{ $recentDays }} days</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="prRecentTable" class="table table-striped table-hover mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Button</th>
                            <th>Saved form</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent as $history)
                            <tr>
                                <td data-order="{{ $history->created_at->timestamp }}">
                                    <span class="eve-time" data-eve-time="{{ $history->created_at->utc()->toIso8601String() }}">
                                        {{ $history->created_at->utc()->format('Y-m-d H:i:s') }} EVE
                                    </span>
                                </td>
                                <td>{{ $history->fields['button'] ?? '' }}</td>
                                <td>{{ $history->fields['saved_form'] ?? '' }}</td>
                                <td>
                                    @if($history->status === 'sent')
                                        <span class="badge badge-success">Delivered</span>
                                        {{-- Filled in again by the page as FCs answer. --}}
                                        <span class="pr-handled" data-history-id="{{ $history->id }}">
                                            @if (! empty($recentAnswers[$history->id]))
                                                <span class="badge badge-info pr-handled-badge" title="An FC has sent a broadcast for this request">
                                                    <i class="fas fa-user-check"></i>
                                                    Handled by {{ implode(', ', array_column($recentAnswers[$history->id], 'name')) }}
                                                </span>
                                            @endif
                                        </span>
                                    @elseif($history->status === 'blocked')
                                        <span class="badge badge-secondary">Cooldown</span>
                                    @elseif($history->status === 'rate_limited')
                                        <span class="badge badge-warning">Too many requests</span>
                                    @else
                                        <span class="badge badge-danger">Not delivered</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
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
        active: @json($active),
        characters: @json($characters),
        csrf: @json(csrf_token()),
        pressUrl: @json(url('discord-pings/requests/__ID__/press')),
        detailsUrl: @json(url('discord-pings/requests/details/__TOKEN__')),
        closeUrl: @json(url('discord-pings/requests/details/__TOKEN__/close')),
        storePresetUrl: @json(url('discord-pings/requests/__ID__/presets')),
        presetUrl: @json(url('discord-pings/requests/presets/__ID__')),
        systemsUrl: @json(url('discord-pings/requests/systems')),
        shipsUrl: @json(url('discord-pings/requests/ships')),
        locationsUrl: @json(url('discord-pings/requests/systems/__ID__/locations')),
        answersUrl: @json(route('discordpings.requests.answers')),
        watchAnswers: @json($watchAnswers ?? false)
    };
</script>
<script src="{{ asset('vendor/discordpings/js/ping-requests.js') }}?v=6"></script>
<script src="{{ asset('vendor/discordpings/js/vendor/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/discordpings/js/vendor/dataTables.bootstrap4.min.js') }}"></script>
<script>
    $(function () {
        $('#prRecentTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            searching: false,
            language: {
                lengthMenu: 'Show _MENU_ requests',
                info: '_START_ to _END_ of _TOTAL_',
                infoEmpty: '',
                emptyTable: @json('Nothing sent in the last ' . $recentDays . ' days.')
            },
            // Only the rows on the page are in the document, so each page gets its local times when it draws.
            drawCallback: function () {
                if (window.EveTime) {
                    window.EveTime.convert(this.api().table().node());
                }
            }
        });
    });
</script>
@endpush
