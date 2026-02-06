@extends('web::layouts.grids.12')

@section('title', 'Ping History')
@section('page_header', 'Discord Ping History')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/dataTables.bootstrap4.min.css') }}">
@endpush

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ping History</h3>
        </div>
        <div class="card-body">
            <table id="historyTable" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Time (EVE)</th>
                        <th>Webhook</th>
                        <th>Message</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($histories as $history)
                        <tr>
                            <td data-order="{{ $history->created_at->timestamp }}">
                                {{ $history->created_at->utc()->format('Y-m-d H:i:s') }} EVE
                            </td>
                            <td>
                                @if($history->webhook)
                                    <span class="badge" style="background-color: {{ $history->webhook->embed_color }}">
                                        {{ $history->webhook->name }}
                                    </span>
                                @else
                                    <span class="badge badge-secondary">Deleted</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($history->message, 80) }}</td>
                            <td>{{ $history->user_name }}</td>
                            <td>
                                @if($history->status === 'sent')
                                    <span class="badge badge-success">Sent</span>
                                @else
                                    <span class="badge badge-danger">Failed</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('discordpings.history.show', $history->id) }}"
                                       class="btn btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($history->webhook && $history->webhook->is_active)
                                        <form method="POST" action="{{ route('discordpings.history.resend', $history->id) }}"
                                              style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
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
    $('#historyTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        columnDefs: [
            { orderable: false, targets: 5 }
        ],
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search history...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ broadcasts',
            infoEmpty: 'No broadcast history found',
            emptyTable: 'No broadcast history found'
        }
    });
});
</script>
@endpush
