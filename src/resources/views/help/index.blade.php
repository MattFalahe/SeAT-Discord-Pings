@extends('web::layouts.grids.12')

@section('title', trans('discordpings::help.help_documentation'))
@section('page_header', trans('discordpings::help.help_documentation'))

@push('head')
<style>
    .help-wrapper {
        display: flex;
        gap: 20px;
    }

    .help-sidebar {
        flex: 0 0 280px;
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }

    .help-content {
        flex: 1;
        min-width: 0;
    }

    .help-nav .nav-link {
        color: #e2e8f0;
        border-radius: 5px;
        margin-bottom: 5px;
        padding: 10px 15px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }

    .help-nav .nav-link:hover {
        background: rgba(23, 162, 184, 0.2);
    }

    .help-nav .nav-link.active {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    }

    .help-nav .nav-link i {
        width: 24px;
        text-align: center;
        margin-right: 10px;
    }

    .help-section {
        display: none;
        animation: fadeIn 0.3s;
    }

    .help-section.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .help-card {
        background: #2d3748;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        border: 1px solid rgba(23, 162, 184, 0.2);
    }

    .help-card h3 {
        color: #17a2b8;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .help-card h4 {
        color: #9ca3af;
        margin-top: 20px;
        margin-bottom: 10px;
        font-size: 1.1rem;
    }

    .help-card h5 {
        color: #9ca3af;
        margin-top: 15px;
        margin-bottom: 8px;
        font-size: 1rem;
    }

    .help-card p, .help-card li {
        color: #d1d5db;
        line-height: 1.6;
    }

    .help-card ul, .help-card ol {
        color: #d1d5db;
        line-height: 1.8;
        margin-left: 20px;
    }

    .help-card code {
        background: rgba(0, 0, 0, 0.3);
        padding: 2px 6px;
        border-radius: 3px;
        color: #fbbf24;
        font-size: 0.9em;
    }

    .help-card pre {
        background: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
        color: #d1d5db;
    }

    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .feature-item {
        background: rgba(23, 162, 184, 0.1);
        border: 1px solid rgba(23, 162, 184, 0.2);
        border-radius: 8px;
        padding: 15px;
        transition: transform 0.2s, border-color 0.2s;
    }

    .feature-item:hover {
        transform: translateY(-2px);
        border-color: #17a2b8;
    }

    .feature-item h5 {
        color: #17a2b8;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .feature-item p {
        color: #9ca3af;
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .info-box {
        background: rgba(23, 162, 184, 0.15);
        border-left: 4px solid #17a2b8;
        padding: 15px;
        border-radius: 0 8px 8px 0;
        margin: 15px 0;
        color: #d1d5db;
        line-height: 1.6;
    }

    .info-box p {
        margin-bottom: 0;
    }

    .info-box i {
        margin-right: 8px;
    }

    .info-box strong {
        margin-right: 5px;
    }

    .warning-box {
        background: rgba(250, 177, 21, 0.1);
        border-left: 4px solid #fab115;
        padding: 15px;
        border-radius: 0 8px 8px 0;
        margin: 15px 0;
        color: #d1d5db;
        line-height: 1.6;
    }

    .warning-box p {
        margin-bottom: 0;
    }

    .success-box {
        background: rgba(72, 187, 120, 0.1);
        border-left: 4px solid #48bb78;
        padding: 15px;
        border-radius: 0 8px 8px 0;
        margin: 15px 0;
        color: #d1d5db;
        line-height: 1.6;
    }

    .success-box p {
        margin-bottom: 0;
    }

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
    }

    .step-list li::before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        width: 30px;
        height: 30px;
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.85rem;
    }

    .faq-item {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        margin-bottom: 10px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s;
    }

    .faq-item:hover {
        border-color: rgba(23, 162, 184, 0.3);
    }

    .faq-question {
        padding: 15px 20px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #e2e8f0;
        font-weight: 500;
        transition: background 0.2s;
    }

    .faq-question:hover {
        background: rgba(23, 162, 184, 0.1);
    }

    .faq-question i {
        transition: transform 0.3s;
        color: #17a2b8;
    }

    .faq-question.open i {
        transform: rotate(180deg);
    }

    .faq-answer {
        display: none;
        padding: 0 20px 15px;
        color: #9ca3af;
        line-height: 1.6;
    }

    .faq-answer.open {
        display: block;
    }

    .perm-table {
        width: 100%;
    }

    .perm-table td {
        padding: 8px 12px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        color: #d1d5db;
    }

    .perm-table td:first-child {
        color: #17a2b8;
        font-weight: 500;
        white-space: nowrap;
        width: 1%;
    }

    .search-box {
        position: relative;
        margin-bottom: 15px;
    }

    .search-box input {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(23, 162, 184, 0.3);
        color: #e2e8f0;
        border-radius: 8px;
        padding: 8px 15px 8px 35px;
        width: 100%;
    }

    .search-box input:focus {
        outline: none;
        border-color: #17a2b8;
    }

    .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
    }

    .plugin-info {
        background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(23, 162, 184, 0.3);
    }

    .plugin-info h3 {
        color: #17a2b8;
        margin-bottom: 15px;
    }

    .plugin-info .info-row {
        color: #9ca3af;
        margin: 5px 0;
    }

    .plugin-info .author {
        color: #17a2b8;
        margin: 10px 0;
    }

    .plugin-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }

    .plugin-link {
        background: rgba(23, 162, 184, 0.1);
        padding: 10px;
        border-radius: 5px;
        border: 1px solid rgba(23, 162, 184, 0.3);
        color: #17a2b8;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .plugin-link:hover {
        background: rgba(23, 162, 184, 0.2);
        color: #40d3ff;
        text-decoration: none;
        transform: translateX(5px);
    }

    .quick-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 20px 0;
    }

    .quick-link {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        color: white;
        text-decoration: none;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .quick-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
        color: white;
        text-decoration: none;
    }

    .quick-link i {
        font-size: 2rem;
        margin-bottom: 8px;
        display: block;
    }

    @media (max-width: 768px) {
        .help-wrapper {
            flex-direction: column;
        }
        .help-sidebar {
            position: static;
            max-height: none;
            flex: none;
        }
        .feature-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('full')
<div class="help-wrapper">
    {{-- Sidebar --}}
    <div class="help-sidebar">
        <div class="card">
            <div class="card-body p-3">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="helpSearch" placeholder="{{ trans('discordpings::help.search_placeholder') }}">
                </div>
                <nav class="help-nav">
                    <a href="#" class="nav-link active" data-section="overview">
                        <i class="fas fa-home"></i> {{ trans('discordpings::help.overview') }}
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
                    <a href="#" class="nav-link" data-section="configuration">
                        <i class="fab fa-discord"></i> {{ trans('discordpings::help.configuration') }}
                    </a>
                    <a href="#" class="nav-link" data-section="pages">
                        <i class="fas fa-th-large"></i> {{ trans('discordpings::help.pages_guide') }}
                    </a>
                    <a href="#" class="nav-link" data-section="commands">
                        <i class="fas fa-terminal"></i> {{ trans('discordpings::help.commands') }}
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
                    <img src="https://img.shields.io/github/v/release/MattFalahe/SeAT-Discord-Pings" alt="Version" style="vertical-align: middle;">
                    <img src="https://img.shields.io/badge/SeAT-5.0-green" alt="SeAT" style="vertical-align: middle;">
                </div>
                <div class="info-row">
                    <strong>{{ trans('discordpings::help.license') }}:</strong> GPL-2.0
                </div>

                <div class="author">
                    <i class="fas fa-user"></i> <strong>{{ trans('discordpings::help.author') }}:</strong> Matt Falahe
                    <br>
                    <i class="fas fa-envelope"></i> <a href="mailto:mattfalahe@gmail.com" style="color: #17a2b8;">mattfalahe@gmail.com</a>
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

            <div class="help-card">
                <h3><i class="fas fa-bullhorn"></i> {{ trans('discordpings::help.welcome_title') }}</h3>
                <p>{{ trans('discordpings::help.welcome_desc') }}</p>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-info-circle"></i> {{ trans('discordpings::help.what_is_title') }}</h3>
                <p>{{ trans('discordpings::help.what_is_desc') }}</p>

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
                        <h5><i class="fab fa-discord"></i> {{ trans('discordpings::help.feature_config_title') }}</h5>
                        <p>{{ trans('discordpings::help.feature_config_desc') }}</p>
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
                </ol>

                <h4>{{ trans('discordpings::help.installation_title') }}</h4>
                <ol class="step-list">
                    <li>
                        <strong>{{ trans('discordpings::help.install_step1_title') }}</strong><br>
                        {{ trans('discordpings::help.install_step1_desc') }}
                        <pre><code>composer require mattfalahe/seat-discord-pings</code></pre>
                    </li>
                    <li>
                        <strong>{{ trans('discordpings::help.install_step2_title') }}</strong><br>
                        {{ trans('discordpings::help.install_step2_desc') }}
                        <pre><code>php artisan migrate
php artisan vendor:publish --tag=public --force</code></pre>
                    </li>
                    <li>
                        <strong>{{ trans('discordpings::help.install_step3_title') }}</strong><br>
                        {{ trans('discordpings::help.install_step3_desc') }}
                    </li>
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

                <h4>{{ trans('discordpings::help.calendar_click') }}</h4>
                <p>{{ trans('discordpings::help.calendar_click_desc') }}</p>
            </div>
        </div>

        {{-- Configuration --}}
        <div class="help-section" id="configuration">
            <div class="help-card">
                <h3><i class="fab fa-discord"></i> {{ trans('discordpings::help.configuration') }}</h3>
                <p>{{ trans('discordpings::help.configuration_intro') }}</p>

                <h4>{{ trans('discordpings::help.configuration_roles') }}</h4>
                <p>{{ trans('discordpings::help.configuration_roles_desc') }}</p>

                <h4>{{ trans('discordpings::help.configuration_channels') }}</h4>
                <p>{{ trans('discordpings::help.configuration_channels_desc') }}</p>

                <h4>{{ trans('discordpings::help.configuration_stagings') }}</h4>
                <p>{{ trans('discordpings::help.configuration_stagings_desc') }}</p>
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

                <h4><i class="fas fa-file-alt"></i> {{ trans('discordpings::help.page_templates') }}</h4>
                <p>{{ trans('discordpings::help.page_templates_desc') }}</p>

                <h4><i class="fas fa-link"></i> {{ trans('discordpings::help.page_webhooks') }}</h4>
                <p>{{ trans('discordpings::help.page_webhooks_desc') }}</p>

                <h4><i class="fas fa-cog"></i> {{ trans('discordpings::help.page_config') }}</h4>
                <p>{{ trans('discordpings::help.page_config_desc') }}</p>
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

                <h4>{{ trans('discordpings::help.command_setup_permissions') }}</h4>
                <p>{{ trans('discordpings::help.command_setup_permissions_desc') }}</p>
                <pre><code>php artisan discordpings:setup-permissions</code></pre>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ trans('discordpings::help.scheduler_note') }}:</strong>
                    {{ trans('discordpings::help.scheduler_note_desc') }}
                </div>
            </div>
        </div>

        {{-- FAQ --}}
        <div class="help-section" id="faq">
            <div class="help-card">
                <h3><i class="fas fa-question-circle"></i> {{ trans('discordpings::help.faq') }}</h3>

                @for($i = 1; $i <= 10; $i++)
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
