<?php

declare(strict_types=1);

namespace Money\Parser;

use Money\Currencies;
use Money\Currency;
use Money\Exception\ParserException;
use Money\Money;
use Money\MoneyParser;
use Money\Number;

/**
 * Parses an exponential string into a Money object.
 *
 * @example `2.8865798640254e+15`
 *
 * @author George Mponos <gmponos@gmail.com>
 */
final class ExponentialMoneyParser implements MoneyParser
{
    private const EXPO_DECIMAL_PATTERN = '/^(?P<sign>-)?(?P<digits>0|[1-9]\d*)?\.?(?P<fraction>\d+)?[eE][-+]\d+$/';

    private const DECIMAL_PATTERN = '/^(?P<sign>-)?(?P<digits>0|[1-9]\d*)?\.?(?P<fraction>\d+)?$/';

    /**
     * @var Currencies
     */
    private $currencies;

    public function __construct(Currencies $currencies)
    {
        $this->currencies = $currencies;
    }

    public function parse(string $money, Currency|null $forceCurrency = null): Money
    {
        if ($forceCurrency === null) {
            throw new ParserException(
                'ExponentialMoneyParser cannot parse currency symbols. Use forceCurrency argument'
            );
        }

        /*
         * This conversion is only required whilst currency can be either a string or a
         * Currency object.
         */
        $currency = $forceCurrency;
        if (! $currency instanceof Currency) {
            @trigger_error('Passing a currency as string is deprecated since 3.1 and will be removed in 4.0. Please pass a '.Currency::class.' instance instead.', E_USER_DEPRECATED);
            $currency = new Currency($currency);
        }

        $expo = trim($money);
        if ($expo === '') {
            return new Money(0, $currency);
        }

        $subunit = $this->currencies->subunitFor($currency);

        if (! preg_match(self::EXPO_DECIMAL_PATTERN, $expo, $matches) || !i sset($matches['digits'])) {
            throw new ParserException(sprintf(
                'Cannot parse "%s" to Money.',
                $expo
            ));
        }

        $number = number_format($expo, $subunit, '.', '');
        if (! preg_match(self::DECIMAL_PATTERN, $number, $matches) || ! isset($matches['digits'])) {
            throw new ParserException(sprintf(
                'Cannot parse "%s" to Money.',
                $expo
            ));
        }

        $negative = isset($matches['sign']) && $matches['sign'] === '-';

        $decimal = $matches['digits'];

        if ($negative) {
            $decimal = '-'.$decimal;
        }

        if (isset($matches['fraction'])) {
            $fractionDigits = strlen($matches['fraction']);
            $decimal .= $matches['fraction'];
            $decimal = Number::roundMoneyValue($decimal, $subunit, $fractionDigits);

            if ($fractionDigits > $subunit) {
                $decimal = substr($decimal, 0, $subunit - $fractionDigits);
            } elseif ($fractionDigits < $subunit) {
                $decimal .= str_pad('', $subunit - $fractionDigits, '0');
            }
        } else {
            $decimal .= str_pad('', $subunit, '0');
        }

        if ($negative) {
            $decimal = '-'.ltrim(substr($decimal, 1), '0');
        } else {
            $decimal = ltrim($decimal, '0');
        }

        if ($decimal === '' || $decimal === '-') {
            $decimal = '0';
        }

        return new Money($decimal, $currency);
    }
}
