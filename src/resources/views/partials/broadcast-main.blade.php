{{--
    The boxes every broadcast form shares, so Send, Schedule and Templates
    carry the same ones and broadcast-form.js can fill and read all of them.
    Values arrive through BroadcastForm.init(), not here.

    $multipleWebhooks  the list for sending to several webhooks at once
    $webhookRequired   whether a webhook has to be picked
--}}
@php
    $multipleWebhooks = $multipleWebhooks ?? false;
    $webhookRequired = $webhookRequired ?? true;
    $embedTypes = ['fleet' => '📢 Fleet Broadcast', 'announcement' => '📣 Announcement', 'message' => '💬 Message', 'prepping' => '‼️ PREPING ‼️'];
    $linkedAccounts = \DiscordPings\Services\DiscordUserResolver::isAvailable();
    $memberPicker = $linkedAccounts && auth()->user()->can('discordpings.send');
    $mentionHelp = $linkedAccounts
        ? 'Type @ and a member\'s main or Discord name, pick them from the list, or paste their Discord user ID. Several can go in one box.'
        : 'To ping a member, paste their Discord user ID: with Developer Mode on in Discord, right-click their name and pick Copy User ID. A typed @name pings nobody without SeAT Connector.';
@endphp

<div class="form-group" id="singleWebhookDiv">
    <label for="webhookSelect">
        Target Webhook
        @if ($webhookRequired)
            <span class="text-danger">*</span>
        @endif
    </label>
    <select name="webhook_id" class="form-control" id="webhookSelect" {{ $webhookRequired ? 'required' : '' }}>
        <option value="">{{ $webhookRequired ? 'Select Channel...' : 'None, pick it when sending' }}</option>
        @foreach ($webhooks ?? [] as $webhook)
            <option value="{{ $webhook->id }}" data-color="{{ $webhook->embed_color }}">
                {{ $webhook->name }}{{ $webhook->channel_type ? ' (' . $webhook->channel_type . ')' : '' }}
            </option>
        @endforeach
    </select>
</div>

@if ($multipleWebhooks)
    <div class="form-group" id="multipleWebhooksDiv" style="display: none;">
        <label>
            Select Target Webhooks
            @if ($webhookRequired)
                <span class="text-danger">*</span>
            @endif
        </label>
        @foreach ($webhooks ?? [] as $webhook)
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" disabled
                       id="webhook_check_{{ $webhook->id }}"
                       name="webhook_ids[]"
                       value="{{ $webhook->id }}">
                <label class="custom-control-label" for="webhook_check_{{ $webhook->id }}">
                    {{ $webhook->name }}
                    @if ($webhook->channel_type)
                        <small class="text-muted">({{ $webhook->channel_type }})</small>
                    @endif
                </label>
            </div>
        @endforeach
    </div>
@endif

<div class="form-group">
    <label for="embedType">Broadcast Type</label>
    <select name="embed_type" class="form-control" id="embedType">
        @foreach ($embedTypes as $embedValue => $embedLabel)
            <option value="{{ $embedValue }}">{{ $embedLabel }}</option>
        @endforeach
    </select>
    <small class="form-text text-muted">
        Choose the type of broadcast to display in Discord
    </small>
</div>

<div class="form-group">
    <label for="broadcastMessage">Message <span class="text-danger">*</span></label>
    <textarea name="message" id="broadcastMessage" class="form-control" rows="12" required
              maxlength="2000" data-message-tools></textarea>
    <small class="form-text text-muted">
        <span id="charCount">0</span>/2000 characters
    </small>
</div>

<div class="form-group">
    <label for="mentionType">Mentions</label>
    <select name="mention_type" class="form-control" id="mentionType">
        <option value="none">No Mention</option>
        <option value="everyone">@everyone</option>
        <option value="here">@here</option>
        <option value="role">Discord Role</option>
        <option value="custom">Custom</option>
    </select>
</div>

<div class="form-group" id="roleMentionDiv" style="display: none;">
    <label>Select Role to Mention</label>
    <select name="role_mention" class="form-control">
        <option value="">Select Role...</option>
        @foreach ($roles ?? [] as $role)
            <option value="{{ $role->id }}">{{ $role->name }}</option>
        @endforeach
    </select>
</div>

<div class="form-group" id="customMentionDiv" style="display: none;">
    <label for="customMention">Custom Mention</label>
    <div class="bf-mention-row">
        <input type="text" name="custom_mention" id="customMention" class="form-control" autocomplete="off"
               maxlength="{{ \DiscordPings\Services\MemberMentions::MAX_LENGTH }}"
               placeholder="{{ $linkedAccounts ? '@Main Name, @discord_name or a Discord user ID' : 'A Discord user ID, or @everyone and @here' }}">
        @if ($memberPicker)
            <button type="button" class="btn btn-sm btn-pings-secondary text-nowrap" id="memberPickerToggle"
                    aria-expanded="false" aria-controls="memberPicker">
                <i class="fas fa-user"></i> Pick from Discord
            </button>
        @endif
    </div>
    @if ($memberPicker)
        <div id="memberPicker" class="bf-member-picker" style="display: none;">
            <input type="search" class="form-control form-control-sm" id="memberPickerFilter"
                   placeholder="Search by main or Discord name" aria-label="Search the linked members">
            <div class="bf-member-list" id="memberPickerList"></div>
        </div>
    @endif
    <small class="form-text text-muted" id="customMentionHint" data-help="{{ $mentionHelp }}">{{ $mentionHelp }}</small>
</div>

<div class="form-group">
    <label for="channelSelect">Discord Channel (Optional)</label>
    <select name="channel_link" class="form-control" id="channelSelect">
        <option value="">No Discord Channel...</option>
        @foreach ($channels ?? [] as $channel)
            <option value="{{ $channel->id }}">#{{ $channel->name }} ({{ ucfirst($channel->channel_type) }})</option>
        @endforeach
    </select>
    <small class="form-text text-muted">
        <i class="fas fa-info-circle"></i> Select a pre-configured Discord channel.
        <strong>If you select a channel here, leave the Comms field empty</strong> to avoid duplicate entries.
    </small>
</div>

<div class="form-group">
    <label for="embedColor">Embed Color</label>
    <div class="input-group">
        <div class="input-group-prepend">
            <span class="input-group-text p-1">
                <input type="color" id="colorPicker" value="#5865F2" aria-label="Pick the embed color"
                       style="width:34px;height:34px;padding:2px;border:none;cursor:pointer;background:none;">
            </span>
        </div>
        <input type="text" name="embed_color" class="form-control"
               id="embedColor" value="#5865F2" pattern="^#[0-9A-Fa-f]{6}$" placeholder="#RRGGBB">
    </div>
    <small class="form-text text-muted">Use the color picker or type a hex code directly</small>
</div>
