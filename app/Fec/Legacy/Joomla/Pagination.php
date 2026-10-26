<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JPagination of Joomla 2.5 without a template override: the same public
 * properties (total, limitstart, limit, pagesStart, pagesStop, pagesCurrent,
 * pagesTotal) and the same HTML from getPagesLinks(), getPagesCounter() and
 * getResultsCounter(). Links are the current URL with limitstart rewritten,
 * routed through JRoute like the original.
 */
class Pagination
{
    public int $total;

    public int $limitstart;

    public int $limit;

    public int $pagesStart = 1;

    public int $pagesStop = 1;

    public int $pagesCurrent = 1;

    public int $pagesTotal = 1;

    public string $prefix;

    private bool $viewAll = false;

    /** @var array<string, mixed> */
    private array $additionalUrlParams = [];

    public function __construct(int|string $total, int|string $limitstart, int|string $limit, string $prefix = '')
    {
        $this->total = (int) $total;
        $this->limitstart = max((int) $limitstart, 0);
        $this->limit = max((int) $limit, 0);
        $this->prefix = $prefix;

        if ($this->limit > $this->total) {
            $this->limitstart = 0;
        }
        if ($this->limit === 0) {
            $this->viewAll = true;
        }

        $displayedPages = 10;
        if ($this->limit > 0) {
            $this->pagesTotal = (int) ceil($this->total / $this->limit);
            $this->pagesCurrent = (int) ceil(($this->limitstart + 1) / $this->limit);
        }
        if ($this->limitstart > $this->total - $this->limit && $this->total > 0 && $this->limit > 0) {
            $this->limitstart = max(0, (int) (ceil($this->total / $this->limit) - 1) * $this->limit);
            $this->pagesCurrent = (int) ceil(($this->limitstart + 1) / $this->limit);
        }
        $this->pagesStart = $this->pagesCurrent - (int) ($displayedPages / 2);
        if ($this->pagesStart < 1) {
            $this->pagesStart = 1;
        }
        if ($this->pagesStart + $displayedPages > $this->pagesTotal) {
            $this->pagesStop = $this->pagesTotal;
            if ($this->pagesTotal < $displayedPages) {
                $this->pagesStart = 1;
            } else {
                $this->pagesStart = $this->pagesTotal - $displayedPages + 1;
            }
        } else {
            $this->pagesStop = $this->pagesStart + $displayedPages - 1;
        }
    }

    public function setAdditionalUrlParam(string $key, mixed $value): mixed
    {
        $old = $this->additionalUrlParams[$key] ?? null;
        if ($value === null) {
            unset($this->additionalUrlParams[$key]);
        } else {
            $this->additionalUrlParams[$key] = $value;
        }

        return $old;
    }

    public function getRowOffset(int $index): int
    {
        return $index + 1 + $this->limitstart;
    }

    public function getResultsCounter(): string
    {
        if ($this->total > 0) {
            $from = $this->limitstart + 1;
            $to = min($this->limitstart + $this->limit, $this->total);
            if ($this->limit === 0) {
                $to = $this->total;
            }

            return sprintf('Results %d - %d of %d', $from, $to, $this->total);
        }

        return '';
    }

    public function getPagesCounter(): string
    {
        return $this->pagesTotal > 1 ? sprintf('Page %d of %d', $this->pagesCurrent, $this->pagesTotal) : '';
    }

    public function getPagesLinks(): string
    {
        if ($this->total <= $this->limit || $this->limit === 0) {
            return '';
        }
        $data = $this->data();
        $html = '<ul>';
        $html .= '<li class="pagination-start">'.$data['start'].'</li>';
        $html .= '<li class="pagination-prev">'.$data['previous'].'</li>';
        foreach ($data['pages'] as $page) {
            $html .= '<li>'.$page.'</li>';
        }
        $html .= '<li class="pagination-next">'.$data['next'].'</li>';
        $html .= '<li class="pagination-end">'.$data['end'].'</li>';

        return $html.'</ul>';
    }

    public function getListFooter(): string
    {
        return '<div class="list-footer"><div class="counter">'.$this->getPagesCounter().'</div>'.$this->getPagesLinks().'</div>';
    }

    public function getLimitBox(): string
    {
        return '';
    }

    /** @return array{start: string, previous: string, pages: list<string>, next: string, end: string} */
    private function data(): array
    {
        $item = function (string $text, int $base, bool $active, ?string $title = null) {
            if (! $active) {
                return '<span class="pagenav">'.$text.'</span>';
            }
            $url = $this->link($base);
            $title = $title ?? $text;
            // The live site marks the adjacent pages (sh404SEF pagination): rel="next" / rel="prev".
            $rel = '';
            if ($base === $this->limitstart + $this->limit) {
                $rel = ' rel="next"';
            } elseif ($base === $this->limitstart - $this->limit && $base >= 0) {
                $rel = ' rel="prev"';
            }

            return '<a title="'.htmlspecialchars($title, ENT_COMPAT, 'UTF-8').'" href="'.$url.'"'.$rel.' class="pagenav">'.$text.'</a>';
        };

        $out = ['start' => '', 'previous' => '', 'pages' => [], 'next' => '', 'end' => ''];
        $notFirst = $this->pagesCurrent > 1;
        $notLast = $this->pagesCurrent < $this->pagesTotal;

        $out['start'] = $item('Start', 0, $notFirst);
        $out['previous'] = $item('Prev', max(0, ($this->pagesCurrent - 2) * $this->limit), $notFirst);
        for ($i = $this->pagesStart; $i <= $this->pagesStop; $i++) {
            $offset = ($i - 1) * $this->limit;
            $out['pages'][] = $item((string) $i, $offset, $i !== $this->pagesCurrent);
        }
        $out['next'] = $item('Next', $this->pagesCurrent * $this->limit, $notLast);
        $out['end'] = $item('End', ($this->pagesTotal - 1) * $this->limit, $notLast);

        return $out;
    }

    /**
     * Links as sh404SEF rewrote them on the live site: the SEF path plus
     * "lang=en&limit=<limit>&start=<N>" (limitstart renamed to start, the
     * limit made explicit, the language first), other query variables kept
     * in between. The runner maps an incoming "start" back to limitstart.
     */
    private function link(int $limitstart): string
    {
        $uri = Uri::getInstance();
        $vars = $uri->getQuery(true);
        foreach ($this->additionalUrlParams as $k => $v) {
            $vars[$k] = (string) $v;
        }
        unset($vars['lang'], $vars['limit'], $vars['start'], $vars['limitstart'], $vars[$this->prefix.'limitstart'], $vars[$this->prefix.'start']);
        // sh404SEF renames limitstart to start only when it is not zero: page 1 links keep "limitstart=0".
        $offsetKey = $limitstart > 0 ? $this->prefix.'start' : $this->prefix.'limitstart';
        $ordered = ['lang' => 'en', 'limit' => (string) $this->limit] + $vars + [$offsetKey => (string) max(0, $limitstart)];
        $query = http_build_query($ordered);
        $path = $uri->getPath();

        return htmlspecialchars($path.($query !== '' ? '?'.$query : ''), ENT_COMPAT, 'UTF-8');
    }
}
