@extends('web::layouts.grids.12')

@section('title', 'Broadcast Templates')
@section('page_header', 'Broadcast Templates')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/vendor/dataTables.bootstrap4.min.css') }}">
@endpush

@section('full')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Templates</h3>
            <div class="card-tools">
                <a href="{{ route('discordpings.templates.create') }}" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Create Template
                </a>
            </div>
        </div>
        <div class="card-body">
            <table id="templatesTable" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td>{{ Str::limit($template->template, 80) }}</td>
                            <td>
                                @if($template->is_global)
                                    <span class="badge badge-primary">Global</span>
                                @else
                                    <span class="badge badge-secondary">Personal</span>
                                @endif
                            </td>
                            <td data-order="{{ $template->created_at ? $template->created_at->timestamp : 0 }}">
                                {{ $template->created_at ? $template->created_at->diffForHumans() : 'N/A' }}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($template->created_by == auth()->id() || auth()->user()->can('discordpings.manage_global_templates'))
                                        <a href="{{ route('discordpings.templates.edit', $template->id) }}"
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('discordpings.templates.destroy', $template->id) }}"
                                              style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this template?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if(auth()->user()->can('discordpings.manage_global_templates'))
                                        <button class="btn {{ $template->is_global ? 'btn-primary' : 'btn-outline-primary' }} toggle-global"
                                                data-id="{{ $template->id }}" title="Toggle Global">
                                            <i class="fas fa-globe"></i>
                                        </button>
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
    $('#templatesTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: 4 }
        ],
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search templates...',
            emptyTable: 'No templates found. Create one to get started!'
        }
    });

    $('.toggle-global').click(function() {
        var templateId = $(this).data('id');
        var $btn = $(this);

        $.post('{{ url("discord-pings/templates") }}/' + templateId + '/toggle-global', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.is_global) {
                $btn.removeClass('btn-outline-primary').addClass('btn-primary');
                $btn.closest('tr').find('.badge').first()
                    .removeClass('badge-secondary').addClass('badge-primary').text('Global');
            } else {
                $btn.removeClass('btn-primary').addClass('btn-outline-primary');
                $btn.closest('tr').find('.badge').first()
                    .removeClass('badge-primary').addClass('badge-secondary').text('Personal');
            }
        })
        .fail(function() {
            alert('Failed to toggle global status');
        });
    });
});
</script>
@endpush
