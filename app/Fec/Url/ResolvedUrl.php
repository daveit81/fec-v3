<?php

namespace App\Fec\Url;

/**
 * Outcome of a registry lookup for one public path.
 *
 *  kind   page      -> a canonical SEF URL: $query holds option/view/id/... from newurl
 *         alias     -> a secondary SEF URL (rank > 0) of the same page: redirect to $canonical
 *         redirect  -> a jos_redirection rule: 301 to $target (absolute or site-relative)
 *         gone      -> sh404SEF recorded a 404 for this path (empty newurl)
 *         miss      -> unknown to both tables
 */
final class ResolvedUrl
{
    /** @param array<string, string> $query */
    public function __construct(
        public readonly string $kind,
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?string $canonical = null,
        public readonly ?string $target = null,
        public readonly ?int $registryId = null,
        public readonly ?int $rank = null,
        public readonly ?string $newurl = null,
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

    public function isPage(): bool
    {
        return $this->kind === 'page';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'path' => $this->path,
            'option' => $this->option(),
            'view' => $this->view(),
            'id' => $this->id(),
            'query' => $this->query ?: null,
            'canonical' => $this->canonical,
            'target' => $this->target,
            'registry_id' => $this->registryId,
            'rank' => $this->rank,
            'newurl' => $this->newurl,
        ], fn ($v) => $v !== null);
    }
}
