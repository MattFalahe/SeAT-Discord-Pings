<?php

namespace DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DiscordPings\Models\PingTemplate;
use DiscordPings\Models\DiscordChannel;
use DiscordPings\Models\DiscordRole;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\StagingLocation;
use DiscordPings\Models\PapType;
use DiscordPings\Helpers\DiscordHelper;
use DiscordPings\Services\BroadcastForm;

class TemplateController extends Controller
{
    /**
     * Every box of the Send form, so a template brings back the whole form it
     * was saved from. Older pages sent the role as mention_role_id.
     */
    private const FIELD_RULES = [
        'webhook_id' => 'nullable|integer|exists:discord_webhooks,id',
        'webhook_ids' => 'nullable|array',
        'webhook_ids.*' => 'integer|exists:discord_webhooks,id',
        'embed_type' => 'nullable|string|in:fleet,announcement,message,prepping',
        'fc_name' => 'nullable|string|max:100',
        'formup_location' => 'nullable|string|max:100',
        'pap_type' => 'nullable|string|max:100',
        'comms' => 'nullable|string|max:200',
        'doctrine' => 'nullable|string|max:200',
        'doctrine_id' => 'nullable|integer',
        'mention_type' => 'nullable|string|in:none,everyone,here,role,custom',
        'role_mention' => 'nullable|integer|exists:discord_roles,id',
        'mention_role_id' => 'nullable|integer|exists:discord_roles,id',
        'custom_mention' => 'nullable|string|max:100',
        'channel_link' => 'nullable|integer|exists:discord_channels,id',
        'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
    ];

    /**
     * Display template list
     */
    public function index()
    {
        try {
            $templates = PingTemplate::forUser(auth()->id())->get();

            return view('discordpings::templates.index', compact('templates'));
        } catch (\Exception $e) {
            Log::error('Discord Pings templates index error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load templates. Please check logs.');
        }
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('discordpings::templates.create', $this->formData());
    }

    /**
     * Store new template
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate(array_merge([
                'name' => 'required|string|max:100',
                'message' => 'required|string|max:2000',
            ], self::FIELD_RULES));

            $fields = self::fieldsFrom($validated);

            PingTemplate::create([
                'name' => $validated['name'],
                'template' => $validated['message'],
                'fields' => !empty($fields) ? $fields : null,
                'created_by' => auth()->id(),
                'is_global' => false,
            ]);

            return redirect()->route('discordpings.templates')
                ->with('success', 'Template created successfully!');
        } catch (\Exception $e) {
            Log::error('Discord Pings template store error: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', 'Failed to create template: ' . $e->getMessage());
        }
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        try {
            $template = PingTemplate::findOrFail($id);

            // Check ownership (unless admin with global perm)
            if ($template->created_by != auth()->id() &&
                !auth()->user()->can('discordpings.manage_global_templates')) {
                abort(403, 'Unauthorized');
            }

            return view('discordpings::templates.edit', array_merge($this->formData(), compact('template')));
        } catch (\Exception $e) {
            Log::error('Discord Pings template edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load template.');
        }
    }

    /**
     * Update template
     */
    public function update(Request $request, $id)
    {
        try {
            $template = PingTemplate::findOrFail($id);

            if ($template->created_by != auth()->id() &&
                !auth()->user()->can('discordpings.manage_global_templates')) {
                abort(403, 'Unauthorized');
            }

            $validated = $request->validate(array_merge([
                'name' => 'required|string|max:100',
                'message' => 'required|string|max:2000',
            ], self::FIELD_RULES));

            $fields = self::fieldsFrom($validated);

            $template->update([
                'name' => $validated['name'],
                'template' => $validated['message'],
                'fields' => !empty($fields) ? $fields : null,
            ]);

            return redirect()->route('discordpings.templates')
                ->with('success', 'Template updated successfully!');
        } catch (\Exception $e) {
            Log::error('Discord Pings template update error: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', 'Failed to update template: ' . $e->getMessage());
        }
    }

    /**
     * Delete template
     */
    public function destroy($id)
    {
        try {
            $template = PingTemplate::findOrFail($id);

            if ($template->created_by != auth()->id() &&
                !auth()->user()->can('discordpings.manage_global_templates')) {
                abort(403, 'Unauthorized');
            }

            $template->delete();

            return redirect()->back()->with('success', 'Template deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Discord Pings template delete error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete template.');
        }
    }

    /**
     * Toggle global status (admin only)
     */
    public function toggleGlobal($id)
    {
        try {
            $template = PingTemplate::findOrFail($id);
            $template->update(['is_global' => !$template->is_global]);

            return response()->json([
                'success' => true,
                'is_global' => $template->is_global,
            ]);
        } catch (\Exception $e) {
            Log::error('Discord Pings template toggle global error: ' . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Quick save template from send page (AJAX)
     */
    public function quickSave(Request $request)
    {
        try {
            $validated = $request->validate(array_merge([
                'name' => 'required|string|max:100',
                'message' => 'required|string|max:2000',
            ], self::FIELD_RULES));

            $fields = self::fieldsFrom($validated);

            $template = PingTemplate::create([
                'name' => $validated['name'],
                'template' => $validated['message'],
                'fields' => !empty($fields) ? $fields : null,
                'created_by' => auth()->id(),
                'is_global' => false,
            ]);

            return response()->json([
                'success' => true,
                'template' => $template,
            ]);
        } catch (\Exception $e) {
            Log::error('Discord Pings template quick save error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * What a template keeps of a form: whatever was filled in. Webhooks become
     * one id or a list, by how many were picked, and a doctrine picked from
     * SeAT Fitting keeps its name next to its id, so the template still loads
     * it by name if the fitting is removed later.
     */
    public static function fieldsFrom(array $validated): array
    {
        $fields = [];

        $ids = array_values(array_unique(array_map('intval', $validated['webhook_ids'] ?? [])));
        if (count($ids) > 1) {
            $fields['webhook_ids'] = $ids;
        } elseif (count($ids) === 1) {
            $fields['webhook_id'] = $ids[0];
        } elseif (! empty($validated['webhook_id'])) {
            $fields['webhook_id'] = (int) $validated['webhook_id'];
        }

        foreach (['embed_type', 'mention_type', 'custom_mention', 'embed_color', 'fc_name', 'formup_location', 'pap_type', 'comms'] as $key) {
            $value = trim((string) ($validated[$key] ?? ''));

            if ($value !== '') {
                $fields[$key] = $value;
            }
        }

        // Templates have always kept the role under this name.
        $role = $validated['role_mention'] ?? $validated['mention_role_id'] ?? null;
        if (! empty($role)) {
            $fields['mention_role_id'] = (int) $role;
        }

        if (! empty($validated['channel_link'])) {
            $fields['channel_link'] = (int) $validated['channel_link'];
        }

        $doctrine = trim((string) ($validated['doctrine'] ?? ''));
        if (! empty($validated['doctrine_id'])) {
            $fitting = DiscordHelper::findFittingDoctrine((int) $validated['doctrine_id']);

            if ($fitting) {
                $fields['doctrine_id'] = (int) $fitting->id;
                $doctrine = (string) $fitting->name;
            }
        }

        if ($doctrine !== '') {
            $fields['doctrine'] = $doctrine;
        }

        return $fields;
    }

    /** The same lists the Send form offers. */
    private function formData(): array
    {
        return [
            'webhooks' => DiscordWebhook::active()->get(),
            'roles' => DiscordRole::active()->get(),
            'channels' => DiscordChannel::active()->get(),
            'stagings' => StagingLocation::active()->get(),
            'papTypes' => PapType::active()->ordered()->get(),
            'hasFittingPlugin' => (bool) DiscordHelper::detectFittingDoctrineClass(),
            // listFittingDoctrines() returns a plain array, and the form counts it.
            'doctrines' => collect(DiscordHelper::listFittingDoctrines()),
            'suggestions' => BroadcastForm::suggestions(auth()->user()),
        ];
    }
}
