<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingest table for cross-plugin tactical events received via Manager Core's
 * EventBus (Structure Manager's `structure_manager.timer.*` family today).
 *
 * One row = one external timer/op. Stays empty and harmless when Manager Core
 * is not installed — the EventBus subscription is class_exists-guarded, so
 * nothing ever writes here without MC + a publishing plugin present.
 */
return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('discord_tactical_events')) {
            return;
        }

        Schema::create('discord_tactical_events', function (Blueprint $table) {
            $table->id();

            // External source identity. (source_plugin, external_timer_id) is
            // the stable key: the EventBus event_id changes on every publish,
            // so the publisher's own timer id is what we correlate on.
            $table->string('source_plugin')->default('structure-manager');
            $table->unsignedBigInteger('external_timer_id');
            $table->string('last_event_id')->nullable();

            // Classification
            $table->string('event_type')->nullable();     // publisher's type, e.g. reinforce_hull, fuel_warning
            $table->string('category_group')->nullable();  // fuel | tactical | lifecycle
            $table->string('severity')->default('info');   // info | warning | critical
            $table->string('title');                       // computed display title

            // When the timer's underlying event happens — the calendar date
            $table->datetime('eve_time')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_elapsed')->default(false);

            // Structure context
            $table->unsignedBigInteger('structure_id')->nullable();
            $table->string('structure_name')->nullable();
            $table->string('structure_type')->nullable();
            $table->unsignedBigInteger('system_id')->nullable();
            $table->string('system_name')->nullable();
            $table->double('system_security')->nullable();

            // Parties
            $table->string('owner_corporation_name')->nullable();
            $table->string('attacker_corporation_name')->nullable();

            // Visibility gate — honored by TacticalEvent::scopeVisibleTo()
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();

            // Admin / linkage
            $table->string('url', 512)->nullable();
            $table->text('notes')->nullable();

            // Lifecycle: active (on calendar) | dismissed (hidden) | elapsed (dimmed)
            $table->string('status')->default('active');

            // Full raw envelope kept verbatim for forward-compatibility
            $table->json('payload')->nullable();

            // Pre-timer ping dedup latches
            $table->timestamp('pinged_24h_at')->nullable();
            $table->timestamp('pinged_1h_at')->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['source_plugin', 'external_timer_id'], 'dte_source_timer_unique');
            $table->index('eve_time');
            $table->index('status');
            $table->index('corporation_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_tactical_events');
    }
};
