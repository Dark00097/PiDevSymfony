<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CurrencyExchangeService
{
    public const SUPPORTED_CURRENCIES = ['TND', 'EUR', 'USD', 'GBP'];

    public const CURRENCY_SYMBOLS = [
        'TND' => 'DT',
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
    ];

    public const CURRENCY_NAMES = [
        'TND' => 'Dinar Tunisien',
        'EUR' => 'Euro',
        'USD' => 'Dollar Américain',
        'GBP' => 'Livre Sterling',
    ];

    private const FALLBACK_RATES = [
        'EUR/TND' => 3.30,  'TND/EUR' => 0.3030,
        'USD/TND' => 3.10,  'TND/USD' => 0.3226,
        'GBP/TND' => 3.90,  'TND/GBP' => 0.2564,
        'EUR/USD' => 1.08,  'USD/EUR' => 0.9259,
        'GBP/EUR' => 1.18,  'EUR/GBP' => 0.8475,
        'GBP/USD' => 1.27,  'USD/GBP' => 0.7874,
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $exchangeRateApiKey = '',
    ) {
    }

    public function getExchangeRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                "https://open.er-api.com/v6/latest/{$from}",
                ['timeout' => 5, 'verify_peer' => false, 'verify_host' => false]
            );
            if ($response->getStatusCode() === 200) {
                $data = $response->toArray(false);
                $rate = (float) ($data['rates'][$to] ?? 0);
                if ($rate > 0) {
                    return $rate;
                }
            }
        } catch (\Throwable) {
            // silencieux — utiliser le fallback
        }

        return $this->getFallbackRate($from, $to);
    }

    public function convert(float $amount, string $from, string $to): float
    {
        if ($amount <= 0) {
            return 0.0;
        }
        return round($amount * $this->getExchangeRate($from, $to), 3);
    }

    public function calculateConversionFee(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return 0.0;
        }
        return round($amount * 0.005, 3);
    }

    public function getConversionDetails(float $amount, string $from, string $to): array
    {
        $rate      = $this->getExchangeRate($from, $to);
        $converted = round($amount * $rate, 3);
        $fee       = $this->calculateConversionFee($amount, $from, $to);

        return [
            'amount'      => $amount,
            'from'        => $from,
            'to'          => $to,
            'rate'        => $rate,
            'converted'   => $converted,
            'fee'         => $fee,
            'total'       => round($converted + $fee, 3),
            'from_symbol' => self::CURRENCY_SYMBOLS[$from] ?? $from,
            'to_symbol'   => self::CURRENCY_SYMBOLS[$to]   ?? $to,
            'from_name'   => self::CURRENCY_NAMES[$from]   ?? $from,
            'to_name'     => self::CURRENCY_NAMES[$to]     ?? $to,
        ];
    }

    public function getAllRates(string $baseCurrency = 'TND'): array
    {
        $rates = [];
        foreach (self::SUPPORTED_CURRENCIES as $currency) {
            if ($currency !== $baseCurrency) {
                $rates[$currency] = $this->getExchangeRate($baseCurrency, $currency);
            }
        }
        return $rates;
    }

    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), self::SUPPORTED_CURRENCIES, true);
    }

    public function getCurrencySymbol(string $currency): string
    {
        return self::CURRENCY_SYMBOLS[$currency] ?? $currency;
    }

    public function getCurrencyName(string $currency): string
    {
        return self::CURRENCY_NAMES[$currency] ?? $currency;
    }

    public function formatAmount(float $amount, string $currency): string
    {
        $symbol = $this->getCurrencySymbol($currency);
        return $currency === 'TND'
            ? number_format($amount, 3, '.', ' ') . ' ' . $symbol
            : number_format($amount, 2, '.', ' ') . ' ' . $symbol;
    }

    private function getFallbackRate(string $from, string $to): float
    {
        return self::FALLBACK_RATES["{$from}/{$to}"] ?? 1.0;
    }
}
