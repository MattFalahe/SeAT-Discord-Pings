@extends('web::layouts.grids.12')

@section('title', 'Schedule Discord Ping')
@section('page_header', 'Schedule Discord Ping')

@section('full')
    <form method="POST" action="{{ route('discordpings.scheduled.store') }}">
        @csrf
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Schedule Settings</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Scheduled Date/Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" required
                                   min="{{ now()->format('Y-m-d\TH:i') }}"
                                   value="{{ old('scheduled_at', now()->addHour()->format('Y-m-d\TH:i')) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Webhook <span class="text-danger">*</span></label>
                            <select name="webhook_id" class="form-control" required>
                                <option value="">Select Webhook...</option>
                                @foreach($webhooks as $webhook)
                                    <option value="{{ $webhook->id }}">{{ $webhook->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Repeat Interval</label>
                            <select name="repeat_interval" class="form-control">
                                <option value="">One-time (No Repeat)</option>
                                <option value="hourly">Hourly</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Repeat Until</label>
                            <input type="datetime-local" name="repeat_until" class="form-control"
                                   min="{{ now()->addDay()->format('Y-m-d\TH:i') }}">
                            <small class="form-text text-muted">Leave empty for indefinite repeat</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required 
                              maxlength="2000">{{ old('message') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Optional Fields</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>FC Name</label>
                            <input type="text" name="fc_name" class="form-control" 
                                   value="{{ old('fc_name') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Formup Location</label>
                            <input type="text" name="formup_location" class="form-control" 
                                   value="{{ old('formup_location') }}">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>PAP Type</label>
                            <select name="pap_type" class="form-control">
                                <option value="">None</option>
                                <option value="Strategic">Strategic</option>
                                <option value="Peacetime">Peacetime</option>
                                <option value="CTA">CTA</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Comms</label>
                            <input type="text" name="comms" class="form-control" 
                                   value="{{ old('comms') }}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Doctrine</label>
                    <input type="text" name="doctrine" class="form-control" 
                           value="{{ old('doctrine') }}">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-clock"></i> Schedule Ping
                </button>
                <a href="{{ route('discordpings.scheduled') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
@stop
