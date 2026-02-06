<?php

namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MattFalahe\Seat\DiscordPings\Models\PingTemplate;
use MattFalahe\Seat\DiscordPings\Models\DiscordRole;
use MattFalahe\Seat\DiscordPings\Models\StagingLocation;

class TemplateController extends Controller
{
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
        $roles = DiscordRole::active()->get();
        $stagings = StagingLocation::active()->get();

        // Check if seat-fitting plugin is installed and get doctrines
        $doctrines = [];
        $hasFittingPlugin = false;

        // Try CryptaTech namespace first
        if (class_exists('CryptaTech\Seat\Fitting\Models\Doctrine')) {
            $hasFittingPlugin = true;
            try {
                $doctrines = \CryptaTech\Seat\Fitting\Models\Doctrine::all();
            } catch (\Exception $e) {
                Log::info('CryptaTech seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
            }
        }
        // Fall back to Denngarr namespace
        elseif (class_exists('Denngarr\Seat\Fitting\Models\Doctrine')) {
            $hasFittingPlugin = true;
            try {
                $doctrines = \Denngarr\Seat\Fitting\Models\Doctrine::all();
            } catch (\Exception $e) {
                Log::info('Denngarr seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
            }
        }

        return view('discordpings::templates.create', compact('roles', 'stagings', 'doctrines', 'hasFittingPlugin'));
    }

    /**
     * Store new template
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'template' => 'required|string|max:2000',
                'embed_type' => 'nullable|string|in:fleet,announcement,message',
                'fc_name' => 'nullable|string|max:100',
                'formup_location' => 'nullable|string|max:100',
                'pap_type' => 'nullable|string|in:Strategic,Peacetime,CTA',
                'comms' => 'nullable|string|max:200',
                'doctrine' => 'nullable|string|max:200',
                'mention_type' => 'nullable|string|in:none,everyone,here,role,custom',
                'mention_role_id' => 'nullable|integer|exists:discord_roles,id',
                'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);

            $fields = [];
            $fieldKeys = ['embed_type', 'fc_name', 'formup_location', 'pap_type', 'comms',
                         'doctrine', 'mention_type', 'mention_role_id', 'embed_color'];

            foreach ($fieldKeys as $key) {
                if (!empty($validated[$key])) {
                    $fields[$key] = $validated[$key];
                }
            }

            PingTemplate::create([
                'name' => $validated['name'],
                'template' => $validated['template'],
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

            $roles = DiscordRole::active()->get();
            $stagings = StagingLocation::active()->get();

            // Check if seat-fitting plugin is installed and get doctrines
            $doctrines = [];
            $hasFittingPlugin = false;

            // Try CryptaTech namespace first
            if (class_exists('CryptaTech\Seat\Fitting\Models\Doctrine')) {
                $hasFittingPlugin = true;
                try {
                    $doctrines = \CryptaTech\Seat\Fitting\Models\Doctrine::all();
                } catch (\Exception $e) {
                    Log::info('CryptaTech seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
                }
            }
            // Fall back to Denngarr namespace
            elseif (class_exists('Denngarr\Seat\Fitting\Models\Doctrine')) {
                $hasFittingPlugin = true;
                try {
                    $doctrines = \Denngarr\Seat\Fitting\Models\Doctrine::all();
                } catch (\Exception $e) {
                    Log::info('Denngarr seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
                }
            }

            return view('discordpings::templates.edit', compact('template', 'roles', 'stagings', 'doctrines', 'hasFittingPlugin'));
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

            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'template' => 'required|string|max:2000',
                'embed_type' => 'nullable|string|in:fleet,announcement,message',
                'fc_name' => 'nullable|string|max:100',
                'formup_location' => 'nullable|string|max:100',
                'pap_type' => 'nullable|string|in:Strategic,Peacetime,CTA',
                'comms' => 'nullable|string|max:200',
                'doctrine' => 'nullable|string|max:200',
                'mention_type' => 'nullable|string|in:none,everyone,here,role,custom',
                'mention_role_id' => 'nullable|integer|exists:discord_roles,id',
                'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);

            $fields = [];
            $fieldKeys = ['embed_type', 'fc_name', 'formup_location', 'pap_type', 'comms',
                         'doctrine', 'mention_type', 'mention_role_id', 'embed_color'];

            foreach ($fieldKeys as $key) {
                if (!empty($validated[$key])) {
                    $fields[$key] = $validated[$key];
                }
            }

            $template->update([
                'name' => $validated['name'],
                'template' => $validated['template'],
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
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'message' => 'required|string|max:2000',
            ]);

            // Build fields from all optional form data
            $fields = [];
            $fieldKeys = ['embed_type', 'fc_name', 'formup_location', 'pap_type', 'comms',
                         'doctrine', 'mention_type', 'mention_role_id', 'embed_color'];

            foreach ($fieldKeys as $key) {
                if ($request->has($key) && !empty($request->input($key))) {
                    $fields[$key] = $request->input($key);
                }
            }

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
}
