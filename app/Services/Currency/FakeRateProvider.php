<?php

namespace App\Services\Currency;

/** In-memory provider for tests and local work. Set rates with ::set(), make it fail with ::failing(). */
class FakeRateProvider implements RateProvider
{
    /** @var array<string, string> code => base units per one unit */
    public static array $rates = ['USD' => '3.67250000', 'EUR' => '4.00000000', 'AED' => '1.00000000'];
    public static bool $fails = false;
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$rates = ['USD' => '3.67250000', 'EUR' => '4.00000000', 'AED' => '1.00000000'];
        self::$fails = false;
        self::$calls = 0;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function ratesToBase(string $base, array $currencies): array
    {
        self::$calls++;
        if (self::$fails) {
            throw new RateProviderException('Provider down');
        }

        $out = [];
        foreach ($currencies as $code) {
            $code = strtoupper($code);
            if (array_key_exists($code, self::$rates)) {
                $out[$code] = is_numeric(self::$rates[$code]) ? Money::rate(self::$rates[$code]) : (string) self::$rates[$code];
            }
        }

        return $out;
    }

    public function supportedCurrencies(): array
    {
        if (self::$fails) {
            throw new RateProviderException('Provider down');
        }

        return array_keys(self::$rates);
    }
}
