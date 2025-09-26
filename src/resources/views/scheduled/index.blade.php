@extends('web::layouts.grids.12')

@section('title', 'Scheduled Pings')
@section('page_header', 'Scheduled Discord Pings')

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Scheduled Pings</h3>
            <div class="card-tools">
                <a href="{{ route('discordpings.scheduled.create') }}" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Schedule New Ping
                </a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-striped">
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
                    @forelse($scheduledPings as $ping)
                        <tr>
                            <td>{{ $ping->scheduled_at->format('Y-m-d H:i') }}</td>
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
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No scheduled pings</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            @if($scheduledPings->hasPages())
                <div class="d-flex justify-content-center">
                    {{ $scheduledPings->links() }}
                </div>
            @endif
        </div>
    </div>
@stop
