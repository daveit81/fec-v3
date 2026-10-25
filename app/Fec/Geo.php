<?php

namespace App\Fec;

use Illuminate\Http\Request;

/**
 * Visitor country as the legacy template computed it: the Cloudflare
 * CF-IPCountry header, with "XX" (unknown) and "T1" (Tor) treated as no
 * detection. An undetected country counts as EU, so the consent default
 * stays on the safe side. Port of templates/fecv2/index.php lines 110-141.
 */
final class Geo
{
    public function __construct(
        public readonly string $country,
        public readonly bool $isEu,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $code = strtoupper(trim((string) $request->header('CF-IPCountry', '')));

        if (! preg_match('/^[A-Z]{2}$/', $code) || $code === 'XX' || $code === 'T1') {
            $code = '';
        }

        $isEu = $code === '' || in_array($code, config('fec.eu_countries', []), true);

        return new self($code, $isEu);
    }

    /** Lowercase code with the legacy "us" fallback used by the catalogue queries. */
    public function catalogueCode(): string
    {
        return $this->country === '' ? 'us' : strtolower($this->country);
    }
}
