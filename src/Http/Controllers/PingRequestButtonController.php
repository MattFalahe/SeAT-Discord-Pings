<?php

namespace DiscordPings\Http\Controllers;

use DiscordPings\Models\DiscordRole;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\PingRequests\Sections;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Settings > Ping Requests: what the buttons are, where they post and who sees them.
 */
class PingRequestButtonController extends Controller
{
    /** They arrive switched off with no webhook, so loading them can't start posting anywhere. */
    private const EXAMPLES = [
        [
            'name' => "Help, I'm tackled",
            'type' => PingRequestButton::TYPE_EMERGENCY,
            'cooldown_minutes' => 0,
            'sections' => Sections::STARTING_POINTS['Tackled'],
        ],
        [
            'name' => 'I want a mining fleet',
            'type' => PingRequestButton::TYPE_REQUEST,
            'cooldown_minutes' => 60,
            'sections' => Sections::STARTING_POINTS['Mining fleet'],
        ],
        [
            'name' => 'I want a PvP fleet',
            'type' => PingRequestButton::TYPE_REQUEST,
            'cooldown_minutes' => 60,
            'sections' => Sections::STARTING_POINTS['PvP fleet'],
        ],
        [
            'name' => "Help, I'm bored",
            'type' => PingRequestButton::TYPE_REQUEST,
            'cooldown_minutes' => 120,
            'sections' => [['label' => 'Looking for something to do', 'box' => Sections::FIXED, 'live' => false]],
        ],
    ];

    public function store(Request $request)
    {
        PingRequestButton::create($this->validated($request));

        return redirect()->back()->with('success', 'Button added.');
    }

    public function update(Request $request, $id)
    {
        PingRequestButton::findOrFail($id)->update($this->validated($request));

        return redirect()->back()->with('success', 'Button saved.');
    }

    public function toggle($id)
    {
        $button = PingRequestButton::with('webhook')->findOrFail($id);

        if (! $button->is_active && ! $button->webhook) {
            return response()->json(['success' => false, 'message' => 'Pick a webhook for this button first.'], 422);
        }

        $button->update(['is_active' => ! $button->is_active]);

        return response()->json(['success' => true, 'is_active' => $button->is_active]);
    }

    public function destroy($id)
    {
        PingRequestButton::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Button deleted, along with the forms members had saved for it.');
    }

    /**
     * The ticked buttons, or every one. The saved forms go with them through
     * the foreign key, the same as when one is deleted on its own.
     */
    public function destroyMany(Request $request)
    {
        $data = $request->validate([
            'all' => 'sometimes|boolean',
            'ids' => 'sometimes|array',
            'ids.*' => 'integer',
        ]);

        if ($request->boolean('all')) {
            $buttons = PingRequestButton::all();
        } elseif (! empty($data['ids'])) {
            $buttons = PingRequestButton::whereIn('id', $data['ids'])->get();
        } else {
            return redirect()->back()->with('error', 'Nothing deleted. Tick the buttons you want to delete first.');
        }

        if ($buttons->isEmpty()) {
            return redirect()->back()->with('error', 'Nothing deleted. Those buttons were already gone.');
        }

        DB::transaction(function () use ($buttons) {
            $buttons->each->delete();
        });

        return redirect()->back()->with('success', $buttons->count() === 1
            ? 'Button deleted, along with the forms members had saved for it.'
            : $buttons->count() . ' buttons deleted, along with the forms members had saved for them.');
    }

    public function loadExamples()
    {
        $added = 0;

        foreach (self::EXAMPLES as $example) {
            $button = PingRequestButton::firstOrCreate(['name' => $example['name']], $example + ['is_active' => false]);
            $added += $button->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->back()->with('success', $added . ' example buttons added, switched off. Give each one a webhook and switch it on.');
    }

    /** Rows for a format pasted into the form, so the guessing lives in one place. */
    public function readFormat(Request $request)
    {
        $data = $request->validate(['format' => 'nullable|string|max:20000']);

        return response()->json(Sections::fromFormat($data['format'] ?? ''));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:' . PingRequestButton::TYPE_EMERGENCY . ',' . PingRequestButton::TYPE_REQUEST,
            'webhook_id' => 'nullable|integer|exists:discord_webhooks,id',
            'mention_role' => 'nullable|string|max:64',
            'ping_notice' => 'nullable|string|max:100',
            // Loose on purpose: clean() trims these to size, so a long label is cut rather than losing the whole form.
            'sections' => 'nullable|array|max:50',
            'sections.*.label' => 'nullable|string|max:1000',
            'sections.*.box' => 'nullable|string|max:20',
            'sections.*.live' => 'nullable|boolean',
            'cooldown_minutes' => 'nullable|integer|min:0|max:1440',
            'corporation_id' => 'nullable|integer',
            'alliance_id' => 'nullable|integer',
            'audience_permissions' => 'nullable|array',
            'audience_permissions.*' => ['string', Rule::in(array_keys(PingRequestButton::audienceOptions()))],
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $mention = trim((string) ($data['mention_role'] ?? ''));
        $roleId = $mention === '' ? null : DiscordRole::parseRoleId($mention);

        if ($mention !== '' && ($roleId === null || strlen($roleId) > 25)) {
            throw ValidationException::withMessages(['mention_role' => 'Use a role ID, or a mention like <@&123456789>.']);
        }

        $active = $request->boolean('is_active');

        if ($active && empty($data['webhook_id'])) {
            throw ValidationException::withMessages(['webhook_id' => 'Pick a webhook before switching a button on.']);
        }

        $notice = trim((string) ($data['ping_notice'] ?? ''));

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'webhook_id' => $data['webhook_id'] ?? null,
            'mention_role_id' => $roleId,
            'ping_notice' => $notice === '' ? null : $notice,
            'sections' => Sections::clean($data['sections'] ?? []) ?: null,
            // Saved as rows from now on, so the old text can't come back as a second version.
            'starter_text' => null,
            'cooldown_minutes' => (int) ($data['cooldown_minutes'] ?? 0),
            'corporation_id' => $data['corporation_id'] ?? null,
            'alliance_id' => $data['alliance_id'] ?? null,
            'audience_permissions' => array_values(array_unique($data['audience_permissions'] ?? [])) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $active,
        ];
    }
}
