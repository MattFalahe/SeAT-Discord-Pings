@extends('web::layouts.grids.12')

@section('title', 'Ping History')
@section('page_header', 'Discord Ping History')

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ping History</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Webhook</th>
                        <th>Message</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $history)
                        <tr>
                            <td>{{ $history->created_at->format('Y-m-d H:i') }}</td>
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
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No ping history found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            @if($histories->hasPages())
                <div class="d-flex justify-content-center">
                    {{ $histories->links() }}
                </div>
            @endif
        </div>
    </div>
@stop
