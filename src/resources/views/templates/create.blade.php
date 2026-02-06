@extends('web::layouts.grids.12')

@section('title', 'Create Template')
@section('page_header', 'Create Broadcast Template')

@push('head')
<style>
    .color-preview {
        width: 30px;
        height: 30px;
        border-radius: 4px;
        display: inline-block;
        vertical-align: middle;
        margin-left: 10px;
        border: 1px solid #dee2e6;
    }
</style>
@endpush

@section('full')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('discordpings.templates.store') }}">
                @csrf

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-alt"></i> Template Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Template Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   value="{{ old('name') }}" placeholder="e.g., Standard Fleet CTA">
                        </div>

                        <div class="form-group">
                            <label>Broadcast Type</label>
                            <select name="embed_type" class="form-control">
                                <option value="fleet" {{ old('embed_type') == 'fleet' ? 'selected' : '' }}>Fleet Broadcast</option>
                                <option value="announcement" {{ old('embed_type') == 'announcement' ? 'selected' : '' }}>Announcement</option>
                                <option value="message" {{ old('embed_type') == 'message' ? 'selected' : '' }}>Message</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Message <span class="text-danger">*</span></label>
                            <textarea name="template" class="form-control" rows="4" required maxlength="2000"
                                      placeholder="Enter the broadcast message template...">{{ old('template') }}</textarea>
                            <small class="form-text text-muted">
                                <span id="charCount">0</span>/2000 characters
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Mention Type</label>
                            <select name="mention_type" class="form-control" id="mentionType">
                                <option value="none">No Mention</option>
                                <option value="everyone" {{ old('mention_type') == 'everyone' ? 'selected' : '' }}>@everyone</option>
                                <option value="here" {{ old('mention_type') == 'here' ? 'selected' : '' }}>@here</option>
                                @if(isset($roles) && $roles->count() > 0)
                                    <option value="role" {{ old('mention_type') == 'role' ? 'selected' : '' }}>Discord Role</option>
                                @endif
                            </select>
                        </div>

                        @if(isset($roles) && $roles->count() > 0)
                        <div class="form-group" id="roleSelectGroup" style="display: {{ old('mention_type') == 'role' ? 'block' : 'none' }};">
                            <label>Select Discord Role</label>
                            <select name="mention_role_id" class="form-control">
                                <option value="">-- Select Role --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}"
                                            {{ old('mention_role_id') == $role->id ? 'selected' : '' }}
                                            style="color: {{ $role->color ?? '#ffffff' }}">
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                Configure Discord roles in <a href="{{ route('discordpings.config') }}">Discord Configuration</a>
                            </small>
                        </div>
                        @endif

                        <div class="form-group">
                            <label>Embed Color</label>
                            <div class="input-group">
                                <input type="text" name="embed_color" class="form-control" id="embedColor"
                                       value="{{ old('embed_color', '#5865F2') }}" pattern="^#[0-9A-Fa-f]{6}$">
                                <div class="color-preview" id="colorPreview"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list"></i> Optional Fields</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> FC Name</label>
                                    <input type="text" name="fc_name" class="form-control"
                                           value="{{ old('fc_name') }}" placeholder="e.g., FC character name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-map-marker-alt"></i> Formup Location</label>
                                    <div class="input-group">
                                        <input type="text" name="formup_location" id="formupLocation" class="form-control"
                                               value="{{ old('formup_location') }}" placeholder="e.g., Jita 4-4">
                                        @if(isset($stagings) && $stagings->count() > 0)
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fas fa-list"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <h6 class="dropdown-header">Quick Select Staging</h6>
                                                @foreach($stagings as $staging)
                                                    <a class="dropdown-item staging-select" href="#"
                                                       data-location="{{ $staging->getFullLocationString() }}">
                                                        {{ $staging->name }}
                                                        @if($staging->is_default)
                                                            <span class="badge badge-primary ml-1">Default</span>
                                                        @endif
                                                        <br>
                                                        <small class="text-muted">{{ $staging->getFullLocationString() }}</small>
                                                    </a>
                                                @endforeach
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="{{ route('discordpings.config') }}#stagings-tab">
                                                    <i class="fas fa-cog"></i> Configure Stagings
                                                </a>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> PAP Type</label>
                                    <select name="pap_type" class="form-control">
                                        <option value="">None</option>
                                        <option value="Strategic" {{ old('pap_type') == 'Strategic' ? 'selected' : '' }}>Strategic</option>
                                        <option value="Peacetime" {{ old('pap_type') == 'Peacetime' ? 'selected' : '' }}>Peacetime</option>
                                        <option value="CTA" {{ old('pap_type') == 'CTA' ? 'selected' : '' }}>CTA</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-headset"></i> Comms</label>
                                    <input type="text" name="comms" class="form-control"
                                           value="{{ old('comms') }}" placeholder="e.g., Mumble Channel 3">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-rocket"></i> Doctrine</label>
                            @if(($hasFittingPlugin ?? false) && ($doctrines ?? collect())->count() > 0)
                                {{-- Show dropdown if seat-fitting is installed --}}
                                <div class="input-group">
                                    <select name="doctrine_select" id="doctrineSelect" class="form-control">
                                        <option value="">Select Doctrine...</option>
                                        @foreach($doctrines as $doctrine)
                                            <option value="{{ $doctrine->name }}">
                                                {{ $doctrine->name }}
                                            </option>
                                        @endforeach
                                        <option value="custom">-- Manual Entry --</option>
                                    </select>
                                </div>
                                <input type="text" name="doctrine" id="doctrineManual" class="form-control mt-2"
                                       placeholder="Or type doctrine manually"
                                       value="{{ old('doctrine') }}"
                                       style="display: none;">
                                <small class="form-text text-muted">
                                    Select a doctrine from seat-fitting or choose "Manual Entry" to type your own
                                </small>
                            @else
                                {{-- Show text input if seat-fitting is not installed --}}
                                <input type="text" name="doctrine" class="form-control"
                                       value="{{ old('doctrine') }}" placeholder="e.g., Void Rays (MWD Kikis)">
                            @endif
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Template
                        </button>
                        <a href="{{ route('discordpings.templates') }}" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    $('textarea[name="template"]').on('input', function() {
        $('#charCount').text($(this).val().length);
    }).trigger('input');

    function updateColorPreview() {
        $('#colorPreview').css('background-color', $('#embedColor').val());
    }
    $('#embedColor').on('input', updateColorPreview);
    updateColorPreview();

    // Toggle role select visibility
    $('#mentionType').on('change', function() {
        if ($(this).val() === 'role') {
            $('#roleSelectGroup').show();
        } else {
            $('#roleSelectGroup').hide();
            $('select[name="mention_role_id"]').val('');
        }
    });

    // Handle staging location selection
    $('.staging-select').click(function(e) {
        e.preventDefault();
        const location = $(this).data('location');
        $('#formupLocation').val(location);
    });

    @if(($hasFittingPlugin ?? false) && ($doctrines ?? collect())->count() > 0)
    // Handle doctrine dropdown change (only if seat-fitting is installed)
    $('#doctrineSelect').change(function() {
        if ($(this).val() === 'custom') {
            $('#doctrineManual').show().prop('name', 'doctrine');
            $(this).prop('name', ''); // Remove name so it doesn't submit
        } else if ($(this).val()) {
            $('#doctrineManual').hide().val('').prop('name', '');
            // Set doctrine value to selected text
            $('<input>').attr({
                type: 'hidden',
                name: 'doctrine',
                value: $(this).val()
            }).appendTo($(this).closest('form'));
            // Remove any previous hidden doctrine input
            $(this).closest('form').find('input[type="hidden"][name="doctrine"]').not(':last').remove();
        } else {
            $('#doctrineManual').hide().val('').prop('name', '');
            $(this).closest('form').find('input[type="hidden"][name="doctrine"]').remove();
        }
    });
    @endif
});
</script>
@endpush
