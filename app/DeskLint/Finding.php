<?php

namespace App\DeskLint;

/**
 * One inconsistency found by a rule. The fingerprint identifies the SAME
 * problem across runs (rule + stable key), so that the report can say
 * "new" or "resolved" instead of repeating the whole list every night.
 */
final class Finding
{
    public function __construct(
        public readonly string $rule,
        public readonly string $key,
        public readonly string $subject,
        public readonly string $detail = '',
        public readonly ?string $url = null,
    ) {
    }

    public function fingerprint(): string
    {
        return $this->rule.':'.$this->key;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'key' => $this->key,
            'subject' => $this->subject,
            'detail' => $this->detail,
            'url' => $this->url,
        ];
    }
}
