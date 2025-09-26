<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'MattFalahe\Seat\DiscordPings\Http\Controllers',
    'middleware' => ['web', 'auth', 'locale'],
    'prefix' => 'discord-pings',
], function () {
    
    // Main send ping page
    Route::get('/send', 'PingController@index')
        ->name('discordpings.send')
        ->middleware('can:discordpings.send');
    
    Route::post('/send', 'PingController@send')
        ->name('discordpings.send.post')
        ->middleware('can:discordpings.send');
    
    Route::post('/send-multiple', 'PingController@sendMultiple')
        ->name('discordpings.send.multiple')
        ->middleware('can:discordpings.send_multiple');
    
    // Discord Configuration (Webhooks, Roles, Channels)
    Route::get('/config', 'DiscordConfigController@index')
        ->name('discordpings.config')
        ->middleware('can:discordpings.manage_webhooks');
    
    // Discord Roles
    Route::post('/config/roles', 'DiscordConfigController@storeRole')
        ->name('discordpings.config.roles.store')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::delete('/config/roles/{id}', 'DiscordConfigController@destroyRole')
        ->name('discordpings.config.roles.destroy')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::post('/config/roles/{id}/toggle', 'DiscordConfigController@toggleRole')
        ->name('discordpings.config.roles.toggle')
        ->middleware('can:discordpings.manage_webhooks');
    
    // Discord Channels
    Route::post('/config/channels', 'DiscordConfigController@storeChannel')
        ->name('discordpings.config.channels.store')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::delete('/config/channels/{id}', 'DiscordConfigController@destroyChannel')
        ->name('discordpings.config.channels.destroy')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::post('/config/channels/{id}/toggle', 'DiscordConfigController@toggleChannel')
        ->name('discordpings.config.channels.toggle')
        ->middleware('can:discordpings.manage_webhooks');
    
    // Webhook management (keeping existing routes)
    Route::get('/webhooks', 'WebhookController@index')
        ->name('discordpings.webhooks')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::get('/webhooks/create', 'WebhookController@create')
        ->name('discordpings.webhooks.create')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::post('/webhooks', 'WebhookController@store')
        ->name('discordpings.webhooks.store')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::get('/webhooks/{id}/edit', 'WebhookController@edit')
        ->name('discordpings.webhooks.edit')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::put('/webhooks/{id}', 'WebhookController@update')
        ->name('discordpings.webhooks.update')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::delete('/webhooks/{id}', 'WebhookController@destroy')
        ->name('discordpings.webhooks.destroy')
        ->middleware('can:discordpings.manage_webhooks');
    
    Route::post('/webhooks/{id}/test', 'WebhookController@test')
        ->name('discordpings.webhooks.test')
        ->middleware('can:discordpings.manage_webhooks');
    
    // History
    Route::get('/history', 'HistoryController@index')
        ->name('discordpings.history')
        ->middleware('can:discordpings.view_history');
    
    Route::get('/history/{id}', 'HistoryController@show')
        ->name('discordpings.history.show')
        ->middleware('can:discordpings.view_history');
    
    Route::post('/history/{id}/resend', 'HistoryController@resend')
        ->name('discordpings.history.resend')
        ->middleware('can:discordpings.send');
    
    // Scheduled pings
    Route::get('/scheduled', 'ScheduledController@index')
        ->name('discordpings.scheduled')
        ->middleware('can:discordpings.manage_scheduled');
    
    Route::get('/scheduled/create', 'ScheduledController@create')
        ->name('discordpings.scheduled.create')
        ->middleware('can:discordpings.manage_scheduled');
    
    Route::post('/scheduled', 'ScheduledController@store')
        ->name('discordpings.scheduled.store')
        ->middleware('can:discordpings.manage_scheduled');
    
    Route::delete('/scheduled/{id}', 'ScheduledController@destroy')
        ->name('discordpings.scheduled.destroy')
        ->middleware('can:discordpings.manage_scheduled');
});
