{{-- Features that need another plugin get boxes of their own, so a missing
     plugin shows on the feature it takes away. Red: a crucial plugin is
     missing, which here means Manager Core. Yellow: only an optional one is. --}}
@php
    $hub = $ecosystem['hub'];
    $boxes = [
        'structure-timers' => [
            'icon' => 'fa-satellite-dish',
            'title' => 'Structure Timers',
            'text' => 'Reinforcement, anchoring and fuel timers from Structure Manager, and the fleet ops entered there, reach FC Opportunities as they change. Webhooks you flag get a reminder 24 hours and 1 hour before each timer.',
        ],
        'fc-opportunities' => [
            'icon' => 'fa-bullseye',
            'title' => 'FC Opportunities Board',
            'text' => 'Upcoming timers and fleet ops, and moon extractions with Mining Manager, on one board with countdowns, and the requests line members sent in the last day above them. Each op becomes a broadcast filled in from it: Form-up to send it now, Schedule to send it later. Form-up works from a request too.',
        ],
        'moon-extractions' => [
            'icon' => 'fa-gem',
            'title' => 'Moon Extractions',
            'text' => 'Moon extractions from Mining Manager 2.0.1 or later show on FC Opportunities with the window a fleet has to mine them. Webhooks you flag get an alert 2 hours before the window closes.',
        ],
        'fc-activity' => [
            'icon' => 'fa-user-check',
            'title' => 'FC Activity in HR Manager',
            'text' => 'HR Manager counts the broadcasts FCs send from here, and the form-ups they schedule for an op, toward each FC\'s activity. Ping requests don\'t count.',
        ],
        'sender-discord' => [
            'icon' => 'fa-id-badge',
            'title' => 'Sender\'s Discord Account',
            'text' => 'A ping request names the Discord account of the member who sent it, next to their SeAT main and corporation, so an FC can reach them straight away.',
            'until' => 'Until then, a request names the SeAT main and corporation.',
            'new' => true,
        ],
        'role-list' => [
            'icon' => 'fa-user-tag',
            'title' => 'Discord Roles by Name',
            'text' => 'Settings > Discord Roles offers your Discord server\'s roles to add, and a request button\'s role list shows them too.',
            'until' => 'Until then, add a role by typing its ID.',
        ],
        'doctrines' => [
            'icon' => 'fa-clipboard-list',
            'title' => 'Doctrines',
            'text' => 'The send, schedule and template forms offer your doctrines to pick from, and the one you pick goes out as a link to it in SeAT.',
            'until' => 'Until then, type the doctrine as text.',
        ],
    ];
    foreach ($boxes as $key => $box) {
        $boxes[$key] += $ecosystem['features'][$key] + ['until' => null, 'new' => false];
    }
@endphp
<h4 class="eco-heading"><i class="fas fa-project-diagram"></i> With other plugins</h4>
<p>These need another plugin. <a href="#plugins" data-goto="plugins">Plugins &amp; Ecosystem</a> says what each one adds and whether this server has it.</p>
<div class="feature-grid">
    <div class="feature-item eco-box eco-hub eco-{{ $hub['state'] }}{{ $hub['state'] !== 'on' ? ' eco-pulse' : '' }}">
        @if($hub['banner'])
            <span class="eco-banner">{{ $hub['banner'] }}</span>
        @endif
        <h5><i class="fas fa-network-wired"></i> Manager Core: The Ecosystem Era</h5>
        <p>Manager Core links the plugins: what happens in one reaches the others, one can ask another for what it knows, and they share prices instead of each fetching its own.</p>
        <p>Through it, Structure Manager's timers and Mining Manager's moon extractions reach SeAT Broadcast as they change, and the broadcasts sent here reach HR Manager.</p>
        @if(! $hub['installed'])
            <p class="eco-note">Manager Core is not installed on this server.</p>
        @endif
    </div>
    @foreach($boxes as $box)
        <div class="feature-item eco-box eco-{{ $box['state'] }}{{ $box['state'] !== 'on' ? ' eco-pulse' : '' }}">
            @if($box['banner'])
                <span class="eco-banner">{{ $box['banner'] }}</span>
            @endif
            <h5><i class="fas {{ $box['icon'] }}"></i> {{ $box['title'] }}
                @if($box['new'])
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                @endif
            </h5>
            <p>{{ $box['text'] }}</p>
            @if($box['state'] === 'on')
                <span class="badge badge-success eco-tag">With {{ $box['using'] }}</span>
            @else
                <p class="eco-note">Needs {{ $box['needs'] }}.{{ $box['until'] ? ' ' . $box['until'] : '' }}</p>
            @endif
        </div>
    @endforeach
</div>
