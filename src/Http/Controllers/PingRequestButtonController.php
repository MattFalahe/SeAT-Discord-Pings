<?php

namespace DiscordPings\Http\Controllers;

use DiscordPings\Models\DiscordRole;
use DiscordPings\Models\PingRequestButton;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
            'starter_text' => "Rorqual Tackled\nRorqual Name:\nSystem: Location In System (Anomaly or Moon):\nName of Cyno (On perch, 300km away):",
        ],
        [
            'name' => 'I want a mining fleet',
            'type' => PingRequestButton::TYPE_REQUEST,
            'cooldown_minutes' => 60,
            'starter_text' => "Mining fleet wanted\nSystem:\nOre or ice:\nWhen:",
        ],
        [
            'name' => 'I want a PvP fleet',
            'type' => PingRequestButton::TYPE_REQUEST,
            'cooldown_minutes' => 60,
            'starter_text' => "PvP fleet wanted\nForming from:\nWhen:\nDoctrine or ship:",
        ],
        [
            'name' => "Help, I'm bored",
            'type' => PingRequestButton::TYPE_REQUEST,
            'cooldown_minutes' => 120,
            'starter_text' => 'Looking for something to do',
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

    public function loadExamples()
    {
        $added = 0;

        foreach (self::EXAMPLES as $example) {
            $button = PingRequestButton::firstOrCreate(['name' => $example['name']], $example + ['is_active' => false]);
            $added += $button->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->back()->with('success', $added . ' example buttons added, switched off. Give each one a webhook and switch it on.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:' . PingRequestButton::TYPE_EMERGENCY . ',' . PingRequestButton::TYPE_REQUEST,
            'webhook_id' => 'nullable|integer|exists:discord_webhooks,id',
            'mention_role' => 'nullable|string|max:64',
            'starter_text' => 'nullable|string|max:2000',
            'cooldown_minutes' => 'nullable|integer|min:0|max:1440',
            'corporation_id' => 'nullable|integer',
            'role_id' => 'nullable|integer',
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

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'webhook_id' => $data['webhook_id'] ?? null,
            'mention_role_id' => $roleId,
            'starter_text' => $data['starter_text'] ?? null,
            'cooldown_minutes' => (int) ($data['cooldown_minutes'] ?? 0),
            'corporation_id' => $data['corporation_id'] ?? null,
            'role_id' => $data['role_id'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $active,
        ];
    }
}
