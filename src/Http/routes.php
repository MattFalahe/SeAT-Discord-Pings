<?php

Route::group([
    'namespace' => 'MattFalahe\Seat\DiscordPings\Http\Controllers',
    'prefix' => 'discord-pings',
    'middleware' => ['web', 'auth', 'locale'],
], function () {
    
    // Main ping routes
    Route::get('/', [
        'as' => 'discord.pings.view',
        'uses' => 'DiscordPingController@showPingForm',
        'middleware' => 'can:discord.pings.send'
    ]);
    
    Route::post('/send', [
        'as' => 'discord.pings.send',
        'uses' => 'DiscordPingController@sendPing',
        'middleware' => 'can:discord.pings.send'
    ]);
    
    Route::post('/send-multiple', [
        'as' => 'discord.pings.send.multiple',
        'uses' => 'DiscordPingController@sendMultiplePing',
        'middleware' => 'can:discord.pings.send.multiple'
    ]);
    
    // Webhook management
    Route::group(['prefix' => 'webhooks', 'middleware' => 'can:discord.pings.webhooks.manage'], function () {
        Route::get('/', [
            'as' => 'discord.pings.webhooks.index',
            'uses' => 'WebhookController@index'
        ]);
        
        Route::get('/create', [
            'as' => 'discord.pings.webhooks.create',
            'uses' => 'WebhookController@create'
        ]);
        
        Route::post('/store', [
            'as' => 'discord.pings.webhooks.store',
            'uses' => 'WebhookController@store'
        ]);
        
        Route::get('/{id}/edit', [
            'as' => 'discord.pings.webhooks.edit',
            'uses' => 'WebhookController@edit'
        ]);
        
        Route::put('/{id}', [
            'as' => 'discord.pings.webhooks.update',
            'uses' => 'WebhookController@update'
        ]);
        
        Route::delete('/{id}', [
            'as' => 'discord.pings.webhooks.destroy',
            'uses' => 'WebhookController@destroy'
        ]);
        
        Route::post('/{id}/test', [
            'as' => 'discord.pings.webhooks.test',
            'uses' => 'WebhookController@test'
        ]);
    });
    
    // History routes
    Route::group(['prefix' => 'history', 'middleware' => 'can:discord.pings.history.view'], function () {
        Route::get('/', [
            'as' => 'discord.pings.history.index',
            'uses' => 'PingHistoryController@index'
        ]);
        
        Route::get('/{id}', [
            'as' => 'discord.pings.history.show',
            'uses' => 'PingHistoryController@show'
        ]);
        
        Route::post('/{id}/resend', [
            'as' => 'discord.pings.history.resend',
            'uses' => 'PingHistoryController@resend',
            'middleware' => 'can:discord.pings.send'
        ]);
    });
    
    // Scheduled pings
    Route::group(['prefix' => 'scheduled', 'middleware' => 'can:discord.pings.scheduled.view'], function () {
        Route::get('/', [
            'as' => 'discord.pings.scheduled.index',
            'uses' => 'ScheduledPingController@index'
        ]);
        
        Route::get('/create', [
            'as' => 'discord.pings.scheduled.create',
            'uses' => 'ScheduledPingController@create',
            'middleware' => 'can:discord.pings.scheduled.create'
        ]);
        
        Route::post('/store', [
            'as' => 'discord.pings.scheduled.store',
            'uses' => 'ScheduledPingController@store',
            'middleware' => 'can:discord.pings.scheduled.create'
        ]);
        
        Route::delete('/{id}', [
            'as' => 'discord.pings.scheduled.destroy',
            'uses' => 'ScheduledPingController@destroy',
            'middleware' => 'can:discord.pings.scheduled.delete'
        ]);
    });
});
