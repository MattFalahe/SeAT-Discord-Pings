@extends('web::layouts.grids.12')

@section('title', 'Discord Configuration')
@section('page_header', 'Discord Configuration')

@push('head')
<style>
    .nav-tabs .nav-link {
        color: #6c757d;
    }
    .nav-tabs .nav-link.active {
        font-weight: bold;
    }
    .color-badge {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 3px;
        vertical-align: middle;
        margin-right: 5px;
    }
    .copy-btn {
        cursor: pointer;
    }
</style>
@endpush

@section('full')
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#webhooks-tab">
                        <i class="fas fa-link"></i> Webhooks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#roles-tab">
                        <i class="fas fa-user-tag"></i> Discord Roles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#channels-tab">
                        <i class="fas fa-hashtag"></i> Discord Channels
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                {{-- Webhooks Tab --}}
                <div class="tab-pane fade show active" id="webhooks-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>Webhook Configuration</h4>
                        <a href="{{ route('discordpings.webhooks.create') }}" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Webhook
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Channel Type</th>
                                    <th>Color</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($webhooks as $webhook)
                                    <tr>
                                        <td>{{ $webhook->name }}</td>
                                        <td>{{ $webhook->channel_type ?? 'General' }}</td>
                                        <td>
                                            <span class="color-badge" style="background-color: {{ $webhook->embed_color }}"></span>
                                            {{ $webhook->embed_color }}
                                        </td>
                                        <td>
                                            @if($webhook->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info test-webhook" data-id="{{ $webhook->id }}" title="Test">
                                                    <i class="fas fa-vial"></i>
                                                </button>
                                                <a href="{{ route('discordpings.webhooks.edit', $webhook->id) }}" 
                                                   class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" action="{{ route('discordpings.webhooks.destroy', $webhook->id) }}" 
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No webhooks configured</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Roles Tab --}}
                <div class="tab-pane fade" id="roles-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>Discord Roles</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addRoleModal">
                            <i class="fas fa-plus"></i> Add Role
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role ID</th>
                                    <th>Mention</th>
                                    <th>Color</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($roles as $role)
                                    <tr>
                                        <td>{{ $role->name }}</td>
                                        <td>
                                            <code>{{ $role->role_id }}</code>
                                            <i class="fas fa-copy copy-btn ml-1" 
                                               data-copy="{{ $role->role_id }}" 
                                               title="Copy ID"></i>
                                        </td>
                                        <td>
                                            <code>{{ $role->getMentionString() }}</code>
                                            <i class="fas fa-copy copy-btn ml-1" 
                                               data-copy="{{ $role->getMentionString() }}" 
                                               title="Copy mention"></i>
                                        </td>
                                        <td>
                                            @if($role->color)
                                                <span class="color-badge" style="background-color: {{ $role->color }}"></span>
                                                {{ $role->color }}
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($role->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info toggle-role" 
                                                        data-id="{{ $role->id }}" 
                                                        title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <form method="POST" action="{{ route('discordpings.config.roles.destroy', $role->id) }}" 
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No Discord roles configured</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Channels Tab --}}
                <div class="tab-pane fade" id="channels-tab">
                    <div class="d-flex justify-content-between mb-3">
                        <h4>Discord Channels</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addChannelModal">
                            <i class="fas fa-plus"></i> Add Channel
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Channel Link</th>
                                    <th>Mention</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($channels as $channel)
                                    <tr>
                                        <td>{{ $channel->name }}</td>
                                        <td>
                                            <span class="badge badge-secondary">{{ ucfirst($channel->channel_type) }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ $channel->getChannelLink() }}" target="_blank">
                                                Open in Discord <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <code>{{ $channel->getMentionString() }}</code>
                                            <i class="fas fa-copy copy-btn ml-1" 
                                               data-copy="{{ $channel->getMentionString() }}" 
                                               title="Copy mention"></i>
                                        </td>
                                        <td>
                                            @if($channel->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info toggle-channel" 
                                                        data-id="{{ $channel->id }}" 
                                                        title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <form method="POST" action="{{ route('discordpings.config.channels.destroy', $channel->id) }}" 
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Delete"
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No Discord channels configured</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Role Modal --}}
    <div class="modal fade" id="addRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('discordpings.config.roles.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Discord Role</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="e.g., Fleet Commanders">
                            <small class="form-text text-muted">A friendly name for this role</small>
                        </div>
                        <div class="form-group">
                            <label>Role ID or Mention <span class="text-danger">*</span></label>
                            <input type="text" name="role_id" class="form-control" required 
                                   placeholder="e.g., 123456789 or <@&123456789>">
                            <small class="form-text text-muted">
                                Copy from Discord: Right-click role → Copy ID, or copy a role mention
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Color (Optional)</label>
                            <input type="text" name="color" class="form-control" 
                                   pattern="^#[0-9A-Fa-f]{6}$" 
                                   placeholder="#5865F2">
                            <small class="form-text text-muted">Hex color code for display</small>
                        </div>
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="Optional description for this role"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Channel Modal --}}
    <div class="modal fade" id="addChannelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('discordpings.config.channels.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Discord Channel</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Channel Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="e.g., fleet-formup">
                            <small class="form-text text-muted">A friendly name for this channel</small>
                        </div>
                        <div class="form-group">
                            <label>Channel URL <span class="text-danger">*</span></label>
                            <input type="url" name="channel_url" class="form-control" required 
                                   placeholder="https://discord.com/channels/123456/789012">
                            <small class="form-text text-muted">
                                Right-click channel in Discord → Copy Link
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Channel Type</label>
                            <select name="channel_type" class="form-control">
                                <option value="text">Text Channel</option>
                                <option value="voice">Voice Channel</option>
                                <option value="announcement">Announcement Channel</option>
                                <option value="forum">Forum Channel</option>
                                <option value="stage">Stage Channel</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="Optional description for this channel"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Channel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    // Copy to clipboard functionality
    $('.copy-btn').click(function() {
        const textToCopy = $(this).data('copy');
        const $btn = $(this);
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            $btn.removeClass('fa-copy').addClass('fa-check text-success');
            setTimeout(function() {
                $btn.removeClass('fa-check text-success').addClass('fa-copy');
            }, 2000);
        });
    });
    
    // Toggle role status
    $('.toggle-role').click(function() {
        const roleId = $(this).data('id');
        const $btn = $(this);
        
        $.post(`{{ url('discord-pings/config/roles') }}/${roleId}/toggle`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            location.reload();
        })
        .fail(function() {
            alert('Failed to toggle role status');
        });
    });
    
    // Toggle channel status
    $('.toggle-channel').click(function() {
        const channelId = $(this).data('id');
        const $btn = $(this);
        
        $.post(`{{ url('discord-pings/config/channels') }}/${channelId}/toggle`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            location.reload();
        })
        .fail(function() {
            alert('Failed to toggle channel status');
        });
    });

    // Test webhook
    $('.test-webhook').click(function() {
        const webhookId = $(this).data('id');
        const button = $(this);
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.post(`{{ url('discord-pings/webhooks') }}/${webhookId}/test`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            button.removeClass('btn-info').addClass('btn-success');
            button.html('<i class="fas fa-check"></i>');
            alert('Test successful! Check your Discord channel.');
        })
        .fail(function() {
            button.removeClass('btn-info').addClass('btn-danger');
            button.html('<i class="fas fa-times"></i>');
            alert('Test failed! Please check the webhook URL.');
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
    
    // Remember active tab
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        localStorage.setItem('activeDiscordConfigTab', $(e.target).attr('href'));
    });
    
    // Restore active tab
    var activeTab = localStorage.getItem('activeDiscordConfigTab');
    if (activeTab) {
        $(`a[href="${activeTab}"]`).tab('show');
    }
});
</script>
@endpush
