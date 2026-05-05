<?php

namespace App\Service;

final class DocumentService
{
    /**
     * @param array<string, mixed>|null $garantie
     * @return array{
     *   status:string,
     *   label:string,
     *   message:string,
     *   has_document:bool,
     *   is_blurry:bool,
     *   is_suspect:bool
     * }
     */
    public function evaluate(?array $garantie): array
    {
        if (!is_array($garantie)) {
            return [
                'status' => 'suspect',
                'label' => 'Suspect',
                'message' => 'Aucune garantie selectionnee pour verifier le document.',
                'has_document' => false,
                'is_blurry' => false,
                'is_suspect' => true,
            ];
        }

        $file = trim((string) ($garantie['documentUrl'] ?? $garantie['documentJustificatif'] ?? ''));
        $status = strtolower(trim((string) ($garantie['statutDocument'] ?? ($garantie['statutVerificationDocument'] ?? 'en_attente'))));
        $feedback = strtolower(trim((string) ($garantie['documentFeedbackMessage'] ?? '')));

        $hasDocument = $file !== '';
        $normalizedFile = $this->normalize($file);
        $hasScreenshotKeyword = str_contains($normalizedFile, 'screenshot')
            || str_contains($normalizedFile, 'capture')
            || str_contains($normalizedFile, 'screen');

        $isBlurry = str_contains($feedback, 'flou')
            || str_contains($feedback, 'blur')
            || str_contains($feedback, 'nettet');

        $isSuspect = !$hasDocument
            || $hasScreenshotKeyword
            || str_contains($status, 'suspect')
            || str_contains($status, 'rejete');

        if ($isSuspect) {
            return [
                'status' => 'suspect',
                'label' => 'Suspect',
                'message' => !$hasDocument
                    ? 'Document manquant: un justificatif officiel est obligatoire.'
                    : ($hasScreenshotKeyword
                        ? 'Capture ecran detectee: document officiel requis.'
                        : 'Document marque suspect. Verification manuelle recommandee.'),
                'has_document' => $hasDocument,
                'is_blurry' => $isBlurry,
                'is_suspect' => true,
            ];
        }

        if ($isBlurry || str_contains($status, 'incomplet') || str_contains($status, 'attente')) {
            return [
                'status' => 'flou',
                'label' => 'Flou',
                'message' => 'Le document existe mais la qualite est insuffisante. Merci de renvoyer une image nette.',
                'has_document' => true,
                'is_blurry' => true,
                'is_suspect' => false,
            ];
        }

        return [
            'status' => 'valide',
            'label' => 'Valide',
            'message' => 'Document present et conforme selon les controles disponibles.',
            'has_document' => true,
            'is_blurry' => false,
            'is_suspect' => false,
        ];
    }

    private function normalize(string $value): string
    {
        $value = strtolower($value);

        return (string) preg_replace('/[^a-z0-9._-]+/', ' ', $value);
    }
}
