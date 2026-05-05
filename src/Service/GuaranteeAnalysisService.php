<?php

namespace App\Service;

final class GuaranteeAnalysisService
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $type = trim((string) ($data['guarantee_type'] ?? ''));
        $creditAmount = $this->toFloat($data['credit_amount'] ?? 0);
        $guaranteeValue = $this->toFloat($data['guarantee_value'] ?? 0);
        $docs = is_array($data['documents'] ?? null) ? $data['documents'] : [];

        $ratio = $creditAmount > 0 ? $guaranteeValue / $creditAmount : 0.0;
        $coveragePercent = $ratio * 100;

        $strength = 'Weak';
        if ($coveragePercent >= 120) {
            $strength = 'Strong';
        } elseif ($coveragePercent >= 80) {
            $strength = 'Moderate';
        }

        $required = $this->requiredDocumentsForType($type);
        $providedNormalized = array_map(static fn ($d) => strtolower(trim((string) $d)), $docs);
        $missing = [];
        foreach ($required as $requiredDoc) {
            if (!in_array(strtolower($requiredDoc), $providedNormalized, true)) {
                $missing[] = $requiredDoc;
            }
        }

        $legalCompleteness = $missing === [] ? 'Complete' : 'Incomplete';

        return [
            'guarantee_type' => $type !== '' ? $type : 'Not specified',
            'coverage_ratio' => round($ratio, 2),
            'coverage_percent' => round($coveragePercent, 1),
            'guarantee_strength' => $strength,
            'legal_completeness' => $legalCompleteness,
            'missing_documents' => $missing,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function requiredDocumentsForType(string $type): array
    {
        $key = strtolower(trim($type));
        return match ($key) {
            'real estate' => ['title deed', 'valuation report', 'owner id'],
            'vehicle' => ['registration card', 'insurance certificate', 'owner id'],
            'deposit' => ['deposit certificate', 'bank statement'],
            'guarantor' => ['guarantor id', 'income proof', 'signed commitment'],
            default => ['identity proof', 'supporting ownership document'],
        };
    }

    private function toFloat(mixed $value): float
    {
        $raw = str_replace(',', '.', trim((string) $value));
        if ($raw === '' || !is_numeric($raw)) {
            return 0.0;
        }
        return (float) $raw;
    }
}

