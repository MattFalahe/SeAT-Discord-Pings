{{-- Every plugin SeAT Broadcast works with and whether this server has it:
     the crucial ones, a divider, then the optional ones. --}}
@php
    $about = [
        'manager-core' => [
            'icon' => 'fa-network-wired',
            'url' => 'https://github.com/MattFalahe/manager-core',
            'text' => 'The hub of the plugin ecosystem. It carries events and data between the plugins, so each one can use what the others know.',
            'here' => 'Structure Manager\'s timers and Mining Manager\'s moon extractions come in through it, and the broadcasts sent here go out through it to HR Manager. Without it, SeAT Broadcast still sends, schedules and takes ping requests on its own.',
        ],
        'structure-manager' => [
            'icon' => 'fa-building',
            'url' => 'https://github.com/MattFalahe/Structure-Manager',
            'text' => 'Keeps track of your corporations\' structures: fuel, reinforcement timers, and the fleet ops planned around them.',
            'here' => 'Its timers and fleet ops fill the FC Opportunities board, and webhooks you flag get a reminder 24 hours and 1 hour before each timer. FC Opportunities shows in the menu once this and Manager Core are both installed.',
        ],
        'mining-manager' => [
            'icon' => 'fa-gem',
            'url' => 'https://github.com/MattFalahe/Mining-Manager',
            'text' => 'Moon mining for your corporations: extractions, the mining ledger and mining tax.',
            'here' => 'Its moon extractions show on FC Opportunities, and webhooks you flag get an alert 2 hours before an extraction\'s window closes. It needs 2.0.1 or later.',
        ],
        'hr-manager' => [
            'icon' => 'fa-users',
            'url' => 'https://github.com/MattFalahe/HR-Manager',
            'text' => 'A recruitment funnel for applicants, and a console for directors to keep track of member health and activity.',
            'here' => 'It counts the broadcasts FCs send from here, and the form-ups they schedule for an op, toward each FC\'s activity. There is nothing to set up on this side.',
        ],
        'seat-connector' => [
            'icon' => 'fa-plug',
            'url' => 'https://github.com/zenobio93/seat-connector',
            'text' => 'Links SeAT accounts to your Discord server and knows its roles.',
            'here' => 'Ping requests name the sender\'s Discord account, and Settings offers your Discord roles to pick from instead of typing their IDs. The older Warlof Discord connector covers the roles, but not the Discord account.',
        ],
        'seat-fitting' => [
            'icon' => 'fa-clipboard-list',
            'url' => 'https://github.com/eveseat-plugins/seat-fitting',
            'text' => 'Keeps your fittings and doctrines in SeAT.',
            'here' => 'The send, schedule and template forms offer your doctrines, and the one you pick goes out as a link to it. Denngarr\'s original works as well as CryptaTech\'s.',
        ],
    ];
    $groups = [
        'crucial' => ['Crucial', 'Needed for full functionality.'],
        'optional' => ['Optional', 'Each adds to a feature or two. SeAT Broadcast works without them.'],
    ];
    $inGroup = array_count_values(array_column($ecosystem['plugins'], 'role'));
@endphp
<div class="help-card">
    <h3><i class="fas fa-puzzle-piece"></i> Plugins &amp; Ecosystem
        <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
    </h3>
    <p>SeAT Broadcast works on its own. These plugins add to it, and each one says whether this server has it, checked when this page opens.</p>

    @foreach($groups as $role => [$title, $line])
        @if(! $loop->first)
            <hr class="eco-divider">
        @endif
        <h4 class="eco-group">{{ $title }}</h4>
        <p class="eco-group-line">{{ ($inGroup[$role] ?? 0) > 0 ? $line : 'None.' }}</p>
        @foreach($ecosystem['plugins'] as $key => $plugin)
            @continue($plugin['role'] !== $role)
            <div class="eco-plugin">
                <div class="eco-plugin-head">
                    <i class="fas {{ $about[$key]['icon'] }}"></i>
                    <strong>{{ $plugin['name'] }}</strong>
                    <span class="eco-badges">
                        @foreach($plugin['badges'] as $badge)
                            <span class="badge badge-{{ $badge['tone'] }}{{ $badge['pulse'] ? ' eco-pulse' : '' }}">{{ $badge['label'] }}</span>
                        @endforeach
                    </span>
                </div>
                <p>{{ $about[$key]['text'] }}</p>
                <p><strong>In SeAT Broadcast:</strong> {{ $about[$key]['here'] }}</p>
                <a href="{{ $about[$key]['url'] }}" target="_blank" rel="noopener" class="eco-link"><i class="fab fa-github"></i> {{ $plugin['name'] }} on GitHub</a>
            </div>
        @endforeach
    @endforeach
</div>
