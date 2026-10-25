<?php

namespace App\Fec\Url;

/**
 * Outcome of a lookup for one public path.
 *
 *  kind   page      -> a canonical URL: $query holds option/view/id/... as Joomla would see them
 *         alias     -> a secondary SEF URL (rank > 0) of the same page: redirect to $canonical
 *         redirect  -> a jos_redirection rule: 301 to $target (absolute or site-relative)
 *         gone      -> sh404SEF recorded a 404 for this path (empty newurl)
 *         miss      -> unknown to the registry and to every pattern
 *
 *  source where the answer came from: "redirection", "sh404sef" or "pattern:<name>"
 *         (the URL families that com_bonuses builds at run time and sh404SEF never stored).
 *  meta   pattern-specific facts (slugs seen in the path, consistency checks) for
 *         the handler and for the resolve command; never part of the Joomla query.
 */
final class ResolvedUrl
{
    /**
     * @param array<string, string> $query
     * @param array<string, string|int|bool|null> $meta
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?string $canonical = null,
        public readonly ?string $target = null,
        public readonly ?int $registryId = null,
        public readonly ?int $rank = null,
        public readonly ?string $newurl = null,
        public readonly string $source = 'sh404sef',
        public readonly array $meta = [],
    ) {
    }

    public function option(): ?string
    {
        return $this->query['option'] ?? null;
    }

    public function view(): ?string
    {
        return $this->query['view'] ?? null;
    }

    public function id(): ?int
    {
        return isset($this->query['id']) && is_numeric($this->query['id']) ? (int) $this->query['id'] : null;
    }

    /** Integer query variable (bid, cid, catid, Itemid...) or null. */
    public function int(string $key): ?int
    {
        return isset($this->query[$key]) && is_numeric($this->query[$key]) ? (int) $this->query[$key] : null;
    }

    public function isPage(): bool
    {
        return $this->kind === 'page';
    }

    /** "option.view" key used by the dispatcher map (view falls back to task, then "default"). */
    public function routeKey(): ?string
    {
        $option = $this->option();
        if ($option === null || $option === '') {
            return null;
        }

        return $option.'.'.($this->view() ?? ($this->query['task'] ?? 'default'));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'source' => $this->source,
            'path' => $this->path,
            'option' => $this->option(),
            'view' => $this->view(),
            'id' => $this->id(),
            'query' => $this->query ?: null,
            'meta' => $this->meta ?: null,
            'canonical' => $this->canonical,
            'target' => $this->target,
            'registry_id' => $this->registryId,
            'rank' => $this->rank,
            'newurl' => $this->newurl,
        ], fn ($v) => $v !== null);
    }
}
