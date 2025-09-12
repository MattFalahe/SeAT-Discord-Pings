<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'YourName\Seat\DiscordBroadcast\Http\Controllers',
    'prefix' => 'discord-broadcast',
    'middleware' => ['web', 'auth', 'locale'],
], function () {
    
    // Broadcast routes
    Route::get('/', [
        'as' => 'discord.broadcast.view',
        'uses' => 'DiscordBroadcastController@showBroadcastForm',
        'middleware' => 'can:discord.broadcast.send'
    ]);
    
    Route::post('/send', [
        'as' => 'discord.broadcast.send',
        'uses' => 'DiscordBroadcastController@sendBroadcast',
        'middleware' => 'can:discord.broadcast.send'
    ]);
    
    Route::post('/send-multiple', [
        'as' => 'discord.broadcast.send.multiple',
        'uses' => 'DiscordBroadcastController@sendMultipleBroadcast',
        'middleware' => 'can:discord.broadcast.send.multiple'
    ]);
    
    // Webhook management
    Route::group(['prefix' => 'webhooks', 'middleware' => 'can:discord.webhooks.manage'], function () {
        Route::get('/', [
            'as' => 'discord.webhooks.index',
            'uses' => 'DiscordWebhookController@index'
        ]);
        
        Route::get('/create', [
            'as' => 'discord.webhooks.create',
            'uses' => 'DiscordWebhookController@create'
        ]);
        
        Route::post('/store', [
            'as' => 'discord.webhooks.store',
            'uses' => 'DiscordWebhookController@store'
        ]);
        
        Route::get('/{id}/edit', [
            'as' => 'discord.webhooks.edit',
            'uses' => 'DiscordWebhookController@edit'
        ]);
        
        Route::put('/{id}', [
            'as' => 'discord.webhooks.update',
            'uses' => 'DiscordWebhookController@update'
        ]);
        
        Route::delete('/{id}', [
            'as' => 'discord.webhooks.destroy',
            'uses' => 'DiscordWebhookController@destroy'
        ]);
        
        Route::post('/{id}/test', [
            'as' => 'discord.webhooks.test',
            'uses' => 'DiscordWebhookController@test'
        ]);
    });
    
    // History routes
    Route::group(['prefix' => 'history', 'middleware' => 'can:discord.history.view'], function () {
        Route::get('/', [
            'as' => 'discord.history.index',
            'uses' => 'BroadcastHistoryController@index'
        ]);
        
        Route::get('/{id}', [
            'as' => 'discord.history.show',
            'uses' => 'BroadcastHistoryController@show'
        ]);
        
        Route::post('/{id}/resend', [
            'as' => 'discord.history.resend',
            'uses' => 'BroadcastHistoryController@resend',
            'middleware' => 'can:discord.broadcast.send'
        ]);
    });
    
    // Scheduled broadcasts
    Route::group(['prefix' => 'scheduled', 'middleware' => 'can:discord.scheduled.view'], function () {
        Route::get('/', [
            'as' => 'discord.scheduled.index',
            'uses' => 'ScheduledBroadcastController@index'
        ]);
        
        Route::get('/create', [
            'as' => 'discord.scheduled.create',
            'uses' => 'ScheduledBroadcastController@create',
            'middleware' => 'can:discord.scheduled.create'
        ]);
        
        Route::post('/store', [
            'as' => 'discord.scheduled.store',
            'uses' => 'ScheduledBroadcastController@store',
            'middleware' => 'can:discord.scheduled.create'
        ]);
        
        Route::delete('/{id}', [
            'as' => 'discord.scheduled.destroy',
            'uses' => 'ScheduledBroadcastController@destroy',
            'middleware' => 'can:discord.scheduled.delete'
        ]);
    });
});
