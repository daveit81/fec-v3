<?php

namespace App\Http\Controllers\Desk;

use App\Fec\Contact\ContactForm;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * /desk/messages: the copies of what the contact form mailed (contact_messages),
 * newest first, by topic and status; a message, and "handled" / "to do".
 */
class MessagesController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $topics = ContactForm::topics((array) config('fec.contact.topics', []));
        $topic = (string) $request->query('topic', '');
        $status = (string) $request->query('status', '');

        $q = DB::table('contact_messages');
        if (isset($topics[$topic])) {
            $q->where('topic', $topic);
        }
        if (in_array($status, ['new', 'done'], true)) {
            $q->where('status', $status);
        }
        $rows = $q->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
        $counts = DB::table('contact_messages')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return view('desk.messages', compact('rows', 'topics', 'topic', 'status', 'counts'));
    }

    public function show(int $id): View
    {
        $row = DB::table('contact_messages')->where('id', $id)->first();
        abort_if($row === null, 404);

        return view('desk.message', [
            'row' => $row,
            'topics' => ContactForm::topics((array) config('fec.contact.topics', [])),
        ]);
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        $status = (string) $request->input('status', '');
        abort_unless(in_array($status, ['new', 'done'], true), 422);
        $changed = DB::table('contact_messages')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
        abort_if($changed === 0 && ! DB::table('contact_messages')->where('id', $id)->exists(), 404);

        return redirect()->route('desk.messages.show', $id)->with('desk_ok', $status === 'done' ? 'Marked as handled.' : 'Moved back to the to-do list.');
    }
}
