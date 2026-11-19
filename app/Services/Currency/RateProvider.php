<?php

namespace App\Services\Currency;

/** A source of daily exchange rates. Implementations are registered in config/rates.php. */
interface RateProvider
{
    /** Short name stored nowhere but shown in logs. */
    public function name(): string;

    /**
     * Rates against a base currency, as the system stores them (D2): base-currency units for ONE unit of each
     * foreign currency. Currencies the provider does not know are left out of the result.
     *
     * @param  string[]  $currencies  ISO codes
     * @return array<string, string>  code => rate with 8 decimals
     * @throws RateProviderException   when the provider cannot answer
     */
    public function ratesToBase(string $base, array $currencies): array;

    /** @return string[] ISO codes the provider supports. @throws RateProviderException */
    public function supportedCurrencies(): array;
}
