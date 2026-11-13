{{--
    What broadcast-form.js and message-tools.js need, for any page using the
    broadcast partials. Pass $suggestions from BroadcastForm::suggestions(),
    and $singleAction with $multipleAction where the form posts each webhook
    mode to its own route.
--}}
@php
    $canLookUp = auth()->user()->can('discordpings.send');
    $broadcastFormConfig = [
        'systemsUrl' => $canLookUp ? route('discordpings.send.systems') : null,
        'locationsUrl' => $canLookUp ? url('discord-pings/send/systems/__ID__/locations') : null,
        'characters' => $suggestions['characters'] ?? [],
        'typedBefore' => $suggestions['typedBefore'] ?? [],
        'singleAction' => $singleAction ?? null,
        'multipleAction' => $multipleAction ?? null,
    ];
@endphp
<script>
    window.BROADCAST_FORM = @json($broadcastFormConfig);
</script>
<script src="{{ asset('vendor/discordpings/js/message-tools.js') }}?v=1"></script>
<script src="{{ asset('vendor/discordpings/js/broadcast-form.js') }}?v=1"></script>
