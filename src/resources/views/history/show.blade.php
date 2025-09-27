@extends('web::layouts.grids.12')

@section('title', 'Ping Details')
@section('page_header', 'Ping Details')

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ping Information</h3>
            <div class="card-tools">
                <a href="{{ route('discordpings.history') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to History
                </a>
            </div>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Sent At</dt>
                <dd class="col-sm-9">{{ $history->created_at->utc()->format('Y-m-d H:i:s') }} EVE</dd>

                <dt class="col-sm-3">Webhook</dt>
                <dd class="col-sm-9">
                    @if($history->webhook)
                        {{ $history->webhook->name }}
                    @else
                        <span class="text-muted">Webhook deleted</span>
                    @endif
                </dd>

                <dt class="col-sm-3">User</dt>
                <dd class="col-sm-9">{{ $history->user_name }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if($history->status === 'sent')
                        <span class="badge badge-success">Sent Successfully</span>
                    @else
                        <span class="badge badge-danger">Failed</span>
                    @endif
                </dd>

                @if($history->error_message)
                    <dt class="col-sm-3">Error</dt>
                    <dd class="col-sm-9">
                        <code>{{ $history->error_message }}</code>
                    </dd>
                @endif

                <dt class="col-sm-3">Message</dt>
                <dd class="col-sm-9">
                    <div class="border rounded p-3 bg-light">
                        {{ $history->message }}
                    </div>
                </dd>

                @if($history->fields && count($history->fields) > 0)
                    <dt class="col-sm-3">Fields</dt>
                    <dd class="col-sm-9">
                        <table class="table table-sm">
                            @foreach($history->fields as $key => $value)
                                <tr>
                                    <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                                    <td>{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </dd>
                @endif
            </dl>
        </div>
        <div class="card-footer">
            @if($history->webhook && $history->webhook->is_active)
                <form method="POST" action="{{ route('discordpings.history.resend', $history->id) }}" 
                      style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-redo"></i> Resend Ping
                    </button>
                </form>
            @endif
        </div>
    </div>
@stop
