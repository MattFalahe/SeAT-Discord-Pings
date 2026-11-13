{{--
    The optional boxes of a broadcast form, the same on every page that has
    one. data-suggest marks the boxes broadcast-form.js offers suggestions for.
--}}
@php
    $defaultStaging = collect($stagings ?? [])->firstWhere('is_default', true);
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="fcName"><i class="fas fa-user"></i> FC Name</label>
            <input type="text" name="fc_name" id="fcName" class="form-control" maxlength="100"
                   autocomplete="off" data-suggest="character">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="formupLocation"><i class="fas fa-map-marker-alt"></i> Formup Location</label>
            <div class="input-group">
                <input type="text" name="formup_location" id="formupLocation" class="form-control" maxlength="100"
                       autocomplete="off" data-suggest="location"
                       placeholder="{{ $defaultStaging ? $defaultStaging->getFullLocationString() . ' (default)' : 'e.g., Jita 4-4' }}">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Stagings">
                        <i class="fas fa-list"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <h6 class="dropdown-header">Quick Select Staging</h6>
                        @forelse ($stagings ?? [] as $staging)
                            <a class="dropdown-item staging-select" href="#"
                               data-location="{{ $staging->getFullLocationString() }}">
                                {{ $staging->name }}
                                @if ($staging->is_default)
                                    <span class="badge badge-primary ml-1">Default</span>
                                @endif
                                <br>
                                <small class="text-muted">{{ $staging->getFullLocationString() }}</small>
                            </a>
                        @empty
                            <span class="dropdown-item text-muted">No staging locations configured</span>
                        @endforelse
                        @can('discordpings.manage_webhooks')
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('discordpings.config') }}#stagings-tab">
                                <i class="fas fa-cog"></i> Configure Stagings
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="papType"><i class="fas fa-tag"></i> PAP Type</label>
            <select name="pap_type" id="papType" class="form-control">
                <option value="">None</option>
                @foreach ($papTypes ?? [] as $pap)
                    <option value="{{ $pap->name }}">{{ $pap->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="commsField"><i class="fas fa-headset"></i> Comms</label>
            <input type="text" name="comms" class="form-control" id="commsField" maxlength="200"
                   autocomplete="off" data-suggest="comms"
                   placeholder="e.g., Mumble Channel 3 or TeamSpeak Room 5">
            <small class="form-text text-muted">
                <i class="fas fa-info-circle"></i> For voice comms or custom channels.
                <strong class="text-warning">Leave empty if Discord Channel is selected above</strong>.
            </small>
        </div>
    </div>
</div>

<div class="form-group">
    <label><i class="fas fa-rocket"></i> Doctrine</label>
    @if (($hasFittingPlugin ?? false) && collect($doctrines ?? [])->count() > 0)
        <select name="doctrine_id" id="doctrineSelect" class="form-control" aria-label="Doctrine">
            <option value="">Select Doctrine...</option>
            @foreach ($doctrines as $doctrine)
                <option value="{{ $doctrine->id }}">{{ $doctrine->name }}</option>
            @endforeach
            <option value="custom">-- Manual Entry --</option>
        </select>
        <input type="text" name="doctrine" id="doctrineManual" class="form-control mt-2" maxlength="200"
               autocomplete="off" data-suggest="doctrine" aria-label="Doctrine, typed"
               placeholder="Or type doctrine manually" style="display: none;">
        <small class="form-text text-muted">
            Select a doctrine from seat-fitting or choose "Manual Entry" to type your own
        </small>
    @else
        <input type="text" name="doctrine" class="form-control" maxlength="200" aria-label="Doctrine"
               autocomplete="off" data-suggest="doctrine"
               placeholder="Void Rays (MWD kikis) (Boosts > Logi > Kikis > Slasher/Hyena/Keres)">
    @endif
</div>
