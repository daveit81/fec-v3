<?php

namespace App\Http\Controllers\Desk;

use App\Fec\Desk\LinksDesk;
use App\Fec\Shortlink\Shortlinks;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * /desk/links: the affiliate links (jos_shortlink) behind /goto.php?link=..., the
 * management com_shortlink did in the Joomla backend (App\Fec\Desk\LinksDesk says what
 * is kept and what is not). Logged-in redazione, Laravel's CSRF token, reads on "fec",
 * writes on "fec_rw" (INSERT, UPDATE, DELETE on jos_shortlink). A phrase that a casino,
 * a bonus, an article or a module uses can be pointed elsewhere but not renamed or deleted.
 */
class LinksController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $show = (string) $request->query('show', 'all');
        $show = in_array($show, LinksDesk::SHOWS, true) ? $show : 'all';
        $sort = (string) $request->query('sort', 'newest');
        $sort = isset(LinksDesk::SORTS[$sort]) ? $sort : 'newest';

        $usage = $this->usage();
        $used = array_values(array_map(fn (array $u): string => $u['phrase'], $usage));

        $query = DB::connection('fec')->table('shortlink')
            ->select(['id', 'phrase', 'link', 'description', 'counter', 'create_date', 'last_call']);
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like): void {
                $w->where('phrase', 'like', $like)->orWhere('link', 'like', $like)->orWhere('description', 'like', $like);
            });
        }
        if ($show === 'unused' && $used !== []) {
            $query->whereNotIn('phrase', $used);
        } elseif ($show === 'idle') {
            $query->where('last_call', '<', gmdate('Y-m-d H:i:s', time() - 30 * 86400));
        } elseif ($show === 'never') {
            $query->where('last_call', '<', '1971-01-01');
        }
        [$column, $dir] = LinksDesk::SORTS[$sort];
        $rows = $query->orderBy($column, $dir)->orderBy('id', 'desc')
            ->paginate(LinksDesk::PER_PAGE)->withQueryString();

        $total = DB::connection('fec')->table('shortlink')->count();
        $inUse = $used === [] ? 0 : DB::connection('fec')->table('shortlink')->whereIn('phrase', $used)->count();

        return view('desk.links', [
            'rows' => $rows,
            'usage' => $usage,
            'clicks' => $this->clicks30(array_map(fn ($r) => (string) $r->phrase, $rows->items())),
            'q' => $q,
            'show' => $show,
            'sort' => $sort,
            'total' => $total,
            'inUse' => $inUse,
            'never' => DB::connection('fec')->table('shortlink')->where('last_call', '<', '1971-01-01')->count(),
        ]);
    }

    public function create(): View
    {
        return view('desk.link', ['row' => null, 'uses' => null, 'stats' => null, 'goUrl' => '', 'resolves' => '']);
    }

    public function store(Request $request): RedirectResponse
    {
        $back = redirect()->route('desk.links.create')->withInput();
        $phrase = LinksDesk::phrase($request->input('phrase'));
        $link = LinksDesk::link($request->input('link'));
        $errors = [];
        if ($phrase === null) {
            $errors['phrase'] = 'The phrase takes letters, digits, dot, dash and underscore (up to 100), starting with a letter or a digit.';
        } elseif ($this->phraseTaken($phrase, 0)) {
            $errors['phrase'] = 'The phrase "'.$phrase.'" exists already.';
        }
        if ($link === null) {
            $errors['link'] = 'The destination is a full URL (https://...) or a path of this site starting with "/".';
        }
        if ($errors !== []) {
            return $back->withErrors($errors);
        }

        try {
            $id = (int) DB::connection('fec_rw')->table('shortlink')->insertGetId([
                'phrase' => $phrase,
                'link' => $link,
                'target' => '',
                'counter' => 0,
                'description' => LinksDesk::description($request->input('description')),
                'create_date' => gmdate('Y-m-d H:i:s'),
                'last_call' => '0000-00-00 00:00:00',
            ]);
        } catch (Throwable $e) {
            Log::error('links desk: insert failed: '.$e->getMessage());

            return $back->with('desk_error', 'Not saved: database error.');
        }
        Log::info('links desk: #'.$id.' "'.$phrase.'" created -> '.$link.' by user '.(int) $request->user()?->id);

        return redirect()->route('desk.links.edit', $id)->with('desk_ok', 'Link "'.$phrase.'" created.');
    }

    public function edit(int $id, Shortlinks $shortlinks): View
    {
        $row = $this->row($id);
        $usage = $this->usage();
        $params = (array) config('fec.goto.params', ['link']);

        return view('desk.link', [
            'row' => $row,
            'uses' => $usage[LinksDesk::key((string) $row->phrase)] ?? null,
            'stats' => $this->stats((string) $row->phrase),
            'goUrl' => LinksDesk::goUrl((string) config('fec.site_url'), (string) $row->phrase, (string) ($params[0] ?? 'link')),
            'resolves' => $shortlinks->target($row),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = $this->row($id);
        $back = redirect()->route('desk.links.edit', $id);
        $inUse = isset($this->usage()[LinksDesk::key((string) $row->phrase)]);
        $errors = [];

        // the phrase: kept when it is in use or not changed (an old spelling stays valid)
        $phrase = (string) $row->phrase;
        $typed = trim((string) $request->input('phrase', $phrase));
        if (! $inUse && $typed !== $phrase) {
            $new = LinksDesk::phrase($typed);
            if ($new === null) {
                $errors['phrase'] = 'The phrase takes letters, digits, dot, dash and underscore (up to 100), starting with a letter or a digit.';
            } elseif ($this->phraseTaken($new, $id)) {
                $errors['phrase'] = 'The phrase "'.$new.'" exists already.';
            } else {
                $phrase = $new;
            }
        }

        $link = (string) $row->link;
        $typedLink = trim((string) $request->input('link', $link));
        if ($typedLink !== $link) {
            $new = LinksDesk::link($typedLink);
            if ($new === null) {
                $errors['link'] = 'The destination is a full URL (https://...) or a path of this site starting with "/".';
            } else {
                $link = $new;
            }
        }
        if ($errors !== []) {
            return $back->withInput()->withErrors($errors);
        }

        $description = LinksDesk::description($request->input('description'));
        $changes = array_filter([
            'phrase' => $phrase !== (string) $row->phrase ? $phrase : null,
            'link' => $link !== (string) $row->link ? $link : null,
            'description' => $description !== (string) $row->description ? $description : null,
        ], fn ($v) => $v !== null);
        if ($changes === []) {
            return $back->with('desk_ok', 'No changes.');
        }

        try {
            DB::connection('fec_rw')->table('shortlink')->where('id', $id)->update($changes);
        } catch (Throwable $e) {
            Log::error('links desk: update of #'.$id.' failed: '.$e->getMessage());

            return $back->withInput()->with('desk_error', 'Not saved: database error.');
        }
        $log = [];
        foreach ($changes as $col => $value) {
            $log[] = $col.' "'.$row->{$col}.'" -> "'.$value.'"';
        }
        Log::info('links desk: #'.$id.' '.implode(', ', $log).' by user '.(int) $request->user()?->id);

        return $back->with('desk_ok', 'Saved.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $row = $this->row($id);
        if (isset($this->usage()[LinksDesk::key((string) $row->phrase)])) {
            return redirect()->route('desk.links.edit', $id)
                ->with('desk_error', 'Not deleted: a casino, a bonus, an article or a module uses "'.$row->phrase.'".');
        }
        try {
            DB::connection('fec_rw')->table('shortlink')->where('id', $id)->delete();
        } catch (Throwable $e) {
            Log::error('links desk: delete of #'.$id.' failed: '.$e->getMessage());

            return redirect()->route('desk.links.edit', $id)->with('desk_error', 'Not deleted: database error.');
        }
        // the whole row, so it can be put back by hand
        Log::info('links desk: #'.$id.' deleted by user '.(int) $request->user()?->id.': '.json_encode((array) $row, JSON_UNESCAPED_SLASHES));

        return redirect()->route('desk.links')->with('desk_ok', 'Link "'.$row->phrase.'" deleted.');
    }

    private function row(int $id): object
    {
        $row = DB::connection('fec')->table('shortlink')->where('id', $id)->first();
        abort_if($row === null, 404);

        return $row;
    }

    /** Another row has this phrase (the table's collation ignores case, so does the check). */
    private function phraseTaken(string $phrase, int $exceptId): bool
    {
        return DB::connection('fec')->table('shortlink')->where('phrase', $phrase)->where('id', '<>', $exceptId)->exists();
    }

    /**
     * Who points at each phrase: casinos (jr_gourl), bonuses (link), articles (body) and
     * Joomla modules (custom HTML), by LinksDesk::key(). Read whole on every page: some
     * 850 rows.
     *
     * @return array<string, array{phrase: string, casinos: list<array{id: int, title: string}>, bonuses: list<array{id: int, title: string}>, articles: list<array{id: int, title: string, state: int}>, modules: list<array{id: int, title: string, state: int}>}>
     */
    private function usage(): array
    {
        $params = array_values(array_map('strval', (array) config('fec.goto.params', ['link'])));
        $map = [];
        $add = function (string $kind, string $text, array $item) use (&$map, $params): void {
            foreach (LinksDesk::phrasesIn($text, $params) as $phrase) {
                $key = LinksDesk::key($phrase);
                $map[$key] ??= ['phrase' => $phrase, 'casinos' => [], 'bonuses' => [], 'articles' => [], 'modules' => []];
                $map[$key][$kind][] = $item;
            }
        };

        $db = DB::connection('fec');
        foreach ($db->table('jreviews_content')->leftJoin('content', 'content.id', '=', 'jreviews_content.contentid')
            ->where('jreviews_content.jr_gourl', 'like', '%goto.php%')
            ->orderBy('content.title')
            ->get(['jreviews_content.contentid', 'jreviews_content.jr_gourl', 'content.title']) as $r) {
            $add('casinos', (string) $r->jr_gourl, ['id' => (int) $r->contentid, 'title' => (string) ($r->title ?? '#'.$r->contentid)]);
        }
        foreach ($db->table('bonuses')->where('link', 'like', '%goto.php%')->orderBy('bid')
            ->get(['bid', 'brand', 'label', 'status', 'link']) as $r) {
            $add('bonuses', (string) $r->link, ['id' => (int) $r->bid, 'title' => trim($r->brand.' - '.$r->label.' ('.$r->status.')')]);
        }
        foreach ($db->table('content')->where(function ($w): void {
            $w->where('introtext', 'like', '%goto.php%')->orWhere('fulltext', 'like', '%goto.php%');
        })->orderBy('id')->get(['id', 'title', 'state', 'introtext', 'fulltext']) as $r) {
            $add('articles', $r->introtext.' '.$r->fulltext, ['id' => (int) $r->id, 'title' => (string) $r->title, 'state' => (int) $r->state]);
        }
        foreach ($db->table('modules')->where('content', 'like', '%goto.php%')->orderBy('id')
            ->get(['id', 'title', 'published', 'content']) as $r) {
            $add('modules', (string) $r->content, ['id' => (int) $r->id, 'title' => (string) $r->title, 'state' => (int) $r->published]);
        }

        return $map;
    }

    /**
     * Clicks of the last 30 days (no bots) from jos_bonus_clicks, the tracker of record,
     * for the phrases of a page of the list.
     *
     * @param  list<string>  $phrases
     * @return array<string, int> by LinksDesk::key()
     */
    private function clicks30(array $phrases): array
    {
        if ($phrases === []) {
            return [];
        }
        $out = [];
        try {
            $rows = DB::connection('fec')->table('bonus_clicks')
                ->whereIn('slug', $phrases)->where('is_bot', 0)
                ->where('created_at', '>=', gmdate('Y-m-d H:i:s', time() - 30 * 86400))
                ->groupBy('slug')->selectRaw('slug, COUNT(*) AS n')->get();
            foreach ($rows as $r) {
                $key = LinksDesk::key((string) $r->slug);
                $out[$key] = ($out[$key] ?? 0) + (int) $r->n;
            }
        } catch (Throwable $e) {
            Log::warning('links desk: click counts unavailable: '.$e->getMessage());
        }

        return $out;
    }

    /** @return array{clicks30: int, bots30: int, clicks: int, last: string}|null */
    private function stats(string $phrase): ?array
    {
        try {
            $r = DB::connection('fec')->table('bonus_clicks')->where('slug', $phrase)
                ->selectRaw('COUNT(*) AS n, SUM(created_at >= ? AND is_bot = 0) AS n30, SUM(created_at >= ? AND is_bot = 1) AS b30, MAX(created_at) AS last', [
                    gmdate('Y-m-d H:i:s', time() - 30 * 86400), gmdate('Y-m-d H:i:s', time() - 30 * 86400),
                ])->first();
        } catch (Throwable $e) {
            Log::warning('links desk: click stats unavailable: '.$e->getMessage());

            return null;
        }

        return [
            'clicks30' => (int) ($r->n30 ?? 0),
            'bots30' => (int) ($r->b30 ?? 0),
            'clicks' => (int) ($r->n ?? 0),
            'last' => LinksDesk::when($r->last ?? null),
        ];
    }
}
