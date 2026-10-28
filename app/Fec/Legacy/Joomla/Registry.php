<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JRegistry: dotted-path access to a nested array (menu item params,
 * component params, configuration). get/set/exists/loadString/loadArray/
 * loadObject/toArray/toObject/toString are the methods our code uses.
 */
class Registry
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(mixed $data = null)
    {
        if (is_array($data)) {
            $this->loadArray($data);
        } elseif (is_object($data)) {
            $this->loadObject($data);
        } elseif (is_string($data) && $data !== '') {
            $this->loadString($data);
        }
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $node = $this->data;
        foreach (explode('.', $path) as $key) {
            if (is_array($node) && array_key_exists($key, $node)) {
                $node = $node[$key];
            } elseif (is_object($node) && isset($node->{$key})) {
                $node = $node->{$key};
            } else {
                return $default;
            }
        }

        // Joomla returns the default for empty strings too (JRegistry::get).
        return ($node === null || $node === '') ? $default : $node;
    }

    public function set(string $path, mixed $value): mixed
    {
        $node = &$this->data;
        foreach (explode('.', $path) as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node = $value;

        return $value;
    }

    public function exists(string $path): bool
    {
        return $this->get($path, "\0") !== "\0";
    }

    public function def(string $path, mixed $default = ''): mixed
    {
        $value = $this->get($path, $default);
        $this->set($path, $value);

        return $value;
    }

    /**
     * JSON (Joomla 2.5 params) or INI "key=value" lines (Joomla 1.5 leftovers): as
     * Joomla 2.5's JSON reader did, a string that neither starts with "{" nor ends
     * with "}" goes to the INI reader (an old article's metadata "robots=index,
     * follow" set the page's robots on the live site).
     */
    public function loadString(string $data, string $format = 'JSON'): static
    {
        $data = trim($data);
        if ($data === '') {
            return $this;
        }
        if (strtoupper($format) === 'JSON' && (str_starts_with($data, '{') || str_ends_with($data, '}'))) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $this->loadArray($decoded);
            }

            return $this;
        }

        return $this->loadArray(self::iniToArray($data));
    }

    /**
     * JRegistryFormatINI::stringToObject() of Joomla 2.5.28 without sections:
     * keys of letters, digits and underscores, quoted values are strings,
     * true/false booleans, numbers int or float, anything else as written.
     *
     * @return array<string, mixed>
     */
    public static function iniToArray(string $data): array
    {
        $out = [];
        foreach (explode("\n", $data) as $line) {
            $line = trim($line);
            if ($line === '' || $line === '0' || $line[0] === ';' || $line[0] === '[') {
                continue;
            }
            if (! strpos($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            if (preg_match('/[^A-Z0-9_]/i', $key)) {
                continue;
            }
            $length = strlen($value);
            if ($length > 0 && $value[0] === '"' && $value[$length - 1] === '"') {
                $value = str_replace('\n', "\n", stripcslashes(substr($value, 1, $length - 2)));
            } elseif ($value === 'false') {
                $value = false;
            } elseif ($value === 'true') {
                $value = true;
            } elseif (is_numeric($value)) {
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $array */
    public function loadArray(array $array): static
    {
        $this->data = array_replace_recursive($this->data, $array);

        return $this;
    }

    public function loadObject(object $object): static
    {
        return $this->loadArray(json_decode((string) json_encode($object), true) ?: []);
    }

    public function merge(Registry $other): static
    {
        return $this->loadArray($other->toArray());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function toObject(): object
    {
        return json_decode((string) json_encode($this->data)) ?: new \stdClass;
    }

    public function toString(string $format = 'JSON'): string
    {
        return (string) json_encode($this->data);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
