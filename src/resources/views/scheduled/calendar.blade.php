@extends('web::layouts.grids.12')

@section('title', 'Broadcasts Calendar')
@section('page_header', 'Broadcasts Calendar')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/fullcalendar.min.css') }}">
<style>
    #calendar {
        max-width: 100%;
        margin: 0 auto;
    }

    /* Dark theme overrides for FullCalendar */
    .fc {
        --fc-border-color: rgba(255, 255, 255, 0.1);
        --fc-button-bg-color: #5865F2;
        --fc-button-border-color: #4752C4;
        --fc-button-hover-bg-color: #4752C4;
        --fc-button-hover-border-color: #3C45A5;
        --fc-button-active-bg-color: #3C45A5;
        --fc-button-active-border-color: #3C45A5;
        --fc-event-bg-color: #5865F2;
        --fc-event-border-color: #4752C4;
        --fc-today-bg-color: rgba(88, 101, 242, 0.1);
        --fc-neutral-bg-color: #2d3748;
        --fc-page-bg-color: transparent;
        --fc-list-event-hover-bg-color: rgba(88, 101, 242, 0.2);
    }

    /* Week view - make time slots taller to show more events */
    .fc-timegrid-slot {
        height: 3em !important;
    }

    /* Allow events to stack better in week view */
    .fc-timegrid-event {
        font-size: 0.8em;
        padding: 2px 4px;
    }

    .fc-timegrid-event .fc-event-title {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Day columns in week view - allow more events side by side */
    .fc-timegrid-col-events {
        margin: 0 1px !important;
    }

    /* Remove scrollbars from calendar */
    .fc-scroller {
        overflow: visible !important;
    }

    .fc-scroller-liquid-absolute {
        position: relative !important;
        overflow: visible !important;
    }

    .fc .fc-col-header-cell {
        background: rgba(88, 101, 242, 0.15);
    }

    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number,
    .fc .fc-list-day-text,
    .fc .fc-list-day-side-text {
        color: #e2e8f0;
    }

    .fc .fc-daygrid-day.fc-day-other .fc-daygrid-day-number {
        color: #6b7280;
    }

    .fc-event {
        cursor: pointer;
        font-size: 0.85em;
        border-radius: 3px;
    }

    .fc .fc-toolbar-title {
        color: #e2e8f0;
    }

    .webhook-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
    }

    .webhook-legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9rem;
        color: #d1d5db;
    }

    .webhook-legend-color {
        width: 14px;
        height: 14px;
        border-radius: 3px;
        flex-shrink: 0;
    }

    /* Event detail modal */
    .event-detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .event-detail-row:last-child {
        border-bottom: none;
    }

    .event-detail-label {
        font-weight: 600;
        color: #9ca3af;
        width: 120px;
        flex-shrink: 0;
    }

    .event-detail-value {
        color: #e2e8f0;
    }
</style>
@endpush

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Calendar</h3>
            <div class="card-tools">
                <a href="{{ route('discordpings.scheduled') }}" class="btn btn-sm btn-outline-secondary mr-1">
                    <i class="fas fa-list"></i> List View
                </a>
                <a href="{{ route('discordpings.scheduled.create') }}" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Schedule New
                </a>
            </div>
        </div>
        <div class="card-body">
            {{-- Webhook color legend --}}
            @if(($webhooks ?? collect())->count() > 0)
                <div class="webhook-legend">
                    @foreach($webhooks as $webhook)
                        <div class="webhook-legend-item">
                            <div class="webhook-legend-color" style="background-color: {{ $webhook->embed_color }}"></div>
                            {{ $webhook->name }}
                        </div>
                    @endforeach
                </div>
            @endif

            <div id="calendar"></div>
        </div>
    </div>

    {{-- Event Detail Modal --}}
    <div class="modal fade" id="eventDetailModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clock"></i> Scheduled Broadcast</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="event-detail-row">
                        <div class="event-detail-label">Time (EVE)</div>
                        <div class="event-detail-value" id="eventTime"></div>
                    </div>
                    <div class="event-detail-row">
                        <div class="event-detail-label">Webhook</div>
                        <div class="event-detail-value" id="eventWebhook"></div>
                    </div>
                    <div class="event-detail-row">
                        <div class="event-detail-label">Repeat</div>
                        <div class="event-detail-value" id="eventRepeat"></div>
                    </div>
                    <div class="event-detail-row" id="eventRepeatUntilRow" style="display:none">
                        <div class="event-detail-label">Until</div>
                        <div class="event-detail-value" id="eventRepeatUntil"></div>
                    </div>
                    <div class="event-detail-row">
                        <div class="event-detail-label">Times Sent</div>
                        <div class="event-detail-value" id="eventTimesSent"></div>
                    </div>
                    <div class="event-detail-row">
                        <div class="event-detail-label">Message</div>
                        <div class="event-detail-value" id="eventMessage" style="white-space: pre-wrap;"></div>
                    </div>
                    <div id="eventFieldsContainer"></div>
                </div>
                <div class="modal-footer">
                    <a id="editPingBtn" href="#" class="btn btn-info" style="display:none;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@push('javascript')
<script src="{{ asset('vendor/discordpings/js/vendor/fullcalendar.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: {
            url: '{{ route("discordpings.api.scheduled-events") }}',
            failure: function() {
                alert('Failed to load scheduled events.');
            }
        },
        eventContent: function(arg) {
            var props = arg.event.extendedProps;

            var typeIcons = {
                'fleet':        '📢',
                'announcement': '📣',
                'message':      '💬',
                'prepping':     '‼️ PREPING ‼️',
            };
            var embedType = (props.fields && props.fields.embed_type) ? props.fields.embed_type : '';
            var typeIcon = typeIcons[embedType] || '📡';

            var d = arg.event.start;
            var hh = String(d.getUTCHours()).padStart(2,'0');
            var mm = String(d.getUTCMinutes()).padStart(2,'0');
            var timeStr = hh + ':' + mm;

            var el = document.createElement('div');
            el.style.display = 'flex';
            el.style.alignItems = 'center';
            el.style.gap = '4px';
            el.style.padding = '1px 4px';
            el.style.overflow = 'hidden';
            el.style.width = '100%';

            if (props.isHistory) {
                // Manual broadcast: coloured dot + type icon + time + message
                var dot = document.createElement('span');
                dot.style.display = 'inline-block';
                dot.style.width = '8px';
                dot.style.height = '8px';
                dot.style.borderRadius = '50%';
                dot.style.background = props.webhookColor;
                dot.style.flexShrink = '0';
                el.appendChild(dot);
            } else {
                // Scheduled broadcast: calendar icon to mark it as scheduled
                var calIcon = document.createElement('span');
                calIcon.style.fontSize = '0.7rem';
                calIcon.style.flexShrink = '0';
                calIcon.textContent = '📅';
                el.appendChild(calIcon);
            }

            var iconEl = document.createElement('span');
            iconEl.style.fontSize = '0.75rem';
            iconEl.style.flexShrink = '0';
            iconEl.textContent = typeIcon;

            var timeEl = document.createElement('span');
            timeEl.style.fontSize = '0.7rem';
            timeEl.style.flexShrink = '0';
            timeEl.style.opacity = '0.8';
            timeEl.textContent = timeStr;

            var text = document.createElement('span');
            text.style.fontSize = '0.78rem';
            text.style.overflow = 'hidden';
            text.style.textOverflow = 'ellipsis';
            text.style.whiteSpace = 'nowrap';
            text.textContent = arg.event.title;

            el.appendChild(iconEl);
            el.appendChild(timeEl);
            el.appendChild(text);

            return { domNodes: [el] };
        },
        eventDidMount: function(info) {
            var props = info.event.extendedProps;
            if (!props.isActive && !props.isHistory) {
                // Sent scheduled ping: dim and strikethrough
                info.el.style.opacity = '0.5';
                info.el.style.textDecoration = 'line-through';
            }
            if (props.isHistory) {
                // Manual broadcast: no background bar
                info.el.style.backgroundColor = 'transparent';
                info.el.style.borderColor = 'transparent';
                info.el.style.boxShadow = 'none';
                info.el.style.opacity = '0.75';
            }
        },
        eventClick: function(info) {
            var props = info.event.extendedProps;
            var startDate = info.event.start;

            // Format time as EVE
            var timeStr = startDate.getUTCFullYear() + '-' +
                String(startDate.getUTCMonth() + 1).padStart(2, '0') + '-' +
                String(startDate.getUTCDate()).padStart(2, '0') + ' ' +
                String(startDate.getUTCHours()).padStart(2, '0') + ':' +
                String(startDate.getUTCMinutes()).padStart(2, '0') + ' EVE';

            $('#eventTime').text(timeStr);
            $('#eventWebhook').html(
                '<span class="badge" style="background-color: ' + props.webhookColor + '">' +
                props.webhook + '</span>' +
                (props.isHistory ? ' <span class="badge badge-info">Manual Broadcast</span>' : '') +
                (!props.isActive && !props.isHistory ? ' <span class="badge badge-secondary">Sent</span>' : '')
            );
            $('#eventRepeat').text(props.repeat);
            $('#eventMessage').text(props.message);
            $('#eventTimesSent').text(props.timesSent);

            if (props.repeatUntil) {
                $('#eventRepeatUntil').text(props.repeatUntil);
                $('#eventRepeatUntilRow').show();
            } else {
                $('#eventRepeatUntilRow').hide();
            }

            // Show fields if any
            var fieldsHtml = '';
            if (props.fields) {
                var fieldLabels = {
                    'fc_name': 'FC Name',
                    'formup_location': 'Formup',
                    'pap_type': 'PAP Type',
                    'comms': 'Comms',
                    'doctrine': 'Doctrine',
                    'embed_type': 'Type'
                };
                for (var key in props.fields) {
                    if (fieldLabels[key]) {
                        fieldsHtml += '<div class="event-detail-row">' +
                            '<div class="event-detail-label">' + fieldLabels[key] + '</div>' +
                            '<div class="event-detail-value">' + props.fields[key] + '</div>' +
                            '</div>';
                    }
                }
            }
            $('#eventFieldsContainer').html(fieldsHtml);

            // Show Edit button only for active scheduled pings (not history, not already sent)
            if (!props.isHistory && props.isActive && props.ping_id) {
                // Pass the clicked occurrence time so the edit form can pre-fill it
                var occurrenceTime = info.event.start
                    ? info.event.start.toISOString()
                    : null;
                var editUrl = '{{ url("discordpings/scheduled") }}/' + props.ping_id + '/edit';
                if (occurrenceTime) editUrl += '?from=' + encodeURIComponent(occurrenceTime);
                $('#editPingBtn').attr('href', editUrl).show();
            } else {
                $('#editPingBtn').hide();
            }

            $('#eventDetailModal').modal('show');
        },
        dateClick: function(info) {
            // Click on empty date -> go to create form with date pre-filled
            var dateStr = info.dateStr;
            window.location.href = '{{ route("discordpings.scheduled.create") }}?date=' + dateStr;
        },
        height: 1600,
        contentHeight: 1550,
        firstDay: 1,
        nowIndicator: true,
        eventDisplay: 'block',
        dayMaxEvents: 4,
        moreLinkClick: 'popover',
        // Week view specific settings
        slotEventOverlap: false,
        slotDuration: '01:00:00',
        expandRows: true,
        // Allow more events to stack in week view
        eventMaxStack: 5
    });

    calendar.render();
});
</script>
@endpush
