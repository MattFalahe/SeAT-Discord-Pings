@extends('web::layouts.grids.3-9')

@section('title', 'Discord Pings')
@section('page_header', 'Discord Pings')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=4">
<script src="{{ asset('vendor/discordpings/js/eve-time.js') }}?v=1" defer></script>
<style>
    /* Page-specific only — chrome lives in canonical CSS.
       Note: the Discord embed preview modal uses Discord's actual UI colors
       (#36393f, #2f3136, #dcddde, etc.) intentionally and must NOT be changed. */
    .template-btn {
        margin-bottom: 5px;
        white-space: normal;
        text-align: left;
    }
    .recent-ping {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .recent-ping:hover {
        background-color: rgba(102, 126, 234, 0.1);
    }
    #previewMessage {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
</style>
@endpush

@section('left')
<div class="discord-pings-wrapper">
    {{-- Global Templates --}}
    @php
        $globalTemplates = ($templates ?? collect())->where('is_global', true);
        $myTemplates = ($templates ?? collect())->where('is_global', false)->where('created_by', auth()->id());
    @endphp

    @if ($globalTemplates->count() > 0)
    <div class="card card-dark mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-globe"></i> Global Templates
            </h3>
        </div>
        <div class="card-body p-2">
            @foreach ($globalTemplates as $tpl)
                <button type="button" class="btn btn-sm btn-block btn-outline-primary template-btn"
                        data-template="{{ $tpl->template }}"
                        data-fields='@json($tpl->fields ?? [])'>
                    {{ $tpl->name }}
                </button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- My Templates --}}
    <div class="card card-dark mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-alt"></i> My Templates
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-sm btn-outline-success" id="openSaveTemplate" title="Save current form as template">
                    <i class="fas fa-save"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-2">
            @forelse ($myTemplates as $tpl)
                <button type="button" class="btn btn-sm btn-block btn-outline-info template-btn"
                        data-template="{{ $tpl->template }}"
                        data-fields='@json($tpl->fields ?? [])'>
                    {{ $tpl->name }}
                </button>
            @empty
                <small class="text-muted d-block text-center py-2">No personal templates yet</small>
            @endforelse
            <a href="{{ route('discordpings.templates') }}" class="btn btn-sm btn-block btn-pings-secondary mt-2">
                <i class="fas fa-cog"></i> Manage Templates
            </a>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history"></i> Recent Pings
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @forelse ($recentPings ?? [] as $ping)
                    <div class="list-group-item recent-ping p-2"
                         data-message="{{ $ping->message }}"
                         data-webhook-id="{{ $ping->webhook_id }}"
                         data-fields='@json($ping->fields ?? [])'>
                        <small class="text-muted">
                            {{ $ping->created_at->diffForHumans() }}
                        </small>
                        <p class="mb-1 small">{{ Str::limit($ping->message, 50) }}</p>
                    </div>
                @empty
                    <div class="list-group-item">
                        <small class="text-muted">No recent pings</small>
                    </div>
                @endforelse
            </div>
        </div>
        @if (($recentPings ?? collect())->count() > 0)
            <div class="card-footer p-2">
                <a href="{{ route('discordpings.history') }}" class="btn btn-sm btn-block btn-pings-secondary">
                    View All History
                </a>
            </div>
        @endif
    </div>
</div>
@stop

@section('right')
<div class="discord-pings-wrapper">
    <form method="POST" action="{{ route('discordpings.send.post') }}" id="pingForm">
        @csrf

        @if (! empty($tacticalEvent))
            <div class="alert-pings-styled alert-pings-info" style="margin-bottom: 15px;">
                <i class="fas fa-satellite-dish"></i>
                <strong>Form-up for:</strong>
                {{ $tacticalEvent->title }}
                @if ($tacticalEvent->system_name)
                    in <strong>{{ $tacticalEvent->system_name }}</strong>
                @endif
                @if ($tacticalEvent->eve_time)
                    ,
                    {{ $tacticalEvent->category_group === 'mining' ? 'window closes at' : 'timer at' }}
                    <strong>
                        <span class="eve-time" data-eve-time="{{ $tacticalEvent->eve_time->toIso8601String() }}" data-show-local>
                            {{ $tacticalEvent->eve_time->format('Y-m-d H:i') }} EVE
                        </span>
                    </strong>
                @endif
                . The form below has been pre-filled for an immediate broadcast; review and adjust before sending.
            </div>
        @endif

        @if (! empty($pingRequest))
            {{-- Checked again when the form comes back, so this only says which request it means. --}}
            <input type="hidden" name="answers_request" value="{{ $pingRequest->id }}">
            <div class="alert-pings-styled alert-pings-info" style="margin-bottom: 15px;">
                <i class="fas fa-hand-paper"></i>
                <strong>Form-up for:</strong>
                {{ $pingRequest->fields['button'] ?? 'a ping request' }},
                {{ $pingRequest->isEmergencyRequest() ? 'an emergency' : 'a request' }} from
                <strong>{{ $pingRequest->fields['main'] ?? $pingRequest->user_name }}</strong>
                sent at
                <strong>
                    <span class="eve-time" data-eve-time="{{ $pingRequest->created_at->utc()->toIso8601String() }}" data-show-local>
                        {{ $pingRequest->created_at->utc()->format('Y-m-d H:i') }} EVE
                    </span>
                </strong>.
                The form below starts from what they wrote, so check it before you send.
                It counts as answered once your broadcast goes out.
                @if (! empty($pingRequestAnswers))
                    <div class="mt-2">
                        <i class="fas fa-user-check"></i>
                        <strong>Already answered by</strong>
                        @foreach ($pingRequestAnswers as $answer)
                            {{ $answer['name'] }}
                            (<span class="eve-time" data-eve-time="{{ gmdate('Y-m-d\TH:i:s\Z', $answer['at']) }}" data-show-local>{{ gmdate('H:i', $answer['at']) }} EVE</span>){{ $loop->last ? '.' : ',' }}
                        @endforeach
                        You can still send your own.
                    </div>
                @endif
            </div>
        @elseif (! empty($pingRequestMissing))
            <div class="alert-pings-styled alert-pings-warning" style="margin-bottom: 15px;">
                <i class="fas fa-exclamation-triangle"></i>
                That request can't be answered from here. It's more than 24 hours old, the member closed it,
                or it isn't one you can see. The form starts as it normally would.
            </div>
        @endif

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paper-plane"></i> Send Ping
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-outline-info" id="multipleToggle">
                        <i class="fas fa-copy"></i> Multiple Webhooks
                    </button>
                </div>
            </div>
            <div class="card-body">
                @include('discordpings::partials.broadcast-main', ['multipleWebhooks' => true])
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i> Optional Fields
                </h3>
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
                    <i class="fas fa-paper-plane"></i> Send Ping
                </button>
                <button type="button" class="btn btn-warning" id="scheduleBtn">
                    <i class="fas fa-clock"></i> Schedule
                </button>
                <button type="button" class="btn btn-pings-secondary float-right" id="previewBtn">
                    <i class="fas fa-eye"></i> Preview
                </button>
            </div>
        </div>
    </form>

    {{-- Preview Modal --}}
    <div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Discord Embed Preview</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div style="background-color: #36393f; padding: 20px; border-radius: 8px;">
                        <div style="background-color: #2f3136; border-left: 4px solid #5865F2; padding: 16px; border-radius: 4px;" id="embedPreview">
                            <div style="color: #fff; font-weight: bold; margin-bottom: 8px;" id="previewTitle">📢 Fleet Broadcast</div>
                            <div style="color: #dcddde; margin-bottom: 12px;" id="previewMessage"></div>
                            <div id="previewFields" style="margin-top: 12px;"></div>
                            <div style="color: #72767d; font-size: 12px; margin-top: 12px;" id="previewFooter"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Save as Template Modal --}}
    <div class="modal fade" id="saveTemplateModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-save"></i> Save as Template</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="templateName">Template Name <span class="text-danger">*</span></label>
                        <input type="text" id="templateName" class="form-control" maxlength="100"
                               placeholder="e.g., My Fleet CTA">
                        <small class="form-text text-muted">Saves every box as it is now, the webhooks and the message included.</small>
                    </div>
                    <div id="saveTemplateAlert" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-pings-primary" id="saveTemplateBtn">
                        <i class="fas fa-save"></i> Save Template
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('javascript')
@php
    // A form that came back with errors keeps what was typed. Otherwise it
    // starts from Settings' defaults and whatever the page was opened for.
    $oldInput = collect(old())->except(['_token', '_method'])->all();
    $formValues = $oldInput !== [] ? $oldInput : ($prefill ?? []);

    $scheduleUrl = route('discordpings.scheduled.create', array_filter([
        'answers_request' => ! empty($pingRequest) ? $pingRequest->id : null,
        'tactical_event_id' => ! empty($tacticalEvent) ? $tacticalEvent->id : null,
    ]));
@endphp
@include('discordpings::partials.broadcast-scripts', [
    'suggestions' => $suggestions ?? [],
    'singleAction' => route('discordpings.send.post'),
    'multipleAction' => route('discordpings.send.multiple'),
])
<script>
$(function () {
    'use strict';

    var TITLES = {
        fleet: '📢 Fleet Broadcast',
        announcement: '📣 Announcement',
        message: '💬 Message',
        prepping: '‼️ PREPING ‼️'
    };

    BroadcastForm.init('#pingForm', @json($formValues));

    // attr() rather than data(): data() turns a message like "1" or "true" into a number or a boolean.
    function attrJson($element, name) {
        try {
            var parsed = JSON.parse($element.attr(name) || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    $('.template-btn').on('click', function () {
        BroadcastForm.fill($.extend({}, attrJson($(this), 'data-fields'), {
            message: $(this).attr('data-template') || ''
        }));
    });

    $('.recent-ping').on('click', function () {
        BroadcastForm.fill($.extend({}, attrJson($(this), 'data-fields'), {
            webhook_id: $(this).attr('data-webhook-id') || null,
            message: $(this).attr('data-message') || ''
        }));
    });

    // Every box goes along, and the request it answers rides in the address.
    $('#scheduleBtn').on('click', function () {
        try {
            localStorage.setItem('pingFormData', JSON.stringify({ v: 2, at: Date.now(), values: BroadcastForm.read() }));
        } catch (e) {
            // Without storage the Schedule page still opens, from its own defaults.
        }

        window.location.href = @json($scheduleUrl);
    });

    function previewField(label, value) {
        return $('<div style="margin-bottom: 8px;">').append(
            $('<span style="color: #8e9297; font-weight: bold;">').text(label),
            $('<br>'),
            $('<span style="color: #dcddde;">').text(value)
        );
    }

    $('#previewBtn').on('click', function (event) {
        event.preventDefault();

        var values = BroadcastForm.read();
        var channel = $('#channelSelect').val() ? $('#channelSelect option:selected').text().trim() : '';
        var $fields = $('#previewFields').empty();

        $('#previewTitle').text(TITLES[values.embed_type] || TITLES.fleet);
        $('#previewMessage').html(values.message ? MessageTools.render(values.message) : '<em>No message entered</em>');
        $('#embedPreview').css('border-left-color', /^#[0-9A-Fa-f]{6}$/.test(values.embed_color) ? values.embed_color : '#5865F2');

        if (values.fc_name) {
            $fields.append(previewField('👤 FC Name', values.fc_name));
        }
        if (values.formup_location) {
            $fields.append(previewField('📍 Formup Location', values.formup_location));
        }
        if (values.pap_type) {
            $fields.append(previewField('🎯 PAP Type', values.pap_type));
        }
        if (channel || values.comms) {
            $fields.append(previewField('🎧 Comms / Channel', channel || values.comms));
        }
        if (values.doctrine) {
            $fields.append(previewField('🚀 Doctrine', values.doctrine));
        }

        var now = new Date().toISOString().replace('T', ' ').substring(0, 19);
        $('#previewFooter').text('This was a broadcast from ' + @json(auth()->user()->name) + ' to discord at ' + now + ' EVE');

        $('#previewModal').appendTo('body').modal('show');
    });

    function templateAlert(kind, text) {
        $('#saveTemplateAlert').empty().append($('<div class="alert">').addClass('alert-' + kind).text(text)).show();
    }

    $('#openSaveTemplate').on('click', function () {
        $('#saveTemplateModal').appendTo('body').modal('show');
    });

    $('#saveTemplateBtn').on('click', function () {
        var name = $('#templateName').val().trim();

        if (!name) {
            templateAlert('danger', 'Please enter a template name.');
            return;
        }

        var $button = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        var data = $.extend(BroadcastForm.read(), { _token: @json(csrf_token()), name: name });

        $.post(@json(route('discordpings.templates.quicksave')), data)
            .done(function () {
                templateAlert('success', 'Template saved!');
                setTimeout(function () {
                    $('#saveTemplateModal').modal('hide');
                    location.reload();
                }, 1000);
            })
            .fail(function (xhr) {
                var json = xhr.responseJSON || {};
                templateAlert('danger', json.error || json.message || 'Failed to save template.');
            })
            .always(function () {
                $button.prop('disabled', false).html('<i class="fas fa-save"></i> Save Template');
            });
    });

    $('#saveTemplateModal').on('hidden.bs.modal', function () {
        $('#templateName').val('');
        $('#saveTemplateAlert').hide().empty();
    });
});
</script>
@endpush
