<?php

namespace App\Fec\Url;

/**
 * What the front controller does with a request, as decided by the Router.
 *
 *  action  redirect    -> $status 301, Location $location (desk rule or alias -> canonical)
 *          render      -> $handler (a PageHandler class) renders $url
 *          not_ported  -> the path is a known page, v3 has no handler yet: 404
 *          not_found   -> gone or miss: 404
 *          gone        -> a legacy .htaccess [G] rule: 410
 *          forbidden   -> a legacy .htaccess [F] rule (the query-string exploit filters): 403
 */
final class Decision
{
    public function __construct(
        public readonly string $action,
        public readonly int $status,
        public readonly ResolvedUrl $url,
        public readonly ?string $location = null,
        public readonly ?string $handler = null,
    ) {}

    public function isRedirect(): bool
    {
        return $this->action === 'redirect';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'action' => $this->action,
            'status' => $this->status,
            'location' => $this->location,
            'handler' => $this->handler,
            'url' => $this->url->toArray(),
        ], fn ($v) => $v !== null);
    }
}
