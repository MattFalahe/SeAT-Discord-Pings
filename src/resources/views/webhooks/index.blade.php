@extends('web::layouts.grids.12')

@section('title', 'Discord Webhooks')
@section('page_header', 'Discord Webhooks')

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Manage Webhooks</h3>
            <div class="card-tools">
                <a href="{{ route('discord.pings.webhooks.create') }}" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Add Webhook
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Restricted To</th>
                        <th>Uses</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($webhooks as $webhook)
                        <tr>
                            <td>
                                <span class="badge" style="background-color: {{ $webhook->embed_color }}">
                                    {{ $webhook->name }}
                                </span>
                            </td>
                            <td>{{ $webhook->channel_type ?? 'General' }}</td>
                            <td>
                                @if($webhook->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($webhook->roles->count() > 0)
                                    @foreach($webhook->roles as $role)
                                        <span class="badge badge-info">{{ $role->title }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">Everyone</span>
                                @endif
                            </td>
                            <td>{{ $webhook->histories()->count() }}</td>
                            <td>{{ $webhook->created_at->diffForHumans() }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-info test-webhook" data-id="{{ $webhook->id }}">
                                        <i class="fas fa-vial"></i> Test
                                    </button>
                                    <a href="{{ route('discord.pings.webhooks.edit', $webhook->id) }}" 
                                       class="btn btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('discord.pings.webhooks.destroy', $webhook->id) }}" 
                                          style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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
<script>
$('.test-webhook').click(function() {
    const webhookId = $(this).data('id');
    const button = $(this);
    
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
    
    $.post('{{ url("discord-pings/webhooks") }}/' + webhookId + '/test', {
        _token: '{{ csrf_token() }}'
    })
    .done(function(response) {
        button.removeClass('btn-info').addClass('btn-success');
        button.html('<i class="fas fa-check"></i> Success');
    })
    .fail(function() {
        button.removeClass('btn-info').addClass('btn-danger');
        button.html('<i class="fas fa-times"></i> Failed');
    })
    .always(function() {
        setTimeout(function() {
            button.prop('disabled', false)
                  .removeClass('btn-success btn-danger')
                  .addClass('btn-info')
                  .html('<i class="fas fa-vial"></i> Test');
        }, 3000);
    });
});
</script>
@endpush
