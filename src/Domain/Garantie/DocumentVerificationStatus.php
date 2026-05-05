<?php

declare(strict_types=1);

namespace App\Domain\Garantie;

final class DocumentVerificationStatus
{
    public const EN_ATTENTE = 'en_attente';
    public const VALIDE = 'valide';
    public const INCOMPLET = 'incomplet';
    public const REJETE = 'rejete';
    public const SUSPECT = 'suspect';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::EN_ATTENTE,
            self::VALIDE,
            self::INCOMPLET,
            self::REJETE,
            self::SUSPECT,
        ];
    }

    public static function normalize(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::all(), true) ? $normalized : self::EN_ATTENTE;
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::VALIDE => 'Valide',
            self::INCOMPLET => 'Incomplet',
            self::REJETE => 'Rejete',
            self::SUSPECT => 'Suspect',
            default => 'En attente',
        };
    }
}

