<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JFilterInput (Joomla 2.5) for the calls our code makes: getInstance() and
 * clean($source, $type). The default instance (no tag or attribute lists,
 * whitelist method) removes EVERY tag, for the "HTML" type too: on the live
 * site $filter->clean($infos, 'HTML') left plain text, and so does this.
 * An instance built with the blacklist method (getInstance(null, null, 1, 1))
 * keeps the markup but drops the tags and attributes Joomla blacklisted plus,
 * with xssAuto, the on* handlers and the script: URLs.
 */
class FilterInput
{
    /** @var array<string, self> */
    private static array $instances = [];

    private const TAG_BLACKLIST = ['applet', 'body', 'bgsound', 'base', 'basefont', 'embed', 'frame', 'frameset', 'head', 'html', 'id', 'iframe', 'ilayer', 'layer', 'link', 'meta', 'name', 'object', 'script', 'style', 'title', 'xml'];

    private const ATTR_BLACKLIST = ['action', 'background', 'codebase', 'dynsrc', 'lowsrc'];

    /**
     * @param  list<string>  $tagsArray
     * @param  list<string>  $attrArray
     */
    public function __construct(
        private array $tagsArray = [],
        private array $attrArray = [],
        private int $tagsMethod = 0,
        private int $attrMethod = 0,
        private int $xssAuto = 1,
    ) {
        $this->tagsArray = array_map('strtolower', $tagsArray);
        $this->attrArray = array_map('strtolower', $attrArray);
    }

    /**
     * @param  list<string>|null  $tagsArray
     * @param  list<string>|null  $attrArray
     */
    public static function getInstance(?array $tagsArray = null, ?array $attrArray = null, int $tagsMethod = 0, int $attrMethod = 0, int $xssAuto = 1): self
    {
        $key = md5(serialize([$tagsArray, $attrArray, $tagsMethod, $attrMethod, $xssAuto]));

        return self::$instances[$key] ??= new self($tagsArray ?? [], $attrArray ?? [], $tagsMethod, $attrMethod, $xssAuto);
    }

    public function clean(mixed $source, string $type = 'string'): mixed
    {
        switch (strtoupper($type)) {
            case 'INT':
            case 'INTEGER':
                preg_match('/-?[0-9]+/', (string) $source, $m);

                return isset($m[0]) ? (int) $m[0] : 0;
            case 'UINT':
                preg_match('/-?[0-9]+/', (string) $source, $m);

                return isset($m[0]) ? abs((int) $m[0]) : 0;
            case 'FLOAT':
            case 'DOUBLE':
                preg_match('/-?[0-9]+(\.[0-9]+)?/', (string) $source, $m);

                return isset($m[0]) ? (float) $m[0] : 0.0;
            case 'BOOL':
            case 'BOOLEAN':
                return (bool) $source;
            case 'WORD':
                return (string) preg_replace('/[^A-Z_]/i', '', (string) $source);
            case 'ALNUM':
                return (string) preg_replace('/[^A-Z0-9]/i', '', (string) $source);
            case 'CMD':
                return ltrim((string) preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $source), '.');
            case 'BASE64':
                return (string) preg_replace('/[^A-Z0-9\/+=]/i', '', (string) $source);
            case 'STRING':
                return $this->remove($this->decode((string) $source));
            case 'HTML':
                return $this->remove((string) $source);
            case 'ARRAY':
                return (array) $source;
            case 'PATH':
                preg_match('/^[A-Za-z0-9_\/-]+[A-Za-z0-9_\.-]*([\\\\\/][A-Za-z0-9_-]+[A-Za-z0-9_\.-]*)*$/', (string) $source, $m);

                return $m[0] ?? '';
            case 'USERNAME':
                return (string) preg_replace('/[\x00-\x1F\x7F<>"\'%&]/', '', (string) $source);
            default:
                // RAW and unknown types: Joomla 2.5 applied the XSS cleaning to arrays
                // and strings when xssAuto was on; a raw request leaves it alone.
                if (is_array($source)) {
                    return array_map(fn ($v) => is_string($v) ? $this->remove($this->decode($v)) : $v, $source);
                }

                return is_string($source) && $this->xssAuto ? $this->remove($this->decode($source)) : $source;
        }
    }

    private function decode(string $source): string
    {
        return html_entity_decode($source, ENT_QUOTES | ENT_HTML401, 'UTF-8');
    }

    /** Joomla's _remove(): clean the tags until nothing changes any more. */
    private function remove(string $source): string
    {
        $loops = 0;
        do {
            $before = $source;
            $source = $this->cleanTags($source);
            $loops++;
        } while ($before !== $source && $loops < 10);

        return $source;
    }

    private function cleanTags(string $source): string
    {
        // whitelist method with an empty list (the default instance): no tag survives
        if ($this->tagsMethod === 0 && $this->tagsArray === []) {
            return strip_tags($source);
        }

        return (string) preg_replace_callback('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>/', function (array $m): string {
            $closing = $m[1] === '/';
            $tag = strtolower($m[2]);
            $inList = in_array($tag, $this->tagsArray, true);
            $keep = $this->tagsMethod === 0 ? $inList : ! $inList;
            if ($this->xssAuto && in_array($tag, self::TAG_BLACKLIST, true)) {
                $keep = false;
            }
            if (! $keep) {
                return '';
            }
            if ($closing) {
                return '</'.$tag.'>';
            }

            return '<'.$tag.$this->cleanAttributes($m[3]).'>';
        }, $source);
    }

    private function cleanAttributes(string $attrs): string
    {
        $out = '';
        preg_match_all('/\s+([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $attrs, $all, PREG_SET_ORDER);
        foreach ($all as $a) {
            $name = strtolower($a[1]);
            $value = $a[2] ?? null;
            $inList = in_array($name, $this->attrArray, true);
            $keep = $this->attrMethod === 0 ? ($this->attrArray !== [] && $inList) : ! $inList;
            if ($this->xssAuto && (str_starts_with($name, 'on') || in_array($name, self::ATTR_BLACKLIST, true))) {
                $keep = false;
            }
            if ($value !== null) {
                $bare = strtolower(trim($value, '"\''));
                if (preg_match('/^\s*(javascript|vbscript|data)\s*:/i', $bare) || str_contains($bare, 'expression(')) {
                    $keep = false;
                }
            }
            if (! $keep) {
                continue;
            }
            $out .= ' '.$name.($value !== null ? '='.$value : '');
        }

        return $out;
    }
}
