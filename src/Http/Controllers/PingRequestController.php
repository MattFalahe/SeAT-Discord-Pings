<?php

namespace DiscordPings\Http\Controllers;

use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Models\PingRequestPreset;
use DiscordPings\Services\DiscordUserResolver;
use DiscordPings\Services\PingRequests\ErrorText;
use DiscordPings\Services\PingRequests\LocationLookup;
use DiscordPings\Services\PingRequests\MemberAccount;
use DiscordPings\Services\PingRequests\PingRequestSender;
use DiscordPings\Services\PingRequests\Sections;
use DiscordPings\Services\PingRequests\ShipLookup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * The line member's side of Ping Requests: the buttons and their saved forms.
 */
class PingRequestController extends Controller
{
    /** Plenty for every alt someone flies, without letting the table grow forever. */
    private const MAX_PRESETS_PER_BUTTON = 20;

    private const MAX_ANSWER_LENGTH = 1000;

    public function index(PingRequestSender $sender)
    {
        $user = auth()->user();

        $buttons = PingRequestButton::active()
            ->visibleTo($user)
            ->forMembers()
            ->with('webhook')
            ->get()
            ->filter(fn ($button) => $button->webhook && $button->webhook->is_active)
            ->values();

        $presets = PingRequestPreset::where('user_id', $user->id)
            ->whereIn('button_id', $buttons->pluck('id'))
            ->orderBy('name')
            ->get()
            ->groupBy('button_id');

        $cards = $buttons->map(function ($button) use ($presets, $sender, $user) {
            $rows = $button->sectionRows();

            return [
                'id' => $button->id,
                'name' => $button->name,
                'emergency' => $button->isEmergency(),
                'pingNotice' => (string) $button->ping_notice,
                'lines' => Sections::lines($rows),
                'plain' => Sections::compose($rows, []),
                'freeText' => ! Sections::hasQuestions($rows),
                'canSave' => Sections::savedKeys($rows) !== [],
                'cooldownMinutes' => (int) $button->cooldown_minutes,
                'readyIn' => $sender->secondsUntilReady($button, $user),
                'presets' => ($presets[$button->id] ?? collect())->map(function ($preset) use ($rows) {
                    $answers = Sections::savedAnswers($rows, $preset->answers ?? []);

                    return [
                        'id' => $preset->id,
                        'name' => $preset->name,
                        'answers' => (object) $answers,
                        'preview' => Sections::compose($rows, $answers),
                    ];
                })->values()->all(),
            ];
        })->all();

        $recent = PingHistory::where('user_id', $user->id)
            ->memberRequests()
            ->latest()
            ->take(10)
            ->get();

        $identity = DiscordUserResolver::senderIdentity($user);
        $characters = MemberAccount::characterNames((int) $user->id);
        $active = $sender->openFor($user);

        return view('discordpings::requests.index', compact('cards', 'recent', 'identity', 'characters', 'active'));
    }

    public function systems(LocationLookup $lookup)
    {
        $systems = $lookup->systems();
        $response = response()->json($systems);

        // The map hardly ever changes, but an empty list means the read failed and that mustn't stick for a day.
        return $systems === [] ? $response : $response->header('Cache-Control', 'private, max-age=86400');
    }

    public function ships(ShipLookup $lookup)
    {
        $ships = $lookup->names();
        $response = response()->json($ships);

        // Kept a day like the systems, and for the same reason an empty list isn't.
        return $ships === [] ? $response : $response->header('Cache-Control', 'private, max-age=86400');
    }

    public function locations($id, LocationLookup $lookup)
    {
        return response()->json($lookup->locations((int) $id, MemberAccount::affiliations((int) auth()->id())));
    }

    /**
     * Every outcome comes back as a normal response with ok true or false, so
     * the page can always tell the member what happened to their press.
     */
    public function press(Request $request, $id, PingRequestSender $sender)
    {
        $validated = $request->validate([
            'preset_id' => 'nullable|integer',
            'answers' => 'nullable|array',
            'answers.*' => 'nullable|string|max:' . self::MAX_ANSWER_LENGTH,
        ]);

        $user = auth()->user();
        $button = PingRequestButton::active()->visibleTo($user)->with('webhook')->find($id);

        if (! $button) {
            return response()->json(['ok' => false, 'status' => 'not_found', 'message' => 'That button is no longer available. Reload the page.', 'retry_in' => null], 404);
        }

        $preset = empty($validated['preset_id']) ? null : $this->ownPreset($validated['preset_id'], $button->id);

        // What was typed in the confirm box wins for this send. The saved form itself stays as it was.
        $answers = $validated['answers'] ?? Sections::savedAnswers($button->sectionRows(), $preset->answers ?? []);

        try {
            return response()->json($sender->press($button, $answers, $user, $preset->name ?? null));
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] Ping request press failed: ' . ErrorText::safe($e->getMessage()), ['button_id' => $button->id]);

            return response()->json(['ok' => false, 'status' => 'failed', 'message' => 'Not delivered. Something went wrong on our side, so press again or tell someone directly.', 'retry_in' => null]);
        }
    }

    /** What a member adds to an emergency once it has gone out. The token says which message, and that it is theirs. */
    public function details(Request $request, $token, PingRequestSender $sender)
    {
        $validated = $request->validate([
            'answers' => 'nullable|array|max:50',
            'answers.*' => 'nullable|string|max:' . self::MAX_ANSWER_LENGTH,
        ]);

        try {
            return response()->json($sender->addDetails((string) $token, $validated['answers'] ?? [], auth()->user()));
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] Adding to a ping request failed: ' . ErrorText::safe($e->getMessage()));

            return response()->json(['ok' => false, 'status' => 'failed', 'message' => 'Not updated. Something went wrong on our side, so try again or tell someone directly.', 'retry_in' => null]);
        }
    }

    /** A member closing their emergency before its time runs out. Like the details, the token says which and that it is theirs. */
    public function close($token, PingRequestSender $sender)
    {
        try {
            return response()->json($sender->close((string) $token, auth()->user()));
        } catch (\Throwable $e) {
            Log::error('[DiscordPings] Closing a ping request failed: ' . ErrorText::safe($e->getMessage()));

            return response()->json(['ok' => false, 'status' => 'failed', 'message' => 'Not closed. Something went wrong on our side, so try again.', 'retry_in' => null]);
        }
    }

    public function storePreset(Request $request, $id)
    {
        $user = auth()->user();
        $button = PingRequestButton::active()->visibleTo($user)->findOrFail($id);
        $data = $this->validatedPreset($request, $button);

        $count = PingRequestPreset::where('user_id', $user->id)->where('button_id', $button->id)->count();
        if ($count >= self::MAX_PRESETS_PER_BUTTON) {
            return response()->json(['ok' => false, 'message' => 'You have the most saved forms this button allows. Delete one first.'], 422);
        }

        if ($this->nameTaken($user->id, $button->id, $data['name'])) {
            return response()->json(['ok' => false, 'message' => 'You already have a saved form with that name.'], 422);
        }

        PingRequestPreset::create($data + ['user_id' => $user->id, 'button_id' => $button->id]);

        return response()->json(['ok' => true]);
    }

    public function updatePreset(Request $request, $id)
    {
        $user = auth()->user();
        $preset = PingRequestPreset::where('user_id', $user->id)->findOrFail($id);
        $button = PingRequestButton::active()->visibleTo($user)->findOrFail($preset->button_id);
        $data = $this->validatedPreset($request, $button);

        if ($this->nameTaken($user->id, $button->id, $data['name'], $preset->id)) {
            return response()->json(['ok' => false, 'message' => 'You already have a saved form with that name.'], 422);
        }

        $preset->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroyPreset($id)
    {
        PingRequestPreset::where('user_id', auth()->id())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function ownPreset($presetId, $buttonId): ?PingRequestPreset
    {
        return PingRequestPreset::where('user_id', auth()->id())->where('button_id', $buttonId)->find($presetId);
    }

    private function nameTaken(int $userId, int $buttonId, string $name, ?int $exceptPresetId = null): bool
    {
        return PingRequestPreset::where('user_id', $userId)
            ->where('button_id', $buttonId)
            ->where('name', $name)
            ->when($exceptPresetId, fn ($query) => $query->where('id', '!=', $exceptPresetId))
            ->exists();
    }

    /**
     * Only answers to what the button actually asks get stored, and never one
     * that's asked after the press.
     *
     * @return array{name: string, answers: array<string, string>}
     */
    private function validatedPreset(Request $request, PingRequestButton $button): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64',
            'answers' => 'nullable|array',
            'answers.*' => 'nullable|string|max:' . self::MAX_ANSWER_LENGTH,
        ]);

        return [
            'name' => trim($validated['name']),
            'answers' => Sections::savedAnswers($button->sectionRows(), $validated['answers'] ?? []),
        ];
    }
}
