<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JUri with the site at the root of its hostname, as freeextrachips.com is:
 *   root()      "https://www.freeextrachips.com/"   root(true)  ""
 *   base()      "https://www.freeextrachips.com/"   base(true)  ""
 *   getInstance()->toString(['scheme','host','port']) "https://www.freeextrachips.com"
 *   getInstance()->getPath() the current request path
 * The public hostname comes from fec.site_url, never from the request, so
 * pages rendered on v3. carry the live site's absolute URLs (parity).
 */
class Uri
{
    private string $scheme = 'https';

    private string $host = '';

    private ?int $port = null;

    private string $path = '/';

    private string $query = '';

    private string $fragment = '';

    /** @var array<string, string> */
    private array $vars = [];

    private static ?Uri $current = null;

    public static function reset(): void
    {
        self::$current = null;
    }

    public static function getInstance(string $uri = 'SERVER'): Uri
    {
        if ($uri === 'SERVER') {
            if (self::$current === null) {
                $site = self::site();
                $request = request();
                // getPathInfo() keeps a trailing slash (Laravel's path() trims it): sh404SEF
                // URLs such as /bonuses/<casino>/ end with one and the pages echo it.
                self::$current = new Uri($site.'/'.ltrim($request->getPathInfo(), '/').($request->getQueryString() !== null && $request->getQueryString() !== '' ? '?'.$request->getQueryString() : ''));
            }

            return self::$current;
        }

        return new Uri($uri);
    }

    public function __construct(?string $uri = null)
    {
        if ($uri !== null) {
            $this->parse($uri);
        }
    }

    /** "https://www.freeextrachips.com" (no trailing slash) */
    public static function site(): string
    {
        return rtrim((string) config('fec.site_url', 'https://www.freeextrachips.com'), '/');
    }

    public static function root(bool $pathonly = false, ?string $path = null): string
    {
        $rootPath = (string) parse_url(self::site(), PHP_URL_PATH);
        if ($pathonly) {
            return rtrim($rootPath, '/');
        }

        return self::site().'/';
    }

    public static function base(bool $pathonly = false): string
    {
        return self::root($pathonly);
    }

    public static function current(): string
    {
        return self::getInstance()->toString(['scheme', 'host', 'port', 'path']);
    }

    public function parse(string $uri): bool
    {
        $parts = parse_url($uri);
        if ($parts === false) {
            return false;
        }
        $this->scheme = $parts['scheme'] ?? $this->scheme;
        $this->host = $parts['host'] ?? $this->host;
        $this->port = isset($parts['port']) ? (int) $parts['port'] : $this->port;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
        parse_str($this->query, $vars);
        $this->vars = array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $vars);

        return true;
    }

    /** @param  list<string>  $parts */
    public function toString(array $parts = ['scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment']): string
    {
        $out = '';
        if (in_array('scheme', $parts, true) && $this->host !== '') {
            $out .= $this->scheme.'://';
        }
        if (in_array('host', $parts, true)) {
            $out .= $this->host;
        }
        if (in_array('port', $parts, true) && $this->port !== null) {
            $out .= ':'.$this->port;
        }
        if (in_array('path', $parts, true)) {
            $out .= $this->path;
        }
        if (in_array('query', $parts, true) && $this->query !== '') {
            $out .= '?'.$this->query;
        }
        if (in_array('fragment', $parts, true) && $this->fragment !== '') {
            $out .= '#'.$this->fragment;
        }

        return $out;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getQuery(bool $toArray = false): string|array
    {
        return $toArray ? $this->vars : $this->query;
    }

    public function getVar(string $name, mixed $default = null): mixed
    {
        return $this->vars[$name] ?? $default;
    }

    public function setVar(string $name, mixed $value): void
    {
        $this->vars[$name] = (string) $value;
        $this->query = http_build_query($this->vars);
    }

    public function delVar(string $name): void
    {
        unset($this->vars[$name]);
        $this->query = http_build_query($this->vars);
    }

    public function isSSL(): bool
    {
        return strtolower($this->scheme) === 'https';
    }
}
