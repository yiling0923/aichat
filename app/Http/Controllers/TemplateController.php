<?php

namespace App\Http\Controllers;

use App\Channels\Templates;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\MessageTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * WhatsApp template manager (owner or manager) and sending a template from the inbox (everyone).
 */
class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.templates', [
            'business' => $request->user()->business,
            'active' => 'templates',
            'channels' => Channel::query()->where('type', 'whatsapp')->get(),
            'templates' => MessageTemplate::query()->with('channel')->orderBy('name')->get(),
            'usage' => $this->usage(),
        ]);
    }

    /** Where each template name is used, so owners know what depends on it. @return array<string, list<string>> */
    private function usage(): array
    {
        $use = [];

        foreach (Channel::query()->where('type', 'whatsapp')->get() as $channel) {
            if ($name = ($channel->settings['reminder_template'] ?? null)) {
                $use[$name][] = 'Booking reminders ('.$channel->name.')';
            }
        }

        foreach (Automation::query()->whereNotNull('template_name')->where('template_name', '!=', '')->get() as $a) {
            $use[$a->template_name][] = (\App\Marketing\AutomationDefaults::all()[$a->key]['title'] ?? $a->key).($a->enabled ? '' : ' (off)');
        }

        return $use;
    }

    public function store(Request $request, Templates $templates): RedirectResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', Rule::in(['en', 'en_US', 'zh_CN', 'zh_HK', 'zh_TW', 'ms'])],
            'category' => ['required', Rule::in(['UTILITY', 'MARKETING'])],
            'body' => ['required', 'string', 'max:1024'],
        ], ['name.regex' => 'The name may only use lower case letters, numbers and underscores.']);

        $channel = Channel::query()->where('type', 'whatsapp')->findOrFail($data['channel_id']);

        try {
            $templates->create($channel, $data['name'], $data['language'], $data['category'], $data['body']);
        } catch (Throwable $e) {
            return back()->withErrors(['template' => 'Meta says: '.$e->getMessage()])->withInput();
        }

        return back()->with('status', 'Sent to Meta for review. Press "Refresh status" in a few minutes.');
    }

    public function sync(Templates $templates): RedirectResponse
    {
        $total = 0;
        $errors = [];

        foreach (Channel::query()->where('type', 'whatsapp')->get() as $channel) {
            try {
                $total += $templates->sync($channel);
            } catch (Throwable $e) {
                $errors[] = $channel->name.': '.$e->getMessage();
            }
        }

        $back = back()->with('status', 'Refreshed '.$total.' template(s).');

        return $errors === [] ? $back : $back->withErrors(['template' => implode(' ', $errors)]);
    }

    public function destroy(int $id): RedirectResponse
    {
        MessageTemplate::query()->findOrFail($id)->delete();

        return back()->with('status', 'Removed from this list. Delete it in Meta too if you no longer want it there.');
    }

    /** Send an approved template into a chat (used when the 24-hour window is closed). */
    public function send(Request $request, int $id, Templates $templates): JsonResponse
    {
        $data = $request->validate(['template_id' => ['required', 'integer'], 'params' => ['array'], 'params.*' => ['nullable', 'string', 'max:200']]);
        $conversation = Conversation::query()->findOrFail($id);
        $template = MessageTemplate::query()->findOrFail($data['template_id']);

        abort_unless($conversation->channel_id === $template->channel_id, 422, 'That template belongs to another number.');

        try {
            $message = $templates->send($conversation, $template, array_values($data['params'] ?? []));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if (! $conversation->isHandledByStaff()) {
            $conversation->handOverToStaff('Staff sent a template');
        }

        return response()->json(['message' => $message->toChatArray()]);
    }
}
