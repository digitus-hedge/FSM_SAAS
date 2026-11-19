<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Http;

/**
 * open.er-api.com. One request returns every rate against the base currency ("1 BASE = x CODE").
 * We store the opposite direction (base units per one CODE), so each rate is inverted here.
 * Terms and attribution: see config/rates.php.
 */
class ExchangeRateApiProvider implements RateProvider
{
    public function name(): string
    {
        return 'open.er-api.com';
    }

    public function ratesToBase(string $base, array $currencies): array
    {
        $perBase = $this->latest($base);
        $out = [];

        foreach ($currencies as $code) {
            $code = strtoupper($code);
            $x = (float) ($perBase[$code] ?? 0);
            if ($x > 0) {
                $out[$code] = Money::rate(1 / $x);
            }
        }

        return $out;
    }

    public function supportedCurrencies(): array
    {
        return array_keys($this->latest('USD'));
    }

    /** @return array<string, float> */
    private function latest(string $base): array
    {
        $cfg = config('rates.open_er_api');

        try {
            $res = Http::timeout($cfg['timeout'])->acceptJson()->get(rtrim($cfg['base_url'], '/') . '/' . strtoupper($base));
        } catch (\Throwable $e) {
            throw new RateProviderException('Rates provider unreachable: ' . $e->getMessage(), 0, $e);
        }

        if (! $res->successful()) {
            throw new RateProviderException('Rates provider answered HTTP ' . $res->status());
        }

        $json = $res->json();
        if (($json['result'] ?? null) !== 'success' || ! is_array($json['rates'] ?? null)) {
            throw new RateProviderException('Rates provider answered with an error: ' . ($json['error-type'] ?? 'unknown'));
        }

        return $json['rates'];
    }
}
