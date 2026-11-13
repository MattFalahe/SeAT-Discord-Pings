{{-- Settings > Send Form: how the Send and Schedule forms start. --}}
@php
    $sendDefaultsOn = ($sendFormMode ?? 'empty') === 'defaults';
    $saved = $sendFormDefaults ?? [];
@endphp

<h4>Send Form</h4>
<p class="text-muted">
    How the Send and Schedule pages start. FCs can change anything before they send.
    The message is never a default: it only starts filled in when an FC answers a ping request,
    and each Ping Requests button can set its own starting point for that.
</p>

<form method="POST" action="{{ route('discordpings.config.send-defaults') }}" id="sendFormDefaults">
    @csrf

    <div class="form-group">
        <label class="d-block">When the form opens</label>
        <div class="custom-control custom-radio">
            <input type="radio" class="custom-control-input js-send-form-mode" id="send_form_mode_empty"
                   name="send_form_mode" value="empty" {{ $sendDefaultsOn ? '' : 'checked' }}>
            <label class="custom-control-label" for="send_form_mode_empty">Start empty</label>
        </div>
        <div class="custom-control custom-radio">
            <input type="radio" class="custom-control-input js-send-form-mode" id="send_form_mode_defaults"
                   name="send_form_mode" value="defaults" {{ $sendDefaultsOn ? 'checked' : '' }}>
            <label class="custom-control-label" for="send_form_mode_defaults">Start from the defaults below</label>
        </div>
    </div>

    {{-- Dimmed while the form starts empty, and still editable so the defaults can be ready before they go on. --}}
    <div id="sendFormDefaultsBody" class="send-form-defaults{{ $sendDefaultsOn ? '' : ' is-off' }}">
        <div class="row">
            <div class="col-md-6 form-group">
                <label for="send_default_webhook_id">Webhook</label>
                <select class="form-control" id="send_default_webhook_id" name="webhook_id">
                    <option value="">No default</option>
                    @foreach ($webhooks->where('is_active', true) as $webhook)
                        <option value="{{ $webhook->id }}" {{ (int) ($saved['webhook_id'] ?? 0) === (int) $webhook->id ? 'selected' : '' }}>{{ $webhook->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 form-group">
                <label for="send_default_embed_type">Broadcast type</label>
                <select class="form-control" id="send_default_embed_type" name="embed_type">
                    <option value="">No default</option>
                    @foreach (['fleet' => '📢 Fleet Broadcast', 'announcement' => '📣 Announcement', 'message' => '💬 Message', 'prepping' => '‼️ PREPING ‼️'] as $embedValue => $embedLabel)
                        <option value="{{ $embedValue }}" {{ ($saved['embed_type'] ?? '') === $embedValue ? 'selected' : '' }}>{{ $embedLabel }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 form-group">
                <label for="send_default_mention_type">Mentions</label>
                <select class="form-control" id="send_default_mention_type" name="mention_type">
                    <option value="">No default</option>
                    @foreach (['none' => 'No Mention', 'everyone' => '@everyone', 'here' => '@here', 'role' => 'Discord Role', 'custom' => 'Custom'] as $mentionValue => $mentionLabel)
                        <option value="{{ $mentionValue }}" {{ ($saved['mention_type'] ?? '') === $mentionValue ? 'selected' : '' }}>{{ $mentionLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="send_default_role_mention">Role, for Discord Role</label>
                <select class="form-control" id="send_default_role_mention" name="role_mention">
                    <option value="">No default</option>
                    @foreach ($roles->where('is_active', true) as $role)
                        <option value="{{ $role->id }}" {{ (int) ($saved['role_mention'] ?? 0) === (int) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="send_default_custom_mention">Custom mention</label>
                <input type="text" class="form-control" id="send_default_custom_mention" name="custom_mention" maxlength="100"
                       value="{{ $saved['custom_mention'] ?? '' }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label for="send_default_channel_link">Discord channel</label>
                <select class="form-control" id="send_default_channel_link" name="channel_link">
                    <option value="">No default</option>
                    @foreach ($channels->where('is_active', true) as $channel)
                        <option value="{{ $channel->id }}" {{ (int) ($saved['channel_link'] ?? 0) === (int) $channel->id ? 'selected' : '' }}>#{{ $channel->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 form-group">
                <label for="send_default_embed_color">Embed color</label>
                <input type="text" class="form-control" id="send_default_embed_color" name="embed_color" maxlength="7"
                       pattern="^#[0-9A-Fa-f]{6}$" placeholder="No default, or #RRGGBB" value="{{ $saved['embed_color'] ?? '' }}">
                <small class="text-muted">Empty uses the webhook's own color.</small>
            </div>
        </div>

        <div class="form-group">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="send_default_fc_main" name="fc_main" value="1"
                       {{ ! empty($saved['fc_main']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="send_default_fc_main">Start FC Name as the sender's main character</label>
            </div>
            <small class="text-muted">The FC can pick another of their characters or type any name.</small>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label for="send_default_formup_location">Formup location</label>
                <input type="text" class="form-control" id="send_default_formup_location" name="formup_location" maxlength="100"
                       list="send_default_stagings" autocomplete="off" value="{{ $saved['formup_location'] ?? '' }}">
                <datalist id="send_default_stagings">
                    @foreach ($stagings->where('is_active', true) as $staging)
                        <option value="{{ $staging->getFullLocationString() }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="col-md-6 form-group">
                <label for="send_default_pap_type">PAP type</label>
                <select class="form-control" id="send_default_pap_type" name="pap_type">
                    <option value="">No default</option>
                    @foreach ($papTypes->where('is_active', true) as $pap)
                        <option value="{{ $pap->name }}" {{ ($saved['pap_type'] ?? '') === $pap->name ? 'selected' : '' }}>{{ $pap->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label for="send_default_comms">Comms</label>
                <input type="text" class="form-control" id="send_default_comms" name="comms" maxlength="200"
                       value="{{ $saved['comms'] ?? '' }}">
            </div>
            <div class="col-md-6 form-group">
                <label for="send_default_doctrine">Doctrine</label>
                <input type="text" class="form-control" id="send_default_doctrine" name="doctrine" maxlength="200"
                       list="send_default_doctrines" autocomplete="off" value="{{ $saved['doctrine'] ?? '' }}">
                <datalist id="send_default_doctrines">
                    @foreach ($doctrineNames ?? [] as $doctrineName)
                        <option value="{{ $doctrineName }}"></option>
                    @endforeach
                </datalist>
                <small class="text-muted">A SeAT Fitting doctrine's name picks it from the list on the form.</small>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-pings-primary">
        <i class="fas fa-save"></i> Save
    </button>
</form>

@push('javascript')
<script>
$(function () {
    $('.js-send-form-mode').on('change', function () {
        $('#sendFormDefaultsBody').toggleClass('is-off', $('#send_form_mode_defaults').prop('checked') !== true);
    });
});
</script>
@endpush
