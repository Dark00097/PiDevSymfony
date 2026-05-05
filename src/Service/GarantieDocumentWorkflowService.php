<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Garantie\DocumentVerificationStatus;

final class GarantieDocumentWorkflowService
{
    public function initialStatus(): string
    {
        return DocumentVerificationStatus::EN_ATTENTE;
    }

    /**
     * @return array<int, string>
     */
    public function allowedStatuses(): array
    {
        return DocumentVerificationStatus::all();
    }

    public function resolveStatus(?string $status): string
    {
        return DocumentVerificationStatus::normalize($status);
    }

    public function canTransition(string $from, string $to): bool
    {
        $from = $this->resolveStatus($from);
        $to = $this->resolveStatus($to);
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            DocumentVerificationStatus::EN_ATTENTE => in_array($to, [
                DocumentVerificationStatus::VALIDE,
                DocumentVerificationStatus::INCOMPLET,
                DocumentVerificationStatus::REJETE,
                DocumentVerificationStatus::SUSPECT,
            ], true),
            DocumentVerificationStatus::INCOMPLET => in_array($to, [
                DocumentVerificationStatus::EN_ATTENTE,
                DocumentVerificationStatus::VALIDE,
                DocumentVerificationStatus::REJETE,
                DocumentVerificationStatus::SUSPECT,
            ], true),
            DocumentVerificationStatus::SUSPECT => in_array($to, [
                DocumentVerificationStatus::EN_ATTENTE,
                DocumentVerificationStatus::VALIDE,
                DocumentVerificationStatus::REJETE,
            ], true),
            DocumentVerificationStatus::VALIDE => in_array($to, [
                DocumentVerificationStatus::SUSPECT,
                DocumentVerificationStatus::REJETE,
            ], true),
            DocumentVerificationStatus::REJETE => $to === DocumentVerificationStatus::EN_ATTENTE,
            default => false,
        };
    }

    public function userMessage(string $status, ?string $adminRemark = null): string
    {
        $status = $this->resolveStatus($status);
        $remark = trim((string) $adminRemark);
        $base = match ($status) {
            DocumentVerificationStatus::VALIDE => 'Le justificatif est valide.',
            DocumentVerificationStatus::INCOMPLET => 'Le justificatif est incomplet. Merci de fournir un document plus clair ou complet.',
            DocumentVerificationStatus::REJETE => 'Le justificatif a ete rejete.',
            DocumentVerificationStatus::SUSPECT => 'Le justificatif est marque suspect et necessite une verification manuelle.',
            default => 'Le justificatif est en attente de verification.',
        };

        if ($remark === '') {
            return $base;
        }

        return $base.' Remarque admin: '.$remark;
    }
}

