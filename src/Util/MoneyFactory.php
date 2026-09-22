<?php

declare(strict_types=1);

namespace Genkgo\Camt\Util;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use SimpleXMLElement;

final class MoneyFactory
{
    private DecimalMoneyParser $decimalMoneyParser;

    public function __construct()
    {
        $this->decimalMoneyParser = new DecimalMoneyParser(new ISOCurrencies());
    }

    public function create(SimpleXMLElement $xmlAmount, ?SimpleXMLElement $CdtDbtInd): Money
    {
        $amount = trim((string) $xmlAmount, " \t\r\n");
        if (str_starts_with($amount, '+')) {
            $amount = substr($amount, 1);
        }

        /** @psalm-var non-empty-string $currency */
        $currency = (string) $xmlAmount->attributes()->Ccy;

        $money = $this->decimalMoneyParser->parse(
            $amount,
            new Currency($currency)
        );

        return (string) $CdtDbtInd === 'DBIT' ? $money->negative() : $money;
    }
}
