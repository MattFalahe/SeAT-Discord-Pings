@php
    $pickerRoleNames = collect($pickerRoles)->pluck('name', 'id');
    // A role in two sources counts under both, because narrowing to a source has to show all of it.
    $pickerSourceCounts = collect($pickerRoles)->pluck('sources')->flatten()->countBy()->sortKeys();
    $pickerSourceColours = ['discord-roles-table' => '#28a745', 'seat-connector' => '#3498db', 'warlof-discord' => '#95a5a6'];
    $sectionBoxes = \DiscordPings\Services\PingRequests\Sections::BOXES;
    $sectionStartingPoints = \DiscordPings\Services\PingRequests\Sections::STARTING_POINTS;
@endphp

@push('head')
<style>
    .prb-section-head, .prb-section-row { display: flex; gap: 6px; align-items: center; }
    .prb-section-head { font-size: 0.8rem; margin-bottom: 4px; }
    .prb-section-row { margin-bottom: 6px; }
    .prb-section-head .prb-col-label, .prb-section-row .js-prb-label { flex: 1 1 auto; min-width: 0; }
    .prb-section-head .prb-col-box, .prb-section-row .js-prb-box { flex: 0 0 165px; }
    .prb-section-head .prb-col-live, .prb-section-row .js-prb-live { flex: 0 0 140px; }
    .prb-section-head .prb-col-tools, .prb-section-row .prb-row-tools { flex: 0 0 102px; }
    .prb-start { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 10px; }
    .prb-tick-col { width: 1%; }
    .prb-head { gap: 6px; }
    .prb-head-tools { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
    @media (max-width: 575.98px) {
        .prb-section-head { display: none; }
        .prb-section-row { flex-wrap: wrap; padding-bottom: 6px; border-bottom: 1px solid #454d55; }
        .prb-section-row .js-prb-label { flex-basis: 100%; }
        .prb-section-row .js-prb-box, .prb-section-row .js-prb-live { flex: 1 1 0; }
    }
</style>
@endpush

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 prb-head">
    <h4 class="mb-0">Ping Requests</h4>
    <div class="prb-head-tools">
        @if($requestButtons->isEmpty())
            <form method="POST" action="{{ route('discordpings.config.ping-requests.examples') }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-pings-secondary">
                    <i class="fas fa-magic"></i> Load examples
                </button>
            </form>
        @else
            <button type="button" class="btn btn-danger js-prb-delete-ticked" disabled>
                <i class="fas fa-trash"></i> Delete selected <span class="js-prb-ticked-count"></span>
            </button>
            <button type="button" class="btn btn-danger js-prb-delete-all">
                <i class="fas fa-trash-alt"></i> Delete all
            </button>
        @endif
        <button type="button" class="btn btn-pings-primary js-add-request-button">
            <i class="fas fa-plus"></i> Add Button
        </button>
    </div>
</div>

<p class="text-muted">
    Buttons line members can press to ask for help or for a fleet. Each one posts to its webhook and pings its role.
    Members need <code>View SeAT Broadcast</code> and <code>Line Member</code> to see them.
</p>

<div class="table-responsive">
    <table class="table table-striped table-hover" style="width:100%">
        <thead>
            <tr>
                <th class="prb-tick-col">
                    @if($requestButtons->isNotEmpty())
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input js-prb-tick-all" id="prb_tick_all" autocomplete="off">
                            <label class="custom-control-label" for="prb_tick_all"><span class="sr-only">Tick every button</span></label>
                        </div>
                    @endif
                </th>
                <th>Button</th>
                <th>Webhook</th>
                <th>Pings</th>
                <th>Cooldown</th>
                <th>Visible to</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requestButtons as $button)
                <tr>
                    <td class="prb-tick-col">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input js-prb-tick" id="prb_tick_{{ $button->id }}" value="{{ $button->id }}" autocomplete="off">
                            <label class="custom-control-label" for="prb_tick_{{ $button->id }}"><span class="sr-only">Tick {{ $button->name }}</span></label>
                        </div>
                    </td>
                    <td>
                        <strong>{{ $button->name }}</strong>
                        <br>
                        @if($button->isEmergency())
                            <span class="badge badge-danger">Emergency</span>
                        @else
                            <span class="badge badge-info">Request</span>
                        @endif
                    </td>
                    <td>
                        @if($button->webhook)
                            {{ $button->webhook->name }}
                        @else
                            <span class="text-muted">None</span>
                        @endif
                    </td>
                    <td>
                        @if($button->mention_role_id)
                            {{ $pickerRoleNames[$button->mention_role_id] ?? ('Role ' . $button->mention_role_id) }}
                        @else
                            <span class="text-muted">Nobody</span>
                        @endif
                    </td>
                    <td>
                        @if($button->isEmergency())
                            <span class="text-muted">None, posts at once</span>
                        @elseif($button->cooldown_minutes > 0)
                            {{ $button->cooldown_minutes }} min
                        @else
                            <span class="text-muted">None</span>
                        @endif
                    </td>
                    <td>
                        @if(empty($button->audience_permissions) && ! $button->corporation_id && ! $button->alliance_id)
                            <span class="text-muted">Every line member</span>
                        @endif
                        @foreach($button->audience_permissions ?? [] as $permission)
                            <span class="badge badge-secondary">{{ $audienceOptions[$permission] ?? $permission }}</span>
                        @endforeach
                        @if($button->corporation_id)
                            <span class="badge badge-info">{{ $requestCorporations[$button->corporation_id] ?? ('Corporation ' . $button->corporation_id) }}</span>
                        @endif
                        @if($button->alliance_id)
                            <span class="badge badge-info">{{ $requestAlliances[$button->alliance_id] ?? ('Alliance ' . $button->alliance_id) }}</span>
                        @endif
                    </td>
                    <td>
                        @if(! $button->webhook)
                            <span class="badge badge-danger">No webhook</span>
                        @elseif(! $button->webhook->is_active)
                            <span class="badge badge-warning">Webhook off</span>
                        @elseif($button->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Off</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-pings-secondary js-edit-request-button" title="Edit"
                                    data-button="{{ json_encode([
                                        'id' => $button->id,
                                        'name' => $button->name,
                                        'type' => $button->type,
                                        'webhook_id' => $button->webhook_id,
                                        'mention_role' => $button->mention_role_id ? '<@&' . $button->mention_role_id . '>' : '',
                                        'ping_notice' => $button->ping_notice,
                                        'sections' => $button->sectionRows(),
                                        'cooldown_minutes' => $button->cooldown_minutes,
                                        'corporation_id' => $button->corporation_id,
                                        'alliance_id' => $button->alliance_id,
                                        'audience_permissions' => $button->audience_permissions ?? [],
                                        'sort_order' => $button->sort_order,
                                        'is_active' => $button->is_active,
                                    ]) }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-pings-secondary js-toggle-request-button" data-id="{{ $button->id }}" title="Switch on or off">
                                <i class="fas fa-power-off"></i>
                            </button>
                            <form method="POST" action="{{ route('discordpings.config.ping-requests.destroy', $button->id) }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" title="Delete"
                                        onclick="return confirm('Delete this button? Any forms members saved for it go with it.')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">No buttons yet. Add one, or load the examples to start from.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<form method="POST" action="{{ route('discordpings.config.ping-requests.destroy-many') }}" id="prb_delete_many" class="d-none">
    @csrf
    @method('DELETE')
</form>

{{-- Moved to the body before it opens. The wrapper class comes along so the button styles still apply out there. --}}
<div class="modal fade discord-pings-wrapper" id="pingRequestButtonModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="pingRequestButtonForm" action="{{ route('discordpings.config.ping-requests.store') }}">
                @csrf
                <input type="hidden" name="_method" id="prb_method" value="PUT" disabled>
                <div class="modal-header">
                    <h5 class="modal-title" id="prb_title">Add Button</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label for="prb_name">Button name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prb_name" name="name" maxlength="100" required placeholder="e.g. Help, I'm tackled">
                            <small class="text-muted">What members see, and the title of the Discord message.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="prb_type">Type</label>
                            <select class="form-control" id="prb_type" name="type">
                                <option value="request">Request</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                    {{-- Plain muted text, because the tinted alert is unreadable on a modal that a light theme makes white. --}}
                    <p class="text-muted" id="prb_type_help_emergency" style="display: none;">
                        <i class="fas fa-bolt"></i> An emergency posts the moment it's pressed. There's no confirm box and no cooldown.
                    </p>
                    <p class="text-muted" id="prb_type_help_request">
                        <i class="fas fa-clipboard-check"></i> A request shows the member what will be posted first, then waits out the cooldown before it can be pressed again.
                    </p>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="prb_webhook">Webhook</label>
                            <select class="form-control" id="prb_webhook" name="webhook_id">
                                <option value="">None yet</option>
                                @foreach($webhooks as $webhook)
                                    <option value="{{ $webhook->id }}">{{ $webhook->name }}{{ $webhook->is_active ? '' : ' (inactive)' }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Where the message goes. A button needs one to be switched on.</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="prb_mention_role">Role to ping</label>
                            <div style="display: flex; gap: 6px;">
                                <input type="text" class="form-control" id="prb_mention_role" name="mention_role" maxlength="64" placeholder="<@&123456789> or 123456789" style="flex: 1;">
                                @if(count($pickerRoles) > 0)
                                    <button type="button" class="btn btn-sm btn-pings-secondary js-toggle-inline-role-picker"
                                            data-picker-id="inlineRolePickerRequestButton" data-input-id="prb_mention_role" style="white-space: nowrap;">
                                        <i class="fas fa-tag"></i> Pick from Discord
                                    </button>
                                @endif
                            </div>
                            <small class="text-muted">Leave empty to post without pinging anyone. Only this role is ever pinged, whatever a member types.</small>
                        </div>
                    </div>

                    @if(count($pickerRoles) > 0)
                        <div id="inlineRolePickerRequestButton" class="inline-role-picker"
                             style="display: none; margin-bottom: 15px; padding: 12px; background: #1e222b; border: 1px solid #454d55; border-radius: 4px;">
                            <div class="inline-role-picker-body">
                                <div style="font-size: 0.78rem; color: #8b95a5; margin-bottom: 8px;">
                                    {{ count($pickerRoles) }} unique {{ \Illuminate\Support\Str::plural('role', count($pickerRoles)) }}
                                    from {{ $pickerSourceCounts->count() }} {{ \Illuminate\Support\Str::plural('source', $pickerSourceCounts->count()) }}:
                                    @foreach($pickerSourceCounts as $source => $sourceCount)
                                        <span class="badge" style="background: {{ $pickerSourceColours[$source] ?? '#666' }}; color: #000; font-weight: 700; font-size: 0.7rem; padding: 2px 6px; margin-left: 3px;">{{ \DiscordPings\Services\DiscordRoleResolver::providerShortLabel($source) }}: {{ $sourceCount }}</span>
                                    @endforeach
                                </div>
                                <div style="display: flex; gap: 6px; margin-bottom: 10px;">
                                    <input type="text" class="form-control form-control-sm js-inline-role-filter" placeholder="Search roles..." style="flex: 1; background: #1e222b; border: 1px solid #454d55; color: #fff;">
                                    @if($pickerSourceCounts->count() > 1)
                                        <select class="form-control form-control-sm js-inline-role-source" style="max-width: 180px; background: #1e222b; border: 1px solid #454d55; color: #fff;">
                                            <option value="">All sources</option>
                                            @foreach($pickerSourceCounts as $source => $sourceCount)
                                                <option value="{{ $source }}">{{ \DiscordPings\Services\DiscordRoleResolver::providerShortLabel($source) }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                {{-- The list scrolls on its own so the search box stays where it is on a big server. --}}
                                <div style="display: flex; flex-wrap: wrap; gap: 4px; max-height: 240px; overflow-y: auto;">
                                    @foreach($pickerRoles as $role)
                                        <button type="button" class="btn btn-sm btn-outline-primary js-role-pick-btn" style="text-align: left;"
                                                data-role-id="{{ $role['id'] }}" data-role-name="{{ $role['name'] }}" data-sources="{{ implode(' ', $role['sources']) }}">
                                            @if(! empty($role['color']) && preg_match('/^#[0-9a-f]{6}$/i', $role['color']))
                                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: {{ $role['color'] }}; margin-right: 6px; vertical-align: middle;"></span>
                                            @endif
                                            {{ $role['name'] }}
                                            <small style="opacity: 0.55; margin-left: 4px;">#{{ substr($role['id'], -6) }}</small>
                                            <span class="badge" style="background: {{ $pickerSourceColours[$role['source']] ?? '#666' }}; color: #000; font-weight: 700; font-size: 0.65rem; padding: 2px 6px; margin-left: 4px; vertical-align: middle;">{{ \DiscordPings\Services\DiscordRoleResolver::providerShortLabel($role['source']) }}</span>
                                            @if(count($role['sources']) > 1)
                                                <span class="badge badge-secondary" style="color: #fff; font-weight: 600; font-size: 0.65rem; padding: 2px 6px; margin-left: 2px;"
                                                      title="Also in: {{ collect($role['sources'])->reject(fn ($source) => $source === $role['source'])->map(fn ($source) => \DiscordPings\Services\DiscordRoleResolver::providerShortLabel($source))->implode(', ') }}">+{{ count($role['sources']) - 1 }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="form-group">
                        <label for="prb_ping_notice">Tell members who gets notified</label>
                        <input type="text" class="form-control" id="prb_ping_notice" name="ping_notice" maxlength="100" placeholder="e.g. Cap Umbrella">
                        <small class="text-muted">Optional. When filled in it shows on the button, for example Cap Umbrella or Designated SIG. It's never posted and doesn't change who gets pinged.</small>
                    </div>

                    <div class="form-group">
                        <label class="d-block">Section names (format)</label>
                        <div class="prb-start">
                            <span class="text-muted">Start from:</span>
                            @foreach($sectionStartingPoints as $startName => $startRows)
                                <button type="button" class="btn btn-sm btn-pings-secondary js-prb-start" data-rows="{{ json_encode($startRows) }}">{{ $startName }}</button>
                            @endforeach
                        </div>
                        <div class="prb-section-head text-muted">
                            <span class="prb-col-label">Section name</span>
                            <span class="prb-col-box">Box</span>
                            <span class="prb-col-live">Filled in</span>
                            <span class="prb-col-tools"></span>
                        </div>
                        <div id="prb_sections"></div>
                        <button type="button" class="btn btn-sm btn-pings-secondary" id="prb_add_section">
                            <i class="fas fa-plus"></i> Add a section
                        </button>
                        <small class="text-muted d-block mt-2">
                            Members fill these in from the top down. Paste your alliance's whole format into any section name and each line becomes its own row,
                            with the box already picked from its wording, so check those. A fixed line goes out as written. With no sections, members get one free text box.
                        </small>
                        <small class="text-muted d-block mt-1">
                            <strong>Filled in:</strong> a saved form keeps the answer, so one press can carry it.
                            <strong>After the press</strong> is for what's only true at that moment, like Panic or a d-scan, and no saved form keeps it.
                            An emergency asks it once the message is out and updates that same Discord message. A request asks it in the confirm box.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="d-block">Limit to Broadcast permissions</label>
                        <div class="row">
                            @foreach($audienceOptions as $permission => $permissionLabel)
                                <div class="col-md-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input js-prb-audience" id="prb_audience_{{ $loop->index }}" name="audience_permissions[]" value="{{ $permission }}">
                                        <label class="custom-control-label" for="prb_audience_{{ $loop->index }}">{{ $permissionLabel }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">Tick one or more and members need any one of them, on top of Line Member. Leave them all empty for every line member.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group" id="prb_cooldown_group">
                            <label for="prb_cooldown">Cooldown (minutes)</label>
                            <input type="number" class="form-control" id="prb_cooldown" name="cooldown_minutes" min="0" max="1440" value="15">
                            <small class="text-muted">Per member. 0 for none.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="prb_corporation">Corporation</label>
                            <select class="form-control" id="prb_corporation" name="corporation_id">
                                <option value="">Any</option>
                                @foreach($requestCorporations as $corporationId => $corporationName)
                                    <option value="{{ $corporationId }}">{{ $corporationName }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only members of this corporation see it.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="prb_alliance">Alliance</label>
                            <select class="form-control" id="prb_alliance" name="alliance_id">
                                <option value="">Any</option>
                                @foreach($requestAlliances as $allianceId => $allianceName)
                                    <option value="{{ $allianceId }}">{{ $allianceName }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Or members of this alliance. With both set, either one is enough.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="prb_sort_order">Order</label>
                            <input type="number" class="form-control" id="prb_sort_order" name="sort_order" min="0" max="9999" value="0">
                            <small class="text-muted">Lower comes first, after the emergencies.</small>
                        </div>
                        <div class="col-md-8 form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="prb_is_active" name="is_active" value="1">
                                <label class="custom-control-label" for="prb_is_active">Switched on</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-pings-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-pings-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('javascript')
<script>
$(document).ready(function () {
    var $modal = $('#pingRequestButtonModal');
    var $form = $('#pingRequestButtonForm');
    var storeUrl = @json(route('discordpings.config.ping-requests.store'));
    var buttonsUrl = @json(url('discord-pings/config/ping-requests'));
    var readFormatUrl = @json(route('discordpings.config.ping-requests.read-format'));
    var csrf = @json(csrf_token());
    var sectionBoxes = @json($sectionBoxes);
    var mostSections = @json(\DiscordPings\Services\PingRequests\Sections::MOST_ROWS);
    var longestLabel = @json(\DiscordPings\Services\PingRequests\Sections::LONGEST_LABEL);
    var $sections = $('#prb_sections');

    function sectionRow(row) {
        var $box = $('<select class="form-control form-control-sm js-prb-box" aria-label="Box">');
        var $live = $('<select class="form-control form-control-sm js-prb-live" aria-label="Filled in">')
            .append($('<option value="0">').text('Saved form'), $('<option value="1">').text('After the press'));

        $.each(sectionBoxes, function (value, label) {
            $box.append($('<option>').val(value).text(label));
        });

        var $row = $('<div class="prb-section-row">').append(
            $('<input type="text" class="form-control form-control-sm js-prb-label" aria-label="Section name">')
                .attr({ maxlength: longestLabel, placeholder: 'e.g. Ship Tackled, or paste a whole format' })
                .val(row.label || ''),
            $box.val(row.box || 'text'),
            $live.val(row.live ? '1' : '0'),
            $('<div class="btn-group btn-group-sm prb-row-tools">').append(
                $('<button type="button" class="btn btn-pings-secondary js-prb-up" title="Move up"><i class="fas fa-arrow-up"></i></button>'),
                $('<button type="button" class="btn btn-pings-secondary js-prb-down" title="Move down"><i class="fas fa-arrow-down"></i></button>'),
                $('<button type="button" class="btn btn-pings-secondary js-prb-remove" title="Remove"><i class="fas fa-times"></i></button>')
            )
        );

        showFilledIn($row);

        return $row;
    }

    // A fixed line has no box, so there's nothing to fill in.
    function showFilledIn($row) {
        var fixed = $row.find('.js-prb-box').val() === 'fixed';

        $row.find('.js-prb-live').prop('disabled', fixed).css('visibility', fixed ? 'hidden' : '');
    }

    function refreshSections() {
        if ($sections.children().length === 0) {
            $sections.append(sectionRow({}));
        }

        $('#prb_add_section').prop('disabled', $sections.children().length >= mostSections);
    }

    function setSections(rows) {
        $sections.empty();
        (rows || []).forEach(function (row) {
            $sections.append(sectionRow(row));
        });
        refreshSections();
    }

    function isEmpty($row) {
        return $row.find('.js-prb-label').val().trim() === '';
    }

    // Pasted rows go where the paste was, and an empty row they land on makes way for them.
    function placeRows($at, rows) {
        var room = mostSections - $sections.children().length + (isEmpty($at) ? 1 : 0);
        var $last = $at;

        rows.slice(0, Math.max(0, room)).forEach(function (row) {
            var $row = sectionRow(row);

            $last.after($row);
            $last = $row;
        });

        if (isEmpty($at) && $last !== $at) {
            $at.remove();
        }

        refreshSections();
    }

    $('#prb_add_section').on('click', function () {
        if ($sections.children().length < mostSections) {
            var $row = sectionRow({});

            $sections.append($row);
            refreshSections();
            $row.find('.js-prb-label').focus();
        }
    });

    $(document).on('click', '.js-prb-start', function () {
        var filled = $sections.children().filter(function () {
            return !isEmpty($(this));
        }).length;

        if (filled === 0 || confirm('Replace these sections with ' + $(this).text().trim() + '?')) {
            setSections($(this).data('rows'));
        }
    });

    $(document).on('click', '#prb_sections .js-prb-up', function () {
        var $row = $(this).closest('.prb-section-row');
        $row.prev().before($row);
    });

    $(document).on('click', '#prb_sections .js-prb-down', function () {
        var $row = $(this).closest('.prb-section-row');
        $row.next().after($row);
    });

    $(document).on('click', '#prb_sections .js-prb-remove', function () {
        $(this).closest('.prb-section-row').remove();
        refreshSections();
    });

    // These are only ever true at the moment, so a new one starts out asked after the press.
    $(document).on('change', '#prb_sections .js-prb-box', function () {
        var $row = $(this).closest('.prb-section-row');

        if ($(this).val() === 'yesno' || $(this).val() === 'long') {
            $row.find('.js-prb-live').val('1');
        }

        showFilledIn($row);
    });

    // Enter would otherwise save the whole button half done, so it starts the next section instead.
    $(document).on('keydown', '#prb_sections .js-prb-label', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        if ($sections.children().length < mostSections) {
            var $row = sectionRow({});

            $(this).closest('.prb-section-row').after($row);
            refreshSections();
            $row.find('.js-prb-label').focus();
        }
    });

    $(document).on('paste', '#prb_sections .js-prb-label', function (event) {
        var clipboard = event.originalEvent && event.originalEvent.clipboardData;
        var text = clipboard ? clipboard.getData('text') : '';
        var $row = $(this).closest('.prb-section-row');

        if (!/[\r\n]/.test(text.trim())) {
            return;
        }

        event.preventDefault();

        $.post(readFormatUrl, { _token: csrf, format: text })
            .done(function (rows) {
                placeRows($row, rows);
            })
            .fail(function () {
                // Still one row per line, just with every box left as plain text.
                placeRows($row, text.split(/\r?\n/).filter(function (line) {
                    return line.trim() !== '';
                }).map(function (line) {
                    return { label: line.trim(), box: 'text', live: false };
                }));
            });
    });

    // Numbered as they stand, so the order on screen is the order they're saved in.
    $form.on('submit', function () {
        $sections.children('.prb-section-row').each(function (index) {
            $(this).find('.js-prb-label').attr('name', 'sections[' + index + '][label]');
            $(this).find('.js-prb-box').attr('name', 'sections[' + index + '][box]');
            $(this).find('.js-prb-live').attr('name', 'sections[' + index + '][live]');
        });
    });

    function showTypeHelp() {
        var emergency = $('#prb_type').val() === 'emergency';
        $('#prb_type_help_emergency').toggle(emergency);
        $('#prb_type_help_request').toggle(!emergency);
        $('#prb_cooldown_group').toggle(!emergency);
    }

    function openModal(button) {
        $form.attr('action', button ? buttonsUrl + '/' + button.id : storeUrl);
        $('#prb_method').prop('disabled', !button);
        $('#prb_title').text(button ? 'Edit Button' : 'Add Button');

        $('#prb_name').val(button ? button.name : '');
        $('#prb_type').val(button ? button.type : 'request');
        $('#prb_webhook').val(button && button.webhook_id ? String(button.webhook_id) : '');
        $('#prb_mention_role').val(button ? button.mention_role : '');
        $('#prb_ping_notice').val(button && button.ping_notice ? button.ping_notice : '');
        setSections(button ? button.sections : []);
        $('#prb_cooldown').val(button ? button.cooldown_minutes : 15);
        $('#prb_corporation').val(button && button.corporation_id ? String(button.corporation_id) : '');
        $('#prb_alliance').val(button && button.alliance_id ? String(button.alliance_id) : '');
        $('.js-prb-audience').each(function () {
            $(this).prop('checked', !!button && button.audience_permissions.indexOf($(this).val()) !== -1);
        });
        $('#prb_sort_order').val(button ? button.sort_order : 0);
        $('#prb_is_active').prop('checked', button ? button.is_active : false);

        var $picker = $('#inlineRolePickerRequestButton').hide();
        $picker.find('.js-inline-role-filter, .js-inline-role-source').val('');
        $picker.find('.js-role-pick-btn').show();
        showTypeHelp();

        // AdminLTE's stacking puts a modal behind its own backdrop when it
        // opens from inside the page, so it has to live under the body.
        $modal.appendTo('body').modal('show');
    }

    $('#prb_type').on('change', showTypeHelp);

    $('.js-add-request-button').on('click', function () {
        openModal(null);
    });

    $('.js-edit-request-button').on('click', function () {
        openModal($(this).data('button'));
    });

    $('.js-toggle-request-button').on('click', function () {
        $.post(buttonsUrl + '/' + $(this).data('id') + '/toggle', { _token: csrf })
            .done(function () {
                location.reload();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not switch that button.');
            });
    });

    var $deleteMany = $('#prb_delete_many');

    function tickedIds() {
        return $('.js-prb-tick:checked').map(function () {
            return this.value;
        }).get();
    }

    function refreshTicks() {
        var all = $('.js-prb-tick').length;
        var ticked = tickedIds().length;

        $('.js-prb-tick-all').prop('checked', all > 0 && ticked === all).prop('indeterminate', ticked > 0 && ticked < all);
        $('.js-prb-delete-ticked').prop('disabled', ticked === 0);
        $('.js-prb-ticked-count').text(ticked > 0 ? '(' + ticked + ')' : '');
    }

    function deleteMany(fields, question) {
        if (! confirm(question)) {
            return;
        }

        $deleteMany.find('.js-prb-delete-field').remove();
        fields.forEach(function (field) {
            $deleteMany.append($('<input type="hidden" class="js-prb-delete-field">').attr('name', field.name).val(field.value));
        });
        $deleteMany[0].submit();
    }

    $('.js-prb-tick-all').on('change', function () {
        $('.js-prb-tick').prop('checked', this.checked);
        refreshTicks();
    });

    $('.js-prb-tick').on('change', refreshTicks);

    $('.js-prb-delete-ticked').on('click', function () {
        var ids = tickedIds();

        if (ids.length === 0) {
            return;
        }

        deleteMany(ids.map(function (id) {
            return { name: 'ids[]', value: id };
        }), ids.length === 1
            ? 'Delete the ticked button? Any forms members saved for it go with it.'
            : 'Delete the ' + ids.length + ' ticked buttons? Any forms members saved for them go with them.');
    });

    $('.js-prb-delete-all').on('click', function () {
        var all = $('.js-prb-tick').length;

        deleteMany([{ name: 'all', value: '1' }], all === 1
            ? 'Delete the only button? Any forms members saved for it go with it.'
            : 'Delete all ' + all + ' buttons? Any forms members saved for them go with them.');
    });

    refreshTicks();

    $(document).on('click', '.js-toggle-inline-role-picker', function () {
        var $picker = $('#' + $(this).attr('data-picker-id'));
        $picker.attr('data-target-input', $(this).attr('data-input-id')).slideToggle(150);
    });

    $(document).on('click', '.js-role-pick-btn', function () {
        var $picker = $(this).closest('.inline-role-picker');
        $('#' + $picker.attr('data-target-input')).val('<@&' + $(this).attr('data-role-id') + '>');
        $picker.slideUp(150);
    });

    $(document).on('input change', '.js-inline-role-filter, .js-inline-role-source', function () {
        var $picker = $(this).closest('.inline-role-picker');
        var needle = $picker.find('.js-inline-role-filter').val().toLowerCase();
        var source = $picker.find('.js-inline-role-source').val() || '';

        $picker.find('.js-role-pick-btn').each(function () {
            var matches = ($(this).attr('data-role-name') + ' ' + $(this).attr('data-role-id')).toLowerCase().indexOf(needle) !== -1;
            var inSource = source === '' || $(this).attr('data-sources').split(' ').indexOf(source) !== -1;
            $(this).toggle(matches && inSource);
        });
    });
});
</script>
@endpush
