@extends('web::layouts.grids.12')

@section('title', 'Scheduled Pings')
@section('page_header', 'Scheduled Discord Pings')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/dataTables.bootstrap4.min.css') }}">
@endpush

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Scheduled Pings</h3>
            <div class="card-tools">
                <a href="{{ route('discordpings.scheduled.calendar') }}" class="btn btn-sm btn-info mr-1">
                    <i class="fas fa-calendar-alt"></i> Calendar View
                </a>
                <a href="{{ route('discordpings.scheduled.create') }}" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Schedule New Ping
                </a>
            </div>
        </div>
        <div class="card-body">
            <table id="scheduledTable" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Scheduled For</th>
                        <th>Webhook</th>
                        <th>Message</th>
                        <th>Repeat</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scheduledPings as $ping)
                        <tr>
                            <td data-order="{{ $ping->scheduled_at->timestamp }}">
                                {{ $ping->scheduled_at->format('Y-m-d H:i') }} EVE
                            </td>
                            <td>
                                @if($ping->webhook)
                                    <span class="badge" style="background-color: {{ $ping->webhook->embed_color }}">
                                        {{ $ping->webhook->name }}
                                    </span>
                                @else
                                    <span class="badge badge-secondary">Deleted</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($ping->message, 80) }}</td>
                            <td>
                                @if($ping->repeat_interval)
                                    <span class="badge badge-info">{{ ucfirst($ping->repeat_interval) }}</span>
                                    @if($ping->repeat_until)
                                        <br><small>Until: {{ $ping->repeat_until->format('Y-m-d') }}</small>
                                    @endif
                                @else
                                    <span class="badge badge-secondary">Once</span>
                                @endif
                            </td>
                            <td>
                                @if($ping->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                                @if($ping->times_sent > 0)
                                    <br><small>Sent {{ $ping->times_sent }} time(s)</small>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('discordpings.scheduled.destroy', $ping->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this scheduled ping?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop

@push('javascript')
<script src="{{ asset('vendor/discordpings/js/vendor/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/discordpings/js/vendor/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('#scheduledTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        columnDefs: [
            { orderable: false, targets: 5 }
        ],
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search scheduled pings...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ scheduled pings',
            infoEmpty: 'No scheduled pings found',
            emptyTable: 'No scheduled pings'
        }
    });
});
</script>
@endpush
