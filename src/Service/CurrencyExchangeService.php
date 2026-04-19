<?php

namespace App\Service;

use Swap\Builder;
use Swap\Swap;

final class CurrencyExchangeService
{
    private Swap $swap;
    
    // Devises supportées
    public const SUPPORTED_CURRENCIES = ['TND', 'EUR', 'USD', 'GBP'];
    
    // Symboles des devises
    public const CURRENCY_SYMBOLS = [
        'TND' => 'DT',
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
    ];
    
    // Noms complets des devises
    public const CURRENCY_NAMES = [
        'TND' => 'Dinar Tunisien',
        'EUR' => 'Euro',
        'USD' => 'Dollar Américain',
        'GBP' => 'Livre Sterling',
    ];

    public function __construct(
        private readonly string $exchangeRateApiKey = '',
    ) {
        $this->initializeSwap();
    }

    private function initializeSwap(): void
    {
        // Utiliser plusieurs sources pour plus de fiabilité
        $builder = new Builder();
        
        // Source 1: Fixer.io (nécessite une clé API)
        if ($this->exchangeRateApiKey !== '' && !str_contains($this->exchangeRateApiKey, 'YOUR_API_KEY')) {
            try {
                $builder->add('fixer', ['api_key' => $this->exchangeRateApiKey]);
            } catch (\Exception $e) {
                error_log('CurrencyExchangeService: Fixer.io non disponible: ' . $e->getMessage());
            }
        }
        
        // Source 2: European Central Bank (gratuit, pas de clé nécessaire)
        try {
            $builder->add('european_central_bank');
        } catch (\Exception $e) {
            error_log('CurrencyExchangeService: ECB non disponible: ' . $e->getMessage());
        }
        
        $this->swap = $builder->build();
    }

    /**
     * Obtient le taux de change entre deux devises
     * 
     * @param string $from Devise source (ex: 'EUR')
     * @param string $to Devise cible (ex: 'TND')
     * @return float Taux de change
     */
    public function getExchangeRate(string $from, string $to): float
    {
        // Si même devise, retourner 1
        if ($from === $to) {
            return 1.0;
        }
        
        try {
            $rate = $this->swap->latest("{$from}/{$to}");
            $value = (float) $rate->getValue();
            
            // Vérifier que le taux est valide
            if ($value > 0) {
                return $value;
            }
        } catch (\Exception $e) {
            error_log("CurrencyExchangeService: Erreur API pour {$from}/{$to}: " . $e->getMessage());
        }
        
        // Utiliser le taux de secours
        return $this->getFallbackRate($from, $to);
    }

    /**
     * Convertit un montant d'une devise à une autre
     * 
     * @param float $amount Montant à convertir
     * @param string $from Devise source
     * @param string $to Devise cible
     * @return float Montant converti
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($amount <= 0) {
            return 0.0;
        }
        
        $rate = $this->getExchangeRate($from, $to);
        return round($amount * $rate, 3);
    }

    /**
     * Calcule les frais de conversion (0.5% du montant)
     * 
     * @param float $amount Montant
     * @param string $from Devise source
     * @param string $to Devise cible
     * @return float Frais de conversion
     */
    public function calculateConversionFee(float $amount, string $from, string $to): float
    {
        // Pas de frais si même devise
        if ($from === $to) {
            return 0.0;
        }
        
        // Frais de 0.5%
        return round($amount * 0.005, 3);
    }

    /**
     * Obtient toutes les informations de conversion
     * 
     * @param float $amount Montant
     * @param string $from Devise source
     * @param string $to Devise cible
     * @return array{amount: float, from: string, to: string, rate: float, converted: float, fee: float, total: float}
     */
    public function getConversionDetails(float $amount, string $from, string $to): array
    {
        $rate = $this->getExchangeRate($from, $to);
        $converted = $this->convert($amount, $from, $to);
        $fee = $this->calculateConversionFee($amount, $from, $to);
        $total = $converted + $fee;
        
        return [
            'amount' => $amount,
            'from' => $from,
            'to' => $to,
            'rate' => $rate,
            'converted' => $converted,
            'fee' => $fee,
            'total' => $total,
            'from_symbol' => self::CURRENCY_SYMBOLS[$from] ?? $from,
            'to_symbol' => self::CURRENCY_SYMBOLS[$to] ?? $to,
            'from_name' => self::CURRENCY_NAMES[$from] ?? $from,
            'to_name' => self::CURRENCY_NAMES[$to] ?? $to,
        ];
    }

    /**
     * Obtient tous les taux de change pour une devise de base
     * 
     * @param string $baseCurrency Devise de base
     * @return array<string, float>
     */
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

    /**
     * Vérifie si une devise est supportée
     */
    public function isCurrencySupported(string $currency): bool
    {
        return in_array(strtoupper($currency), self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Obtient le symbole d'une devise
     */
    public function getCurrencySymbol(string $currency): string
    {
        return self::CURRENCY_SYMBOLS[$currency] ?? $currency;
    }

    /**
     * Obtient le nom complet d'une devise
     */
    public function getCurrencyName(string $currency): string
    {
        return self::CURRENCY_NAMES[$currency] ?? $currency;
    }

    /**
     * Taux de secours en cas d'échec de l'API
     */
    private function getFallbackRate(string $from, string $to): float
    {
        $fallbackRates = [
            'EUR/TND' => 3.30,
            'TND/EUR' => 0.303,
            'USD/TND' => 3.10,
            'TND/USD' => 0.323,
            'GBP/TND' => 3.90,
            'TND/GBP' => 0.256,
            'EUR/USD' => 1.08,
            'USD/EUR' => 0.926,
            'GBP/EUR' => 1.18,
            'EUR/GBP' => 0.847,
            'GBP/USD' => 1.27,
            'USD/GBP' => 0.787,
        ];
        
        $key = "{$from}/{$to}";
        return $fallbackRates[$key] ?? 1.0;
    }

    /**
     * Formate un montant avec sa devise
     */
    public function formatAmount(float $amount, string $currency): string
    {
        $symbol = $this->getCurrencySymbol($currency);
        
        // Format selon la devise
        if ($currency === 'TND') {
            return number_format($amount, 3, '.', ' ') . ' ' . $symbol;
        }
        
        return number_format($amount, 2, '.', ' ') . ' ' . $symbol;
    }
}
