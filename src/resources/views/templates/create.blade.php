@extends('web::layouts.grids.12')

@section('title', 'Create Template')
@section('page_header', 'Create Broadcast Template')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=3">
@endpush

@section('full')
<div class="discord-pings-wrapper">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('discordpings.templates.store') }}" id="templateForm">
                @csrf

                <div class="card card-dark">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-alt"></i> Template Details</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-outline-info" id="multipleToggle">
                                <i class="fas fa-copy"></i> Multiple Webhooks
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="templateName">Template Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="templateName" class="form-control" required maxlength="100"
                                   value="{{ old('name') }}" placeholder="e.g., Standard Fleet CTA">
                        </div>

                        @include('discordpings::partials.broadcast-main', ['multipleWebhooks' => true, 'webhookRequired' => false])
                    </div>
                </div>

                <div class="card card-dark">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list"></i> Optional Fields</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('discordpings::partials.broadcast-optional')
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-pings-primary">
                            <i class="fas fa-save"></i> Save Template
                        </button>
                        <a href="{{ route('discordpings.templates') }}" class="btn btn-pings-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('javascript')
@php
    $formValues = collect(old())->except(['_token', '_method', 'name'])->all();
@endphp
@include('discordpings::partials.broadcast-scripts', ['suggestions' => $suggestions ?? []])
<script>
$(function () {
    BroadcastForm.init('#templateForm', @json($formValues));
});
</script>
@endpush
