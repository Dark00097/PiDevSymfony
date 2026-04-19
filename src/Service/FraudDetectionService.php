<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FraudDetectionService
{
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
    private const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
    private const MAX_UPLOAD_SIZE = 5_242_880; // 5 MB
    private const MIN_FILE_SIZE = 8_192; // 8 KB
    private const MIN_IMAGE_WIDTH = 500;
    private const MIN_IMAGE_HEIGHT = 500;
    private const EXPIRY_DELAY_DAYS = 365;

    public function __construct(
        private readonly Connection $connection,
        private readonly ActivityService $activityService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param array<string, mixed> $garantieData
     * @return array<string, mixed>
     */
    public function analyzeGuarantee(array $garantieData, int $userId): array
    {
        $garantieId = (int) ($garantieData['idGarantie'] ?? 0);
        $creditId = (int) ($garantieData['idCredit'] ?? 0);
        $documentPath = trim((string) ($garantieData['documentJustificatif'] ?? ''));
        $documentName = $this->resolveDisplayDocumentName($garantieData);
        $documentFamily = $this->normalizeDocumentFamily($documentName);
        $nomGarant = trim((string) ($garantieData['nomGarant'] ?? ''));
        $evaluationDate = trim((string) ($garantieData['dateEvaluation'] ?? ''));

        $userName = $this->fetchUserFullName($userId);
        $documentExtension = strtolower(pathinfo($documentName !== '' ? $documentName : $documentPath, PATHINFO_EXTENSION));
        $fileInfo = $this->inspectStoredDocument($documentPath);
        $duplicateCount = $this->countDuplicateDocuments($userId, $documentPath, $documentFamily, $fileInfo['file_hash'], $garantieId);
        $versionCount = $this->countDocumentVersions($userId, $creditId, $documentFamily, $garantieId);
        $recentAttempts = $this->countRecentUploadAttempts($userId, $garantieId, 7);
        $isExpired = $this->isDocumentExpired($evaluationDate);

        $score = 5;
        $reasons = [];
        $signals = [];

        if ($documentPath === '') {
            $score += 35;
            $reasons[] = 'Aucun document justificatif n a ete fourni.';
            $signals[] = 'missing_document';
        }

        if ($documentExtension !== '' && !in_array($documentExtension, self::ALLOWED_EXTENSIONS, true)) {
            $score += 24;
            $reasons[] = 'Format de document non pris en charge.';
            $signals[] = 'unsupported_extension';
        }

        if ($documentName !== '' && preg_match('/(?:fake|edited|modify|test|copy|scan|duplicata|duplicate|final[0-9]*|tmp|draft|whatsapp)/i', $documentName) === 1) {
            $score += 18;
            $reasons[] = 'Nom de fichier suspect ou peu fiable.';
            $signals[] = 'suspicious_filename';
        }

        if ($documentPath !== '') {
            if (!$fileInfo['exists']) {
                $score += 28;
                $reasons[] = 'Document introuvable apres televersement.';
                $signals[] = 'missing_stored_file';
            } elseif (!$fileInfo['readable']) {
                $score += 28;
                $reasons[] = 'Document illisible ou endommage.';
                $signals[] = 'unreadable_file';
            } else {
                if ($fileInfo['size_bytes'] > self::MAX_UPLOAD_SIZE) {
                    $score += 12;
                    $reasons[] = 'Fichier anormalement volumineux.';
                    $signals[] = 'oversized_file';
                }

                if ($fileInfo['size_bytes'] > 0 && $fileInfo['size_bytes'] < self::MIN_FILE_SIZE) {
                    $score += 16;
                    $reasons[] = 'Image potentiellement illisible ou trop compressee.';
                    $signals[] = 'tiny_file';
                }

                if (
                    $fileInfo['mime_type'] !== ''
                    && !in_array($fileInfo['mime_type'], self::ALLOWED_MIME_TYPES, true)
                ) {
                    $score += 22;
                    $reasons[] = 'Type MIME suspect pour un justificatif de garantie.';
                    $signals[] = 'mime_mismatch';
                }

                if ($fileInfo['is_image']) {
                    if (
                        $fileInfo['width'] > 0
                        && $fileInfo['height'] > 0
                        && ($fileInfo['width'] < self::MIN_IMAGE_WIDTH || $fileInfo['height'] < self::MIN_IMAGE_HEIGHT)
                    ) {
                        $score += 22;
                        $reasons[] = 'Resolution trop faible, document possiblement illisible.';
                        $signals[] = 'low_resolution';
                    }
                } else {
                    $score += 24;
                    $reasons[] = 'Le fichier televerse ne ressemble pas a une image exploitable.';
                    $signals[] = 'non_image_document';
                }
            }
        }

        if ($isExpired) {
            $score += 22;
            $reasons[] = 'Document expire ou trop ancien.';
            $signals[] = 'expired_document';
        }

        if ($duplicateCount > 0) {
            $score += 20;
            $reasons[] = 'Document doublon detecte.';
            $signals[] = 'duplicate_document';
        }

        if ($versionCount > 1) {
            $score += 15;
            $reasons[] = 'Versions multiples du meme document detectees.';
            $signals[] = 'multiple_versions';
        }

        if ($recentAttempts >= 3) {
            $score += 18;
            $reasons[] = 'Trop de tentatives d upload recentes sur cette garantie.';
            $signals[] = 'too_many_attempts';
        }

        if ($nomGarant !== '' && $userName !== '' && !$this->isNameConsistent($nomGarant, $userName)) {
            $score += 20;
            $reasons[] = 'Nom incoherent avec le client.';
            $signals[] = 'name_mismatch';
        }

        $score = max(0, min(100, $score));
        [$level, $status] = $this->classifyScore($score);

        return $this->decorateAnalysis([
            'garantie_id' => $garantieId,
            'credit_id' => $creditId,
            'score' => $score,
            'level' => $level,
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
            'document_name' => $documentName,
            'document_family' => $documentFamily,
            'file_hash' => $fileInfo['file_hash'],
            'signals' => array_values(array_unique($signals)),
            'meta' => [
                'duplicate_count' => $duplicateCount,
                'version_count' => $versionCount,
                'recent_attempts' => $recentAttempts,
                'mime_type' => $fileInfo['mime_type'],
                'size_bytes' => $fileInfo['size_bytes'],
                'width' => $fileInfo['width'],
                'height' => $fileInfo['height'],
                'is_image' => $fileInfo['is_image'],
                'ocr_ready' => true,
                'external_api_ready' => true,
            ],
            'ocr_payload' => $this->prepareExternalOcrPayload($garantieData),
        ]);
    }

    /**
     * @param array<string, mixed> $analysis
     */
    public function recordGuaranteeAnalysis(int $userId, int $garantieId, string $documentName, array $analysis): void
    {
        if ($userId <= 0 || $garantieId <= 0 || $analysis === []) {
            return;
        }

        $payload = [
            'garantie_id' => $garantieId,
            'credit_id' => (int) ($analysis['credit_id'] ?? 0),
            'document_name' => trim($documentName),
            'document_family' => trim((string) ($analysis['document_family'] ?? '')),
            'file_hash' => trim((string) ($analysis['file_hash'] ?? '')),
            'score' => (int) ($analysis['score'] ?? 0),
            'level' => (string) ($analysis['level'] ?? ''),
            'status' => (string) ($analysis['status'] ?? ''),
            'reasons' => is_array($analysis['reasons'] ?? null) ? $analysis['reasons'] : [],
            'signals' => is_array($analysis['signals'] ?? null) ? $analysis['signals'] : [],
            'meta' => is_array($analysis['meta'] ?? null) ? $analysis['meta'] : [],
            'ocr_payload' => is_array($analysis['ocr_payload'] ?? null) ? $analysis['ocr_payload'] : [],
        ];

        $this->activityService->log(
            $userId,
            'FRAUD_ANALYSIS',
            'Garantie fraud detection',
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @param int[] $garantieIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function loadGuaranteeFraudHistory(int $userId, array $garantieIds = []): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM user_activity_log WHERE idUser = ? AND action_type = ? ORDER BY created_at DESC',
            [$userId, 'FRAUD_ANALYSIS']
        );

        $history = [];
        foreach ($rows as $row) {
            $details = json_decode((string) ($row['details'] ?? ''), true);
            if (!is_array($details)) {
                continue;
            }

            $entryGarantieId = (int) ($details['garantie_id'] ?? 0);
            if ($entryGarantieId <= 0 || ($garantieIds !== [] && !in_array($entryGarantieId, $garantieIds, true))) {
                continue;
            }

            $entry = $this->decorateAnalysis([
                'garantie_id' => $entryGarantieId,
                'credit_id' => (int) ($details['credit_id'] ?? 0),
                'score' => (int) ($details['score'] ?? 0),
                'level' => (string) ($details['level'] ?? ''),
                'status' => (string) ($details['status'] ?? ''),
                'reasons' => is_array($details['reasons'] ?? null) ? $details['reasons'] : [],
                'signals' => is_array($details['signals'] ?? null) ? $details['signals'] : [],
                'document_name' => (string) ($details['document_name'] ?? ''),
                'document_family' => (string) ($details['document_family'] ?? ''),
                'file_hash' => (string) ($details['file_hash'] ?? ''),
                'meta' => is_array($details['meta'] ?? null) ? $details['meta'] : [],
                'ocr_payload' => is_array($details['ocr_payload'] ?? null) ? $details['ocr_payload'] : [],
            ]);
            $entry['created_at'] = $row['created_at'] ?? null;
            $entry['created_at_label'] = $this->formatHistoryDate($row['created_at'] ?? null);

            $history[$entryGarantieId][] = $entry;
        }

        return $history;
    }

    /**
     * @param array<string, mixed> $garantieData
     * @return array<string, mixed>
     */
    public function prepareExternalOcrPayload(array $garantieData): array
    {
        return [
            'document_path' => trim((string) ($garantieData['documentJustificatif'] ?? '')),
            'original_name' => $this->resolveDisplayDocumentName($garantieData),
            'garantie_id' => (int) ($garantieData['idGarantie'] ?? 0),
            'credit_id' => (int) ($garantieData['idCredit'] ?? 0),
            'nom_garant' => trim((string) ($garantieData['nomGarant'] ?? '')),
            'date_evaluation' => trim((string) ($garantieData['dateEvaluation'] ?? '')),
            'requested_checks' => [
                'name_match',
                'expiry_check',
                'document_consistency',
                'tampering_signals',
                'readability_check',
            ],
            'provider' => 'pending',
            'comment' => 'Payload pret pour une future integration OCR ou API externe.',
        ];
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    private function decorateAnalysis(array $analysis): array
    {
        $score = max(0, min(100, (int) ($analysis['score'] ?? 0)));
        [$defaultLevel, $defaultStatus] = $this->classifyScore($score);

        $levelKey = $this->normalizeLevel((string) ($analysis['level_key'] ?? $analysis['level'] ?? $defaultLevel));
        $statusKey = $this->normalizeStatus((string) ($analysis['status_key'] ?? $analysis['status'] ?? $defaultStatus));

        return array_replace($analysis, [
            'score' => $score,
            'level_key' => $levelKey,
            'status_key' => $statusKey,
            'level' => $this->displayLevel($levelKey),
            'status' => $this->displayStatus($statusKey),
            'badge_class' => $this->getBadgeClass($statusKey),
            'score_badge_class' => $this->getScoreBadgeClass($score),
            'level_badge_class' => $this->getLevelBadgeClass($levelKey),
            'status_badge_class' => $this->getBadgeClass($statusKey),
        ]);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function classifyScore(int $score): array
    {
        if ($score <= 24) {
            return ['faible', 'valide'];
        }

        if ($score <= 49) {
            return ['moyen', 'a verifier'];
        }

        if ($score <= 79) {
            return ['eleve', 'suspect'];
        }

        return ['eleve', 'rejete'];
    }

    private function fetchUserFullName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $user = $this->connection->fetchAssociative(
            'SELECT nom, prenom FROM users WHERE idUser = ? LIMIT 1',
            [$userId]
        );
        if ($user === false) {
            return '';
        }

        return trim((string) ($user['prenom'] ?? '').' '.(string) ($user['nom'] ?? ''));
    }

    private function isDocumentExpired(string $dateValue): bool
    {
        if ($dateValue === '') {
            return false;
        }

        try {
            $date = new \DateTimeImmutable($dateValue);
        } catch (\Throwable) {
            return false;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d days', self::EXPIRY_DELAY_DAYS));

        return $date < $threshold;
    }

    private function isNameConsistent(string $nomGarant, string $userFullName): bool
    {
        if ($userFullName === '') {
            return true;
        }

        $normalizedGarant = $this->normalizeName($nomGarant);
        $normalizedUser = $this->normalizeName($userFullName);

        if ($normalizedGarant === '' || $normalizedUser === '') {
            return true;
        }

        return str_contains($normalizedUser, $normalizedGarant) || str_contains($normalizedGarant, $normalizedUser);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/[^\p{L}0-9]+/u', ' ', $name) ?? ''), 'UTF-8');
    }

    private function resolveDisplayDocumentName(array $garantieData): string
    {
        $originalName = trim((string) ($garantieData['uploadedOriginalName'] ?? ''));
        if ($originalName !== '') {
            return $originalName;
        }

        $storedPath = trim((string) ($garantieData['documentJustificatif'] ?? ''));

        return $storedPath !== '' ? basename($storedPath) : '';
    }

    private function normalizeDocumentFamily(string $documentName): string
    {
        $baseName = pathinfo($documentName, PATHINFO_FILENAME);
        $normalized = mb_strtolower($baseName, 'UTF-8');
        $normalized = preg_replace('/(?:garantie[_-]?doc[_-]?)?[a-f0-9]{8,}/i', ' ', $normalized) ?? '';
        $normalized = preg_replace('/(?:_?v(?:ersion)?[0-9]+|_?final[0-9]*|_?copy|_?scan|_?edited|_?duplicate)/i', ' ', $normalized) ?? '';
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    /**
     * @return array{absolute_path:string, exists:bool, readable:bool, size_bytes:int, mime_type:string, width:int, height:int, is_image:bool, file_hash:string}
     */
    private function inspectStoredDocument(string $documentPath): array
    {
        $default = [
            'absolute_path' => '',
            'exists' => false,
            'readable' => false,
            'size_bytes' => 0,
            'mime_type' => '',
            'width' => 0,
            'height' => 0,
            'is_image' => false,
            'file_hash' => '',
        ];

        $absolutePath = $this->resolveDocumentAbsolutePath($documentPath);
        if ($absolutePath === '') {
            return $default;
        }

        $default['absolute_path'] = $absolutePath;
        $default['exists'] = is_file($absolutePath);
        $default['readable'] = is_readable($absolutePath);

        if (!$default['exists'] || !$default['readable']) {
            return $default;
        }

        $default['size_bytes'] = (int) (filesize($absolutePath) ?: 0);
        $default['mime_type'] = (string) (mime_content_type($absolutePath) ?: '');
        $default['file_hash'] = (string) (sha1_file($absolutePath) ?: '');

        $imageSize = @getimagesize($absolutePath);
        if (is_array($imageSize)) {
            $default['width'] = (int) ($imageSize[0] ?? 0);
            $default['height'] = (int) ($imageSize[1] ?? 0);
            $default['is_image'] = true;
        } elseif ($default['mime_type'] === 'image/svg+xml' || str_ends_with(strtolower($absolutePath), '.svg')) {
            $default['is_image'] = true;
        }

        return $default;
    }

    private function resolveDocumentAbsolutePath(string $documentPath): string
    {
        $normalizedPath = trim(str_replace('\\', '/', $documentPath));
        if ($normalizedPath === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:\//', $normalizedPath) === 1 && str_starts_with($normalizedPath, str_replace('\\', '/', $this->projectDir))) {
            return $normalizedPath;
        }

        $normalizedPath = ltrim($normalizedPath, '/');

        return $this->projectDir.'/public/'.$normalizedPath;
    }

    private function countDuplicateDocuments(
        int $userId,
        string $documentPath,
        string $documentFamily,
        string $fileHash,
        int $currentGarantieId
    ): int {
        if ($userId <= 0) {
            return 0;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT idGarantie, documentJustificatif FROM garantiecredit WHERE idUser = ? AND idGarantie <> ?',
            [$userId, $currentGarantieId]
        );

        $duplicates = 0;
        foreach ($rows as $row) {
            $storedPath = trim((string) ($row['documentJustificatif'] ?? ''));
            if ($storedPath === '') {
                continue;
            }

            if ($documentPath !== '' && $storedPath === $documentPath) {
                ++$duplicates;
                continue;
            }

            if ($fileHash !== '') {
                $storedHash = $this->inspectStoredDocument($storedPath)['file_hash'];
                if ($storedHash !== '' && hash_equals($fileHash, $storedHash)) {
                    ++$duplicates;
                    continue;
                }
            }

            $storedFamily = $this->normalizeDocumentFamily(basename($storedPath));
            if ($documentFamily !== '' && $storedFamily !== '' && $storedFamily === $documentFamily) {
                ++$duplicates;
            }
        }

        return $duplicates;
    }

    private function countDocumentVersions(int $userId, int $creditId, string $documentFamily, int $currentGarantieId): int
    {
        if ($userId <= 0 || $documentFamily === '') {
            return 0;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT details FROM user_activity_log WHERE idUser = ? AND action_type = ? ORDER BY created_at DESC',
            [$userId, 'FRAUD_ANALYSIS']
        );

        $count = 0;
        foreach ($rows as $row) {
            $details = json_decode((string) ($row['details'] ?? ''), true);
            if (!is_array($details)) {
                continue;
            }

            if ((int) ($details['garantie_id'] ?? 0) === $currentGarantieId) {
                continue;
            }

            $loggedCreditId = (int) ($details['credit_id'] ?? 0);
            if ($creditId > 0 && $loggedCreditId !== $creditId) {
                continue;
            }

            $loggedFamily = trim((string) ($details['document_family'] ?? ''));
            if ($loggedFamily !== '' && $loggedFamily === $documentFamily) {
                ++$count;
            }
        }

        return $count;
    }

    private function countRecentUploadAttempts(int $userId, int $garantieId, int $days): int
    {
        if ($userId <= 0 || $garantieId <= 0) {
            return 0;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM user_activity_log
             WHERE idUser = ?
               AND action_type = ?
               AND details LIKE ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$userId, 'FRAUD_ANALYSIS', '%"garantie_id":'.$garantieId.'%', $days]
        );
    }

    private function normalizeLevel(string $level): string
    {
        $normalized = $this->normalizeToken($level);

        return match ($normalized) {
            'faible' => 'faible',
            'moyen' => 'moyen',
            'eleve', 'elevee', 'high' => 'eleve',
            default => 'faible',
        };
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = $this->normalizeToken($status);

        return match ($normalized) {
            'valide', 'valid' => 'valide',
            'a verifier', 'a_verifier', 'averifier', 'review', 'pending_review' => 'a verifier',
            'suspect', 'suspicious' => 'suspect',
            'rejete', 'rejected', 'refuse' => 'rejete',
            default => 'valide',
        };
    }

    private function normalizeToken(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return strtr($normalized, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
    }

    private function displayLevel(string $levelKey): string
    {
        return match ($levelKey) {
            'faible' => 'faible',
            'moyen' => 'moyen',
            'eleve' => 'eleve',
            default => 'faible',
        };
    }

    private function displayStatus(string $statusKey): string
    {
        return match ($statusKey) {
            'valide' => 'valide',
            'a verifier' => 'a verifier',
            'suspect' => 'suspect',
            'rejete' => 'rejete',
            default => 'valide',
        };
    }

    private function getBadgeClass(string $status): string
    {
        return match ($status) {
            'valide' => 'fraud-badge--success',
            'a verifier' => 'fraud-badge--warning',
            'suspect' => 'fraud-badge--danger',
            'rejete' => 'fraud-badge--dark',
            default => 'fraud-badge--neutral',
        };
    }

    private function getLevelBadgeClass(string $level): string
    {
        return match ($level) {
            'faible' => 'fraud-badge--success-soft',
            'moyen' => 'fraud-badge--warning-soft',
            'eleve' => 'fraud-badge--danger-soft',
            default => 'fraud-badge--neutral',
        };
    }

    private function getScoreBadgeClass(int $score): string
    {
        if ($score <= 24) {
            return 'fraud-badge--success-soft';
        }

        if ($score <= 49) {
            return 'fraud-badge--warning-soft';
        }

        if ($score <= 79) {
            return 'fraud-badge--danger-soft';
        }

        return 'fraud-badge--dark';
    }

    private function formatHistoryDate(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return 'Analyse recente';
        }

        try {
            return (new \DateTimeImmutable($value))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return 'Analyse recente';
        }
    }
}
