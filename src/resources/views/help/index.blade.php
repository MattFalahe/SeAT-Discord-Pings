@extends('web::layouts.grids.12')

@section('title', trans('discordpings::help.help_documentation'))
@section('page_header', trans('discordpings::help.help_documentation'))

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/discordpings/css/discord-pings.css') }}?v=2">
<style>
    /* Page-specific only — chrome lives in canonical CSS */

    /* Step list (numbered counter circles) — slightly tighter than canonical .step-by-step */
    .step-list {
        counter-reset: step-counter;
        list-style: none;
        padding-left: 0;
    }
    .step-list li {
        counter-increment: step-counter;
        position: relative;
        padding-left: 45px;
        margin-bottom: 12px;
        color: #d1d5db !important;
    }
    .step-list li::before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        width: 30px;
        height: 30px;
        background: linear-gradient(135deg, var(--pings-primary-start) 0%, var(--pings-primary-end) 100%);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.85rem;
    }

    /* Plugin-info gradient header card (used on Overview section) */
    .plugin-info {
        background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(102, 126, 234, 0.3);
    }
    .plugin-info h3 {
        color: var(--pings-primary-start) !important;
        margin-bottom: 15px;
    }
    .plugin-info .info-row {
        color: #9ca3af !important;
        margin: 5px 0;
    }
    .plugin-info .author {
        color: var(--pings-primary-start) !important;
        margin: 10px 0;
    }

    /* Plugin link list (GitHub / Changelog / Issues / README quick-jump tiles) */
    .plugin-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }
    .plugin-link {
        background: rgba(102, 126, 234, 0.1);
        padding: 10px;
        border-radius: 5px;
        border: 1px solid rgba(102, 126, 234, 0.3);
        color: var(--pings-primary-start) !important;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
    }
    .plugin-link:hover {
        background: rgba(102, 126, 234, 0.2);
        color: #a5b4fc !important;
        text-decoration: none;
        transform: translateX(5px);
    }
</style>
@endpush

@section('full')
<div class="discord-pings-wrapper">
<div class="help-wrapper">
    {{-- Sidebar --}}
    <div class="help-sidebar">
        <div class="card card-dark">
            <div class="card-body p-3">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="helpSearch" placeholder="{{ trans('discordpings::help.search_placeholder') }}">
                </div>
                <nav class="help-nav">
                    <a href="#" class="nav-link active" data-section="overview">
                        <i class="fas fa-home"></i> {{ trans('discordpings::help.overview') }}
                    </a>
                    <a href="#" class="nav-link" data-section="whats-new">
                        <i class="fas fa-star"></i> {{ trans('discordpings::help.whats_new') }}
                    </a>
                    <a href="#" class="nav-link" data-section="getting-started">
                        <i class="fas fa-rocket"></i> {{ trans('discordpings::help.getting_started') }}
                    </a>
                    <a href="#" class="nav-link" data-section="sending-pings">
                        <i class="fas fa-paper-plane"></i> {{ trans('discordpings::help.sending_pings') }}
                    </a>
                    <a href="#" class="nav-link" data-section="webhooks">
                        <i class="fas fa-link"></i> {{ trans('discordpings::help.webhooks') }}
                    </a>
                    <a href="#" class="nav-link" data-section="templates">
                        <i class="fas fa-file-alt"></i> {{ trans('discordpings::help.templates') }}
                    </a>
                    <a href="#" class="nav-link" data-section="scheduled">
                        <i class="fas fa-clock"></i> {{ trans('discordpings::help.scheduled') }}
                    </a>
                    <a href="#" class="nav-link" data-section="calendar">
                        <i class="fas fa-calendar-alt"></i> {{ trans('discordpings::help.calendar') }}
                    </a>
                    <a href="#" class="nav-link" data-section="structure-timers">
                        <i class="fas fa-satellite-dish"></i> {{ trans('discordpings::help.structure_timers') }}
                        <span class="v2-badge v2-badge-nav">{{ trans('discordpings::help.v2_badge') }}</span>
                    </a>
                    <a href="#" class="nav-link" data-section="mining-extractions">
                        <i class="fas fa-gem"></i> {{ trans('discordpings::help.mining_extractions_title') }}
                        <span class="v2-badge v2-badge-nav">{{ trans('discordpings::help.v2_badge') }}</span>
                    </a>
                    <a href="#" class="nav-link" data-section="fc-opportunities">
                        <i class="fas fa-bullseye"></i> {{ trans('discordpings::help.fc_opportunities') }}
                        <span class="v2-badge v2-badge-nav">{{ trans('discordpings::help.v2_badge') }}</span>
                    </a>
                    <a href="#" class="nav-link" data-section="published-events">
                        <i class="fas fa-broadcast-tower"></i> {{ trans('discordpings::help.published_events') }}
                        <span class="v2-badge v2-badge-nav">{{ trans('discordpings::help.v2_badge') }}</span>
                    </a>
                    <a href="#" class="nav-link" data-section="configuration">
                        <i class="fas fa-cog"></i> {{ trans('discordpings::help.configuration') }}
                    </a>
                    <a href="#" class="nav-link" data-section="pages">
                        <i class="fas fa-th-large"></i> {{ trans('discordpings::help.pages_guide') }}
                    </a>
                    <a href="#" class="nav-link" data-section="commands">
                        <i class="fas fa-terminal"></i> {{ trans('discordpings::help.commands') }}
                    </a>
                    <a href="#" class="nav-link" data-section="permissions">
                        <i class="fas fa-user-shield"></i> {{ trans('discordpings::help.permissions') }}
                    </a>
                    <a href="#" class="nav-link" data-section="faq">
                        <i class="fas fa-question-circle"></i> {{ trans('discordpings::help.faq') }}
                    </a>
                    <a href="#" class="nav-link" data-section="troubleshooting">
                        <i class="fas fa-wrench"></i> {{ trans('discordpings::help.troubleshooting') }}
                    </a>
                </nav>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="help-content">
        {{-- Overview Section --}}
        <div class="help-section active" id="overview">
            {{-- Plugin Information --}}
            <div class="plugin-info">
                <h3><i class="fas fa-info-circle"></i> {{ trans('discordpings::help.plugin_info_title') }}</h3>
                <div class="info-row">
                    <strong>{{ trans('discordpings::help.version') }}:</strong>
                    <img src="https://img.shields.io/packagist/v/mattfalahe/seat-discord-pings" alt="Version" style="vertical-align: middle;">
                    <img src="https://img.shields.io/badge/SeAT-5.0-green" alt="SeAT" style="vertical-align: middle;">
                </div>
                <div class="info-row">
                    <strong>{{ trans('discordpings::help.license') }}:</strong> GPL-2.0
                </div>

                <div class="author">
                    <i class="fas fa-user"></i> <strong>{{ trans('discordpings::help.author') }}:</strong> Matt Falahe
                    <br>
                    <i class="fas fa-envelope"></i> <a href="mailto:mattfalahe@gmail.com" style="color: #667eea;">mattfalahe@gmail.com</a>
                </div>

                <div class="plugin-links">
                    <a href="https://github.com/MattFalahe/SeAT-Discord-Pings" target="_blank" class="plugin-link">
                        <i class="fab fa-github"></i> {{ trans('discordpings::help.github_repo') }}
                    </a>
                    <a href="https://github.com/MattFalahe/SeAT-Discord-Pings/blob/main/CHANGELOG.md" target="_blank" class="plugin-link">
                        <i class="fas fa-list"></i> {{ trans('discordpings::help.changelog') }}
                    </a>
                    <a href="https://github.com/MattFalahe/SeAT-Discord-Pings/issues" target="_blank" class="plugin-link">
                        <i class="fas fa-bug"></i> {{ trans('discordpings::help.report_issues') }}
                    </a>
                    <a href="https://github.com/MattFalahe/SeAT-Discord-Pings/blob/main/README.md" target="_blank" class="plugin-link">
                        <i class="fas fa-book"></i> {{ trans('discordpings::help.readme') }}
                    </a>
                </div>

                <div class="success-box" style="margin-top: 15px;">
                    <i class="fas fa-heart"></i>
                    <strong>{{ trans('discordpings::help.support_project') }}:</strong>
                    <ul style="margin-top: 10px; margin-bottom: 0;">
                        <li>⭐ Star the GitHub repository</li>
                        <li>🐛 Report bugs and issues</li>
                        <li>💡 Suggest new features</li>
                        <li>🛠️ Contributing code improvements</li>
                        <li>📢 Share with other SeAT users</li>
                    </ul>
                </div>
            </div>

            {{-- Version Status — installed vs latest on Packagist --}}
            @php
                $vs = $versionStatus ?? ['current' => '?', 'current_source' => 'config', 'is_dev_branch' => false, 'latest' => null, 'status' => 'unknown', 'message' => '', 'release_url' => null];
                $statusBadgeClass = [
                    'current'    => 'badge-success',
                    'outdated'   => 'badge-warning',
                    'ahead'      => 'badge-info',
                    'dev_branch' => 'badge-info',
                    'unknown'    => 'badge-secondary',
                ][$vs['status']] ?? 'badge-secondary';
                $statusLabel = [
                    'current'    => '✓ Up to date',
                    'outdated'   => '⚠ Update available',
                    'ahead'      => '🚀 Pre-release',
                    'dev_branch' => '🌱 Development branch',
                    'unknown'    => '— Unable to check',
                ][$vs['status']] ?? '— Unknown';
                // Show the raw branch ref as-is (no 'v' prefix); tagged versions get the v.
                $installedDisplay = $vs['is_dev_branch'] ? $vs['current'] : ('v' . $vs['current']);
                $sourceHint = $vs['current_source'] === 'composer'
                    ? 'resolved via Composer\'s installed.json'
                    : 'resolved via discordpings.config.php (fallback — Composer metadata unavailable)';
            @endphp
            <div class="help-card">
                <h3><i class="fas fa-tag"></i> Version Status</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin: 0.5rem 0;">
                    <div>
                        <strong>Installed:</strong>
                        <span class="badge badge-secondary" style="font-size: 0.9rem;" title="{{ $sourceHint }}">
                            {{ $installedDisplay }}
                        </span>
                    </div>
                    <div>
                        <strong>Latest release:</strong>
                        @if($vs['latest'])
                            <span class="badge badge-secondary" style="font-size: 0.9rem;">v{{ $vs['latest'] }}</span>
                        @else
                            <span class="badge badge-secondary" style="font-size: 0.9rem;">unknown</span>
                        @endif
                    </div>
                    <div>
                        <span class="badge {{ $statusBadgeClass }}" style="font-size: 0.9rem;">{{ $statusLabel }}</span>
                    </div>
                    @if($vs['release_url'])
                        <div>
                            <a href="{{ $vs['release_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-pings-secondary">
                                <i class="fas fa-external-link-alt"></i> View release notes
                            </a>
                        </div>
                    @endif
                </div>
                <small class="text-muted">{{ $vs['message'] }}</small>
                @if($vs['status'] === 'outdated')
                    <div class="info-box" style="margin-top: 0.75rem;">
                        <i class="fas fa-arrow-circle-up"></i>
                        <strong>Upgrade recipe (SeAT Docker stack):</strong>
                        <pre style="margin-top: 0.4rem; margin-bottom: 0;"><code>docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d</code></pre>
                        <small class="text-muted" style="display: block; margin-top: 0.4rem;">
                            Container boot pulls the latest plugin via composer, runs new migrations, and re-seeds schedules automatically.
                        </small>
                    </div>
                @endif
                <small class="text-muted" style="display: block; margin-top: 0.4rem; font-size: 0.75rem;">
                    <i class="fas fa-info-circle"></i>
                    Installed version {{ $sourceHint }}. Latest checked via Packagist's public API (6h cache, safe on outages).
                </small>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-bullhorn"></i> {{ trans('discordpings::help.welcome_title') }}</h3>
                <p>{{ trans('discordpings::help.welcome_desc') }}</p>
            </div>

            {{-- v2.0.0 upgrade highlights --}}
            <div class="whats-new-box">
                <h4><i class="fas fa-rocket"></i> {{ trans('discordpings::help.whats_new_v2_title') }}</h4>
                <p>{!! trans('discordpings::help.whats_new_v2_intro') !!}</p>
                {!! trans('discordpings::help.whats_new_v2_list') !!}
                <p style="margin-top: 12px; margin-bottom: 0; font-size: 0.88rem; color: #8b95a5;">
                    <i class="fas fa-info-circle"></i>
                    {!! trans('discordpings::help.whats_new_v2_upgrade_note') !!}
                </p>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-info-circle"></i> {{ trans('discordpings::help.what_is_title') }}</h3>
                <p>{!! trans('discordpings::help.what_is_desc') !!}</p>

                <div class="info-box">
                    <i class="fas fa-lightbulb"></i>
                    <strong>{{ trans('discordpings::help.key_benefit') }}:</strong>
                    {{ trans('discordpings::help.key_benefit_desc') }}
                </div>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-star"></i> {{ trans('discordpings::help.key_features') }}</h3>
                <div class="feature-grid">
                    <div class="feature-item">
                        <h5><i class="fas fa-paper-plane"></i> {{ trans('discordpings::help.feature_send_title') }}</h5>
                        <p>{{ trans('discordpings::help.feature_send_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <h5><i class="fas fa-file-alt"></i> {{ trans('discordpings::help.feature_templates_title') }}</h5>
                        <p>{{ trans('discordpings::help.feature_templates_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <h5><i class="fas fa-clock"></i> {{ trans('discordpings::help.feature_scheduled_title') }}</h5>
                        <p>{{ trans('discordpings::help.feature_scheduled_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <h5><i class="fas fa-calendar-alt"></i> {{ trans('discordpings::help.feature_calendar_title') }}</h5>
                        <p>{{ trans('discordpings::help.feature_calendar_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <h5><i class="fas fa-history"></i> {{ trans('discordpings::help.feature_history_title') }}</h5>
                        <p>{{ trans('discordpings::help.feature_history_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <h5><i class="fas fa-cog"></i> {{ trans('discordpings::help.feature_config_title') }}</h5>
                        <p>{{ trans('discordpings::help.feature_config_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <h5><i class="fas fa-satellite-dish"></i> {{ trans('discordpings::help.feature_structure_timers_title') }}
                            <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                        </h5>
                        <p>{{ trans('discordpings::help.feature_structure_timers_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <h5><i class="fas fa-bullseye"></i> {{ trans('discordpings::help.feature_fc_opportunities_title') }}
                            <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                        </h5>
                        <p>{{ trans('discordpings::help.feature_fc_opportunities_desc') }}</p>
                    </div>
                </div>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-rocket"></i> {{ trans('discordpings::help.quick_links_title') }}</h3>

                <div class="quick-links">
                    <a href="{{ route('discordpings.send') }}" class="quick-link">
                        <i class="fas fa-paper-plane"></i>
                        {{ trans('discordpings::help.send_broadcast') }}
                    </a>
                    <a href="{{ route('discordpings.history') }}" class="quick-link">
                        <i class="fas fa-history"></i>
                        {{ trans('discordpings::help.view_history') }}
                    </a>
                    <a href="{{ route('discordpings.scheduled.calendar') }}" class="quick-link">
                        <i class="fas fa-calendar-alt"></i>
                        {{ trans('discordpings::help.view_calendar') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- What's New --}}
        <div class="help-section" id="whats-new">
            <div class="help-card">
                <h3><i class="fas fa-star"></i> {{ trans('discordpings::help.whats_new') }}</h3>
                <p>{{ trans('discordpings::help.whats_new_intro') }}</p>

                <h4>{{ trans('discordpings::help.whats_new_fc_opportunities_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_fc_opportunities_desc') }}</p>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> Open it from the sidebar: <strong>SeAT Broadcast &gt; FC Opportunities</strong>.</p>
                </div>

                <h4>{{ trans('discordpings::help.whats_new_structure_timers_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_structure_timers_desc') }}</p>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> The structure timer integration is optional. It activates only when <strong>Manager Core</strong> and <strong>Structure Manager</strong> are installed.</p>
                </div>

                <h4>{{ trans('discordpings::help.whats_new_prepping_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_prepping_desc') }}</p>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> The four broadcast types are now: <strong>📢 Fleet Broadcast</strong>, <strong>📣 Announcement</strong>, <strong>💬 Message</strong>, and <strong>‼️ PREPING ‼️</strong>.</p>
                </div>

                <h4>{{ trans('discordpings::help.whats_new_colorpicker_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_colorpicker_desc') }}</p>

                <h4>{{ trans('discordpings::help.whats_new_pap_config_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_pap_config_desc') }}</p>

                <h4>{{ trans('discordpings::help.whats_new_scheduled_visibility_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_scheduled_visibility_desc') }}</p>
                <div class="warning-box">
                    <p><i class="fas fa-shield-alt"></i> <strong>Permission required:</strong> Regular FCs (with just <strong>Send Discord Pings</strong>) manage their OWN scheduled broadcasts. To also see and manage OTHER users' broadcasts you need the <strong>Manage All Scheduled Pings</strong> permission (the "Fleet Coordinator" tier).</p>
                </div>

                <h4>{{ trans('discordpings::help.whats_new_calendar_history_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_calendar_history_desc') }}</p>

                <h4>{{ trans('discordpings::help.whats_new_bulk_clear_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_bulk_clear_desc') }}</p>

                <h4>{{ trans('discordpings::help.whats_new_edit_scheduled_title') }}</h4>
                <p>{{ trans('discordpings::help.whats_new_edit_scheduled_desc') }}</p>
            </div>
        </div>

        {{-- Getting Started --}}
        <div class="help-section" id="getting-started">
            <div class="help-card">
                <h3><i class="fas fa-rocket"></i> {{ trans('discordpings::help.getting_started') }}</h3>
                <p>{{ trans('discordpings::help.getting_started_intro') }}</p>

                <h4>{{ trans('discordpings::help.quick_start_guide') }}</h4>
                <ol class="step-list">
                    <li>{{ trans('discordpings::help.getting_started_step1') }}</li>
                    <li>{{ trans('discordpings::help.getting_started_step2') }}</li>
                    <li>{{ trans('discordpings::help.getting_started_step3') }}</li>
                    <li>{{ trans('discordpings::help.getting_started_step4') }}</li>
                    <li>{{ trans('discordpings::help.getting_started_step5') }}</li>
                    <li>{{ trans('discordpings::help.getting_started_step6') }}</li>
                </ol>
            </div>
        </div>

        {{-- Sending Pings --}}
        <div class="help-section" id="sending-pings">
            <div class="help-card">
                <h3><i class="fas fa-paper-plane"></i> {{ trans('discordpings::help.sending_pings') }}</h3>
                <p>{{ trans('discordpings::help.sending_pings_intro') }}</p>

                <h4>{{ trans('discordpings::help.sending_single') }}</h4>
                <p>{{ trans('discordpings::help.sending_single_desc') }}</p>

                <h4>{{ trans('discordpings::help.sending_multiple') }}</h4>
                <p>{{ trans('discordpings::help.sending_multiple_desc') }}</p>

                <h4>{{ trans('discordpings::help.sending_mentions') }}</h4>
                <p>{{ trans('discordpings::help.sending_mentions_desc') }}</p>

                <h4>{{ trans('discordpings::help.sending_embed_types') }}</h4>
                <p>{{ trans('discordpings::help.sending_embed_types_desc') }}</p>

                <h4>{{ trans('discordpings::help.sending_preview') }}</h4>
                <p>{{ trans('discordpings::help.sending_preview_desc') }}</p>

                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>{{ trans('discordpings::help.sending_schedule_redirect') }}:</strong> {{ trans('discordpings::help.sending_schedule_redirect_desc') }}</p>
                </div>
            </div>
        </div>

        {{-- Webhooks --}}
        <div class="help-section" id="webhooks">
            <div class="help-card">
                <h3><i class="fas fa-link"></i> {{ trans('discordpings::help.webhooks') }}</h3>
                <p>{{ trans('discordpings::help.webhooks_intro') }}</p>

                <h4>{{ trans('discordpings::help.webhooks_creating') }}</h4>
                <ol class="step-list">
                    <li>{{ trans('discordpings::help.webhooks_creating_step1') }}</li>
                    <li>{{ trans('discordpings::help.webhooks_creating_step2') }}</li>
                    <li>{{ trans('discordpings::help.webhooks_creating_step3') }}</li>
                    <li>{{ trans('discordpings::help.webhooks_creating_step4') }}</li>
                    <li>{{ trans('discordpings::help.webhooks_creating_step5') }}</li>
                    <li>{{ trans('discordpings::help.webhooks_creating_step6') }}</li>
                </ol>

                <h4>{{ trans('discordpings::help.webhooks_testing') }}</h4>
                <p>{{ trans('discordpings::help.webhooks_testing_desc') }}</p>

                <h4>{{ trans('discordpings::help.webhooks_managing') }}</h4>
                <p>{{ trans('discordpings::help.webhooks_managing_desc') }}</p>
            </div>
        </div>

        {{-- Templates --}}
        <div class="help-section" id="templates">
            <div class="help-card">
                <h3><i class="fas fa-file-alt"></i> {{ trans('discordpings::help.templates') }}</h3>
                <p>{{ trans('discordpings::help.templates_intro') }}</p>

                <h4>{{ trans('discordpings::help.templates_personal') }}</h4>
                <p>{{ trans('discordpings::help.templates_personal_desc') }}</p>

                <h4>{{ trans('discordpings::help.templates_global') }}</h4>
                <p>{{ trans('discordpings::help.templates_global_desc') }}</p>

                <h4>{{ trans('discordpings::help.templates_quick_save') }}</h4>
                <p>{{ trans('discordpings::help.templates_quick_save_desc') }}</p>

                <h4>{{ trans('discordpings::help.templates_using') }}</h4>
                <p>{{ trans('discordpings::help.templates_using_desc') }}</p>
            </div>
        </div>

        {{-- Scheduled --}}
        <div class="help-section" id="scheduled">
            <div class="help-card">
                <h3><i class="fas fa-clock"></i> {{ trans('discordpings::help.scheduled') }}</h3>
                <p>{{ trans('discordpings::help.scheduled_intro') }}</p>

                <h4>{{ trans('discordpings::help.scheduled_creating') }}</h4>
                <p>{{ trans('discordpings::help.scheduled_creating_desc') }}</p>

                <h4>{{ trans('discordpings::help.scheduled_repeat') }}</h4>
                <p>{{ trans('discordpings::help.scheduled_repeat_desc') }}</p>

                <h4>{{ trans('discordpings::help.scheduled_managing') }}</h4>
                <p>{{ trans('discordpings::help.scheduled_managing_desc') }}</p>

                <h4>{{ trans('discordpings::help.scheduled_editing') }}</h4>
                <p>{{ trans('discordpings::help.scheduled_editing_desc') }}</p>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Recurring series:</strong> Editing a recurring ping that has already fired updates all future occurrences from the new scheduled time. Past sends remain in broadcast history untouched.</p>
                </div>

                <h4>{{ trans('discordpings::help.scheduled_bulk_clear') }}</h4>
                <p>{{ trans('discordpings::help.scheduled_bulk_clear_desc') }}</p>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Requires:</strong> Manage All Scheduled Pings permission (Fleet Coordinator tier — bulk-clear touches everyone's inactive pings, so it's gated above the FC tier). Active upcoming pings are never touched.</p>
                </div>

                <div class="warning-box">
                    <p><i class="fas fa-exclamation-triangle"></i> <strong>{{ trans('discordpings::help.scheduled_limit') }}:</strong> {{ trans('discordpings::help.scheduled_limit_desc') }}</p>
                </div>
            </div>
        </div>

        {{-- Calendar --}}
        <div class="help-section" id="calendar">
            <div class="help-card">
                <h3><i class="fas fa-calendar-alt"></i> {{ trans('discordpings::help.calendar') }}</h3>
                <p>{{ trans('discordpings::help.calendar_intro') }}</p>

                <h4>{{ trans('discordpings::help.calendar_navigation') }}</h4>
                <p>{{ trans('discordpings::help.calendar_navigation_desc') }}</p>

                <h4>{{ trans('discordpings::help.calendar_events') }}</h4>
                <p>{{ trans('discordpings::help.calendar_events_desc') }}</p>

                <h4>{{ trans('discordpings::help.calendar_history') }}</h4>
                <p>{{ trans('discordpings::help.calendar_history_desc') }}</p>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Permissions:</strong> <strong>View Ping History</strong> shows your own sent broadcasts on the calendar. <strong>View All History</strong> shows sent broadcasts from all users.</p>
                </div>

                <h4>{{ trans('discordpings::help.calendar_click') }}</h4>
                <p>{{ trans('discordpings::help.calendar_click_desc') }}</p>

                <h4>{{ trans('discordpings::help.calendar_local_time_title') }}</h4>
                <p>{{ trans('discordpings::help.calendar_local_time_desc') }}</p>
                <div class="info-box">
                    <p>
                        <i class="fas fa-globe"></i>
                        <strong>Your browser timezone (right now):</strong>
                        <code id="detected-tz-name">detecting…</code>
                        — sample conversion: <code id="detected-tz-sample">…</code>
                    </p>
                    <p class="text-muted" style="font-size: 0.85em; margin-bottom: 0;">
                        If this doesn't match your physical timezone, check your operating
                        system's Date &amp; Time settings — the browser inherits its timezone
                        from there.
                    </p>
                </div>
                <script>
                    (function () {
                        try {
                            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'unknown';
                            var nowSample = new Intl.DateTimeFormat(undefined, {
                                year: 'numeric', month: '2-digit', day: '2-digit',
                                hour: '2-digit', minute: '2-digit', timeZoneName: 'short'
                            }).format(new Date());
                            var elTz     = document.getElementById('detected-tz-name');
                            var elSample = document.getElementById('detected-tz-sample');
                            if (elTz)     elTz.textContent = tz;
                            if (elSample) elSample.textContent = nowSample;
                        } catch (e) {
                            // Old browser — leave placeholders.
                        }
                    })();
                </script>
            </div>
        </div>

        {{-- Structure Timers --}}
        <div class="help-section" id="structure-timers">
            <div class="help-card">
                <h3><i class="fas fa-satellite-dish"></i> {{ trans('discordpings::help.structure_timers') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                    <span class="mc-badge">{{ trans('discordpings::help.mc_badge') }}</span>
                </h3>
                <p>{!! trans('discordpings::help.structure_timers_intro') !!}</p>

                <div class="warning-box">
                    <p><i class="fas fa-shield-alt"></i> <strong>{{ trans('discordpings::help.structure_timers_requirements_title') }}:</strong> {{ trans('discordpings::help.structure_timers_requirements_desc') }}</p>
                </div>

                <h4>{{ trans('discordpings::help.structure_timers_how_title') }}</h4>
                <p>{{ trans('discordpings::help.structure_timers_how_desc') }}</p>

                <h4>{{ trans('discordpings::help.structure_timers_planner_title') }}</h4>
                <p>{!! trans('discordpings::help.structure_timers_planner_desc') !!}</p>

                <h4>{{ trans('discordpings::help.structure_timers_pings_title') }}</h4>
                <p>{{ trans('discordpings::help.structure_timers_pings_desc') }}</p>

                <h4>{{ trans('discordpings::help.structure_timers_opsec_title') }}</h4>
                <p>{{ trans('discordpings::help.structure_timers_opsec_desc') }}</p>

                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>{{ trans('discordpings::help.structure_timers_retention_title') }}:</strong> {{ trans('discordpings::help.structure_timers_retention_desc') }}</p>
                </div>
            </div>
        </div>

        {{-- Mining Extractions --}}
        <div class="help-section" id="mining-extractions">
            <div class="help-card">
                <h3><i class="fas fa-gem"></i> {{ trans('discordpings::help.mining_extractions_title') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                    <span class="mc-badge">{{ trans('discordpings::help.mc_badge') }}</span>
                </h3>
                <p>{!! trans('discordpings::help.mining_extractions_intro') !!}</p>

                <h4>{{ trans('discordpings::help.mining_extractions_lifecycle_title') }}</h4>
                <p>{!! trans('discordpings::help.mining_extractions_lifecycle_desc') !!}</p>

                <h4>{{ trans('discordpings::help.mining_extractions_multi_fc_title') }}</h4>
                <p>{{ trans('discordpings::help.mining_extractions_multi_fc_desc') }}</p>

                <h4>{{ trans('discordpings::help.mining_extractions_alerts_title') }}</h4>
                <p>{{ trans('discordpings::help.mining_extractions_alerts_desc') }}</p>

                <h4>{{ trans('discordpings::help.mining_extractions_opsec_title') }}</h4>
                <p>{{ trans('discordpings::help.mining_extractions_opsec_desc') }}</p>
            </div>
        </div>

        {{-- FC Opportunities --}}
        <div class="help-section" id="fc-opportunities">
            <div class="help-card">
                <h3><i class="fas fa-bullseye"></i> {{ trans('discordpings::help.fc_opportunities') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                    <span class="mc-badge">{{ trans('discordpings::help.mc_badge') }}</span>
                </h3>
                <p>{{ trans('discordpings::help.fc_opportunities_intro') }}</p>

                <div class="warning-box">
                    <p><i class="fas fa-shield-alt"></i> <strong>{{ trans('discordpings::help.fc_opportunities_requires_title') }}:</strong> {{ trans('discordpings::help.fc_opportunities_requires_desc') }}</p>
                </div>

                <h4>{{ trans('discordpings::help.fc_opportunities_what_title') }}</h4>
                <p>{{ trans('discordpings::help.fc_opportunities_what_desc') }}</p>

                <h4>{{ trans('discordpings::help.fc_opportunities_workflow_title') }}</h4>
                <p>{!! trans('discordpings::help.fc_opportunities_workflow_desc') !!}</p>

                <h4>{{ trans('discordpings::help.fc_opportunities_source_title') }}</h4>
                <p>{!! trans('discordpings::help.fc_opportunities_source_desc') !!}</p>

                <h4>{{ trans('discordpings::help.fc_opportunities_indicators_title') }}</h4>
                <p>{{ trans('discordpings::help.fc_opportunities_indicators_desc') }}</p>

                <h4>{{ trans('discordpings::help.fc_opportunities_offset_title') }}</h4>
                <p>{{ trans('discordpings::help.fc_opportunities_offset_desc') }}</p>

                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Permissions:</strong> Any user with the <strong>Send Discord Pings</strong> permission can access FC Opportunities, schedule pings for ops, and manage their own scheduled pings on the Calendar and list views. The higher <strong>Manage All Scheduled Pings</strong> permission adds visibility and control over OTHER users' pings (the "Fleet Coordinator" tier). The "📡 N scheduled" badge on every op shows the <strong>global</strong> count of formup broadcasts already scheduled, so even tier-1 FCs see when an op is covered and don't duplicate effort.</p>
                </div>
            </div>
        </div>

        {{-- Published Events --}}
        <div class="help-section" id="published-events">
            <div class="help-card">
                <h3><i class="fas fa-broadcast-tower"></i> {{ trans('discordpings::help.published_events') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                    <span class="mc-badge">{{ trans('discordpings::help.mc_badge') }}</span>
                </h3>
                <p>{!! trans('discordpings::help.published_events_intro') !!}</p>

                <h4>{{ trans('discordpings::help.published_events_catalog_title') }}</h4>
                <p>{!! trans('discordpings::help.published_events_catalog_desc') !!}</p>

                <h4><code>{{ trans('discordpings::help.published_events_broadcast_sent_title') }}</code></h4>
                <p>{!! trans('discordpings::help.published_events_broadcast_sent_desc') !!}</p>

                <h4><code>{{ trans('discordpings::help.published_events_formup_scheduled_title') }}</code></h4>
                <p>{!! trans('discordpings::help.published_events_formup_scheduled_desc') !!}</p>

                <div class="info-box">
                    <p><i class="fas fa-shield-alt"></i> <strong>{{ trans('discordpings::help.published_events_safety_title') }}:</strong> {{ trans('discordpings::help.published_events_safety_desc') }}</p>
                </div>
            </div>
        </div>

        {{-- Configuration --}}
        <div class="help-section" id="configuration">
            <div class="help-card">
                <h3><i class="fas fa-cog"></i> {{ trans('discordpings::help.configuration') }}</h3>
                <p>{{ trans('discordpings::help.configuration_intro') }}</p>

                <h4>{{ trans('discordpings::help.configuration_roles') }}</h4>
                <p>{{ trans('discordpings::help.configuration_roles_desc') }}</p>

                <h4>{{ trans('discordpings::help.configuration_channels') }}</h4>
                <p>{{ trans('discordpings::help.configuration_channels_desc') }}</p>

                <h4>{{ trans('discordpings::help.configuration_stagings') }}</h4>
                <p>{{ trans('discordpings::help.configuration_stagings_desc') }}</p>

                <h4>{{ trans('discordpings::help.configuration_pap_types') }}</h4>
                <p>{{ trans('discordpings::help.configuration_pap_types_desc') }}</p>

                <h4>{{ trans('discordpings::help.configuration_structure_timers') }}</h4>
                <p>{{ trans('discordpings::help.configuration_structure_timers_desc') }}</p>

                <h4>{{ trans('discordpings::help.configuration_routing_map') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                </h4>
                <p>{{ trans('discordpings::help.configuration_routing_map_desc') }}</p>
            </div>
        </div>

        {{-- Pages Guide --}}
        <div class="help-section" id="pages">
            <div class="help-card">
                <h3><i class="fas fa-th-large"></i> {{ trans('discordpings::help.pages_guide') }}</h3>
                <p>{{ trans('discordpings::help.pages_guide_intro') }}</p>

                <h4><i class="fas fa-paper-plane"></i> {{ trans('discordpings::help.page_send') }}</h4>
                <p>{{ trans('discordpings::help.page_send_desc') }}</p>

                <h4><i class="fas fa-history"></i> {{ trans('discordpings::help.page_history') }}</h4>
                <p>{{ trans('discordpings::help.page_history_desc') }}</p>

                <h4><i class="fas fa-clock"></i> {{ trans('discordpings::help.page_scheduled') }}</h4>
                <p>{{ trans('discordpings::help.page_scheduled_desc') }}</p>

                <h4><i class="fas fa-calendar-alt"></i> {{ trans('discordpings::help.page_calendar') }}</h4>
                <p>{{ trans('discordpings::help.page_calendar_desc') }}</p>

                <h4><i class="fas fa-bullseye"></i> {{ trans('discordpings::help.page_opportunities') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                    <span class="mc-badge">{{ trans('discordpings::help.mc_badge') }}</span>
                </h4>
                <p>{{ trans('discordpings::help.page_opportunities_desc') }}</p>

                <h4><i class="fas fa-file-alt"></i> {{ trans('discordpings::help.page_templates') }}</h4>
                <p>{{ trans('discordpings::help.page_templates_desc') }}</p>

                <h4><i class="fas fa-link"></i> {{ trans('discordpings::help.page_webhooks') }}</h4>
                <p>{{ trans('discordpings::help.page_webhooks_desc') }}</p>

                <h4><i class="fas fa-cog"></i> {{ trans('discordpings::help.page_config') }}</h4>
                <p>{{ trans('discordpings::help.page_config_desc') }}</p>
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Requires:</strong> Manage Webhooks permission to access the Settings page.</p>
                </div>

                <h4><i class="fas fa-stethoscope"></i> {{ trans('discordpings::help.page_diagnostic') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                </h4>
                <p>{{ trans('discordpings::help.page_diagnostic_desc') }}</p>
                <div class="warning-box">
                    <p><i class="fas fa-shield-alt"></i> <strong>Admin-only:</strong> Requires the <strong>Plugin Admin</strong> permission. Intentionally NOT in the sidebar nav to hide troubleshooting clutter from daily ops. Reach via <code>/discord-pings/diagnostic</code>.</p>
                </div>
            </div>
        </div>

        {{-- Commands --}}
        <div class="help-section" id="commands">
            <div class="help-card">
                <h3><i class="fas fa-terminal"></i> {{ trans('discordpings::help.commands') }}</h3>
                <p>{{ trans('discordpings::help.commands_intro') }}</p>

                <h4>{{ trans('discordpings::help.command_process_scheduled') }}</h4>
                <p>{{ trans('discordpings::help.command_process_scheduled_desc') }}</p>
                <pre><code>php artisan discordpings:process-scheduled</code></pre>

                <h4>{{ trans('discordpings::help.command_cleanup') }}</h4>
                <p>{{ trans('discordpings::help.command_cleanup_desc') }}</p>
                <pre><code>php artisan discordpings:cleanup-history</code></pre>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ trans('discordpings::help.scheduler_note') }}:</strong>
                    {{ trans('discordpings::help.scheduler_note_desc') }}
                </div>
            </div>
        </div>

        {{-- Permissions --}}
        <div class="help-section" id="permissions">
            <div class="help-card">
                <h3><i class="fas fa-user-shield"></i> {{ trans('discordpings::help.permissions') }}</h3>
                <p>{!! trans('discordpings::help.permissions_intro') !!}</p>

                {{-- Tier Model --}}
                <h4>{{ trans('discordpings::help.permissions_tier_model_title') }}</h4>
                <p>{{ trans('discordpings::help.permissions_tier_model_desc') }}</p>

                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-top: 0.75rem;">
                        <thead>
                            <tr>
                                <th>Capability</th>
                                <th>Tier 1: <code>discordpings.send</code><br><small class="text-muted">("Send Discord Pings" — the FC tier)</small></th>
                                <th>Tier 2: <code>discordpings.manage_scheduled</code><br><small class="text-muted">("Manage All Scheduled Pings" — the Fleet Coordinator tier, ON TOP of tier 1)</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Send manual broadcasts</td>
                                <td><i class="fas fa-check text-success"></i></td>
                                <td><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>Schedule a new ping (own)</td>
                                <td><i class="fas fa-check text-success"></i></td>
                                <td><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>See own scheduled pings in list / calendar</td>
                                <td><i class="fas fa-check text-success"></i></td>
                                <td><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>Edit / delete own scheduled pings</td>
                                <td><i class="fas fa-check text-success"></i></td>
                                <td><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>Use FC Opportunities planner</td>
                                <td><i class="fas fa-check text-success"></i></td>
                                <td><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>See "📡 N scheduled" global coordination badge on FC Opportunities</td>
                                <td><i class="fas fa-check text-success"></i> (count only)</td>
                                <td><i class="fas fa-check text-success"></i> (count + can drill into the underlying pings)</td>
                            </tr>
                            <tr style="border-top: 2px solid var(--pings-border);">
                                <td>See OTHER users' scheduled pings in list / calendar</td>
                                <td><i class="fas fa-times text-danger"></i></td>
                                <td><i class="fas fa-check text-success"></i> (with "Created By" column)</td>
                            </tr>
                            <tr>
                                <td>Edit / delete OTHER users' scheduled pings</td>
                                <td><i class="fas fa-times text-danger"></i> (403 if attempted)</td>
                                <td><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>Bulk-clear inactive pings older than N days</td>
                                <td><i class="fas fa-times text-danger"></i></td>
                                <td><i class="fas fa-check text-success"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="info-box" style="margin-top: 0.75rem;">
                    <p><i class="fas fa-info-circle"></i> The calendar's "manual broadcast history" events (the past sent broadcasts displayed as small dots) follow a <strong>separate</strong> permission pair: <strong>View Ping History</strong> (own) and <strong>View All History</strong> (everyone's). A tier-1 FC with only <code>view_history</code> sees own scheduled pings + own past broadcasts on the calendar; pair them with <code>view_all_history</code> to see everyone's past broadcasts while still only seeing their own scheduled pings.</p>
                </div>

                {{-- Scenario recipes --}}
                <h4 class="mt-4">{{ trans('discordpings::help.permissions_scenarios_title') }}</h4>
                <p>{{ trans('discordpings::help.permissions_scenarios_desc') }}</p>

                <div class="feature-grid">
                    <div class="feature-item" style="border-left: 4px solid #1abc9c;">
                        <h5><i class="fas fa-user"></i> Solo FC (small corp)</h5>
                        <p>One person runs all ops.</p>
                        <ul style="margin-bottom: 0;">
                            <li><code>discordpings.view</code></li>
                            <li><code>discordpings.send</code></li>
                            <li><code>discordpings.view_history</code></li>
                        </ul>
                        <p style="margin-top: 0.5rem; font-size: 0.85rem;" class="text-muted">Can do everything; nothing to coordinate.</p>
                    </div>

                    <div class="feature-item" style="border-left: 4px solid #667eea;">
                        <h5><i class="fas fa-users"></i> Regular FC (multi-FC corp)</h5>
                        <p>One of several FCs sharing the duty.</p>
                        <ul style="margin-bottom: 0;">
                            <li><code>discordpings.view</code></li>
                            <li><code>discordpings.send</code></li>
                            <li><code>discordpings.view_history</code> (or <code>view_all_history</code> for full transparency)</li>
                        </ul>
                        <p style="margin-top: 0.5rem; font-size: 0.85rem;" class="text-muted">Plans and runs their own ops; sees the global "📡 N scheduled" coordination badge so they don't duplicate work.</p>
                    </div>

                    <div class="feature-item" style="border-left: 4px solid #f39c12;">
                        <h5><i class="fas fa-shield-alt"></i> Fleet Coordinator</h5>
                        <p>Oversees all FC scheduling.</p>
                        <ul style="margin-bottom: 0;">
                            <li>Everything in <em>Regular FC</em> +</li>
                            <li><code>discordpings.manage_scheduled</code></li>
                            <li><code>discordpings.view_all_history</code></li>
                        </ul>
                        <p style="margin-top: 0.5rem; font-size: 0.85rem;" class="text-muted">Sees every FC's pings, can resolve overlaps, can bulk-clear old inactive entries.</p>
                    </div>

                    <div class="feature-item" style="border-left: 4px solid #6c757d;">
                        <h5><i class="fas fa-eye"></i> Auditor / Read-only Director</h5>
                        <p>Doesn't run fleets but wants visibility.</p>
                        <ul style="margin-bottom: 0;">
                            <li><code>discordpings.view</code></li>
                            <li><code>discordpings.view_all_history</code></li>
                        </ul>
                        <p style="margin-top: 0.5rem; font-size: 0.85rem;" class="text-muted">Sees the History page across all users. No scheduling, no planning surface. Calendar / FC Opportunities are hidden (no <code>send</code>).</p>
                    </div>

                    <div class="feature-item" style="border-left: 4px solid #dc3545;">
                        <h5><i class="fas fa-cog"></i> Plugin Admin</h5>
                        <p>Operations engineer / SeAT admin.</p>
                        <ul style="margin-bottom: 0;">
                            <li>All FC + Coordinator perms +</li>
                            <li><code>discordpings.manage_webhooks</code></li>
                            <li><code>discordpings.manage_global_templates</code></li>
                            <li><code>discordpings.admin</code> (Diagnostic page)</li>
                            <li><code>discordpings.send_multiple</code> (multi-webhook broadcast)</li>
                        </ul>
                        <p style="margin-top: 0.5rem; font-size: 0.85rem;" class="text-muted">Full control. Can configure webhooks, roles, channels, troubleshoot via the Diagnostic page.</p>
                    </div>
                </div>

                {{-- Full reference --}}
                <h4 class="mt-4">{{ trans('discordpings::help.permissions_reference_title') }}</h4>
                <p>{{ trans('discordpings::help.permissions_reference_desc') }}</p>

                <ul class="permissions-list">
                    <li><strong>View Discord Pings</strong> (<code>discordpings.view</code>) — {{ trans('discordpings::help.perm_view') }}</li>
                    <li><strong>Send Discord Pings</strong> (<code>discordpings.send</code>) — {{ trans('discordpings::help.perm_send') }}</li>
                    <li><strong>Send to Multiple Webhooks</strong> (<code>discordpings.send_multiple</code>) — {{ trans('discordpings::help.perm_send_multiple') }}</li>
                    <li><strong>Manage Webhooks</strong> (<code>discordpings.manage_webhooks</code>) — {{ trans('discordpings::help.perm_manage_webhooks') }}</li>
                    <li><strong>View Ping History</strong> (<code>discordpings.view_history</code>) — {{ trans('discordpings::help.perm_view_history') }}</li>
                    <li><strong>View All History</strong> (<code>discordpings.view_all_history</code>) — {{ trans('discordpings::help.perm_view_all_history') }}</li>
                    <li><strong>Manage All Scheduled Pings</strong> (<code>discordpings.manage_scheduled</code>) — {{ trans('discordpings::help.perm_manage_scheduled') }}</li>
                    <li><strong>Manage Templates</strong> (<code>discordpings.manage_templates</code>) — {{ trans('discordpings::help.perm_manage_templates') }}</li>
                    <li><strong>Manage Global Templates</strong> (<code>discordpings.manage_global_templates</code>) — {{ trans('discordpings::help.perm_manage_global_templates') }}</li>
                    <li><strong>Plugin Admin</strong> (<code>discordpings.admin</code>) — {{ trans('discordpings::help.perm_admin') }}</li>
                </ul>
            </div>
        </div>

        {{-- FAQ --}}
        <div class="help-section" id="faq">
            <div class="help-card">
                <h3><i class="fas fa-question-circle"></i> {{ trans('discordpings::help.faq') }}</h3>

                @for($i = 1; $i <= 13; $i++)
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>{{ trans('discordpings::help.faq_q' . $i) }}</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            {{ trans('discordpings::help.faq_a' . $i) }}
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Troubleshooting --}}
        <div class="help-section" id="troubleshooting">
            <div class="help-card">
                <h3><i class="fas fa-wrench"></i> {{ trans('discordpings::help.troubleshooting') }}</h3>

                <h4><i class="fas fa-exclamation-circle text-danger"></i> {{ trans('discordpings::help.issue_webhook_fail_title') }}</h4>
                <ul>
                    <li>{{ trans('discordpings::help.issue_webhook_fail_solution1') }}</li>
                    <li>{{ trans('discordpings::help.issue_webhook_fail_solution2') }}</li>
                    <li>{{ trans('discordpings::help.issue_webhook_fail_solution3') }}</li>
                </ul>

                <h4><i class="fas fa-exclamation-circle text-warning"></i> {{ trans('discordpings::help.issue_scheduled_not_sending_title') }}</h4>
                <ul>
                    <li>{{ trans('discordpings::help.issue_scheduled_not_sending_solution1') }}</li>
                    <li>{{ trans('discordpings::help.issue_scheduled_not_sending_solution2') }}</li>
                    <li>{{ trans('discordpings::help.issue_scheduled_not_sending_solution3') }}</li>
                </ul>

                <h4><i class="fas fa-exclamation-circle text-info"></i> {{ trans('discordpings::help.issue_no_permissions_title') }}</h4>
                <ul>
                    <li>{{ trans('discordpings::help.issue_no_permissions_solution1') }}</li>
                    <li>{{ trans('discordpings::help.issue_no_permissions_solution2') }}</li>
                    <li>{{ trans('discordpings::help.issue_no_permissions_solution3') }}</li>
                </ul>

                <h4><i class="fas fa-exclamation-circle text-warning"></i> {{ trans('discordpings::help.issue_mentions_not_working_title') }}</h4>
                <ul>
                    <li>{{ trans('discordpings::help.issue_mentions_not_working_solution1') }}</li>
                    <li>{{ trans('discordpings::help.issue_mentions_not_working_solution2') }}</li>
                    <li>{{ trans('discordpings::help.issue_mentions_not_working_solution3') }}</li>
                </ul>

                <h4><i class="fas fa-exclamation-circle text-info"></i> {{ trans('discordpings::help.issue_fc_opps_empty_title') }}
                    <span class="v2-badge v2-badge-inline">{{ trans('discordpings::help.v2_badge') }}</span>
                </h4>
                <ul>
                    <li>{{ trans('discordpings::help.issue_fc_opps_empty_solution1') }}</li>
                    <li>{{ trans('discordpings::help.issue_fc_opps_empty_solution2') }}</li>
                    <li>{{ trans('discordpings::help.issue_fc_opps_empty_solution3') }}</li>
                    <li>{{ trans('discordpings::help.issue_fc_opps_empty_solution4') }}</li>
                </ul>
            </div>
        </div>

    </div>
</div>
</div>
@stop

@push('javascript')
<script>
$(document).ready(function() {
    // Section navigation
    $('.help-nav .nav-link').click(function(e) {
        e.preventDefault();
        var section = $(this).data('section');

        $('.help-nav .nav-link').removeClass('active');
        $(this).addClass('active');

        $('.help-section').removeClass('active');
        $('#' + section).addClass('active');

        window.location.hash = section;
    });

    // Load from hash
    if (window.location.hash) {
        var section = window.location.hash.substring(1);
        var $link = $(`.help-nav .nav-link[data-section="${section}"]`);
        if ($link.length) {
            $link.click();
        }
    }

    // FAQ toggle
    $('.faq-question').click(function() {
        $(this).toggleClass('open');
        $(this).next('.faq-answer').toggleClass('open');
    });

    // Search
    var searchTimeout;
    $('#helpSearch').on('input', function() {
        clearTimeout(searchTimeout);
        var query = $(this).val().toLowerCase().trim();

        searchTimeout = setTimeout(function() {
            if (!query) {
                $('.help-card').show();
                $('.help-nav .nav-link').first().click();
                return;
            }

            // Show all sections for searching
            $('.help-section').addClass('active');

            $('.help-card').each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) > -1);
            });
        }, 300);
    });
});
</script>
@endpush
