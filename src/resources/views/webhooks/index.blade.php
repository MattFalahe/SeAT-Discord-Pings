@extends('web::layouts.grids.12')

@section('title', 'Discord Webhooks')
@section('page_header', 'Discord Webhooks')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/dataTables.bootstrap4.min.css') }}">
@endpush

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Manage Webhooks</h3>
            <div class="card-tools">
                <a href="{{ route('discordpings.webhooks.create') }}" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Add Webhook
                </a>
            </div>
        </div>
        <div class="card-body">
            <table id="webhooksTable" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
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
                            <td data-order="{{ $webhook->created_at->timestamp }}">
                                {{ $webhook->created_at->diffForHumans() }}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-info test-webhook" data-id="{{ $webhook->id }}">
                                        <i class="fas fa-vial"></i>
                                    </button>
                                    <a href="{{ route('discordpings.webhooks.edit', $webhook->id) }}"
                                       class="btn btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('discordpings.webhooks.destroy', $webhook->id) }}"
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
<script src="{{ asset('vendor/discordpings/js/vendor/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/discordpings/js/vendor/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('#webhooksTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: 4 }
        ],
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search webhooks...',
            emptyTable: 'No webhooks configured'
        }
    });

    $('.test-webhook').click(function() {
        const webhookId = $(this).data('id');
        const button = $(this);

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.post('{{ url("discord-pings/webhooks") }}/' + webhookId + '/test', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            button.removeClass('btn-info').addClass('btn-success');
            button.html('<i class="fas fa-check"></i>');
        })
        .fail(function() {
            button.removeClass('btn-info').addClass('btn-danger');
            button.html('<i class="fas fa-times"></i>');
        })
        .always(function() {
            setTimeout(function() {
                button.prop('disabled', false)
                      .removeClass('btn-success btn-danger')
                      .addClass('btn-info')
                      .html('<i class="fas fa-vial"></i>');
            }, 3000);
        });
    });
});
</script>
@endpush
