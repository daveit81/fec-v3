<?php

namespace App\Http\Controllers\Desk;

use App\Fec\Desk\JackpotsDesk;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * /desk/jackpots: the jackpot desk rewritten for v3 (App\Fec\Desk\JackpotsDesk says
 * why only "published" is written). Logged-in redazione, Laravel's CSRF token, reads
 * on "fec", the one write on "fec_rw".
 */
class JackpotsController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $published = JackpotsDesk::published($request->query('published'));
        $soft = trim((string) $request->query('soft', ''));

        $query = DB::connection('fec')->table('jackpots')
            ->leftJoin('content', 'content.id', '=', 'jackpots.cid')
            ->select([
                'jackpots.jid', 'jackpots.cid', 'jackpots.name', 'jackpots.currency', 'jackpots.amount',
                'jackpots.soft', 'jackpots.published', 'jackpots.last_update', 'content.title',
            ]);
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like): void {
                $w->where('jackpots.name', 'like', $like)->orWhere('jackpots.jid', 'like', $like)->orWhere('content.title', 'like', $like);
            });
        }
        if ($published !== null) {
            $query->where('jackpots.published', $published);
        }
        if ($soft !== '') {
            $query->where('jackpots.soft', $soft);
        }

        $rows = $query->orderBy('jackpots.name')->orderBy('jackpots.jid')
            ->paginate(JackpotsDesk::PER_PAGE)->withQueryString();

        $counts = DB::connection('fec')->table('jackpots')
            ->selectRaw('published, COUNT(*) AS n')->groupBy('published')->pluck('n', 'published');

        return view('desk.jackpots', [
            'rows' => $rows,
            'softs' => $this->softwareLabels(),
            'q' => $q,
            'published' => $published,
            'soft' => $soft,
            'counts' => $counts,
            'now' => time(),
        ]);
    }

    /** POST jid + published (0/1): show or hide a jackpot in the reviews' jackpot section. */
    public function publish(Request $request): RedirectResponse
    {
        $jid = $request->input('jid');
        $published = JackpotsDesk::published($request->input('published'));
        $back = $this->backUrl($request);
        if (! JackpotsDesk::validJid($jid) || $published === null) {
            return redirect($back)->with('desk_error', 'Richiesta non valida.');
        }

        try {
            $changed = DB::connection('fec_rw')->table('jackpots')->where('jid', $jid)->update(['published' => $published]);
            $exists = $changed > 0 || DB::connection('fec')->table('jackpots')->where('jid', $jid)->exists();
        } catch (Throwable $e) {
            Log::error('jackpots desk: update failed: '.$e->getMessage());

            return redirect($back)->with('desk_error', 'Salvataggio non riuscito (errore del database).');
        }
        if (! $exists) {
            return redirect($back)->with('desk_error', 'Jackpot '.$jid.' non trovato.');
        }
        Log::info('jackpots desk: '.$jid.' published='.$published.' by user '.(int) $request->user()?->id);

        return redirect($back)->with('desk_ok', 'Jackpot '.$jid.($published ? ' pubblicato.' : ' nascosto.'));
    }

    /** @return array<string, string> soft value => label (JReviews field 311, as the old desk read it). */
    private function softwareLabels(): array
    {
        try {
            return DB::connection('fec')->table('jreviews_fieldoptions')->where('fieldid', 311)
                ->orderBy('text')->pluck('text', 'value')->map(fn ($t) => (string) $t)->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** The list as it was (filters and page kept), never another host. */
    private function backUrl(Request $request): string
    {
        $back = (string) $request->input('back', '');

        return str_starts_with($back, '/desk/jackpots') && ! str_contains($back, "\n") ? $back : route('desk.jackpots');
    }
}
