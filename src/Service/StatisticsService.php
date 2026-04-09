<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class StatisticsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AdminSecuritySettingsService $adminSecuritySettingsService,
        private readonly ActivityService $activityService,
    ) {
    }

    public function getAdminStatistics(): array
    {
        return [
            'user_management' => $this->buildUserManagementStats(),
            'admin_security' => $this->buildAdminSecurityStats(),
            'credit_management' => $this->buildCreditStats(),
        ];
    }

    private function buildUserManagementStats(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM users ORDER BY created_at DESC, idUser DESC');
        $statusCounts = [
            'ACTIVE' => 0,
            'PENDING' => 0,
            'DECLINED' => 0,
            'BANNED' => 0,
            'OTHER' => 0,
        ];
        foreach ($rows as $row) {
            $status = strtoupper(trim((string) ($row['status'] ?? 'OTHER')));
            if (!array_key_exists($status, $statusCounts)) {
                $status = 'OTHER';
            }
            ++$statusCounts[$status];
        }

        return [
            'headline' => [
                ['label' => 'Total users', 'value' => count($rows)],
                ['label' => 'Active users', 'value' => $statusCounts['ACTIVE']],
                ['label' => 'Pending users', 'value' => $statusCounts['PENDING']],
                ['label' => 'Admins', 'value' => count(array_filter($rows, static fn (array $row): bool => strtoupper((string) ($row['role'] ?? '')) === 'ROLE_ADMIN'))],
            ],
            'bars' => $this->buildBars($statusCounts),
            'recent' => array_map(
                static fn (array $row): array => [
                    'title' => trim(sprintf('%s %s', $row['prenom'] ?? '', $row['nom'] ?? '')),
                    'meta' => sprintf('%s | %s', $row['email'] ?? '-', $row['status'] ?? '-'),
                ],
                array_slice($rows, 0, 8)
            ),
        ];
    }

    private function buildAdminSecurityStats(): array
    {
        $settings = $this->adminSecuritySettingsService->getSettings();
        $admins = $this->connection->fetchAllAssociative("SELECT * FROM users WHERE role = 'ROLE_ADMIN' ORDER BY idUser DESC");
        $controls = [
            'Biometric admin login' => $settings['require_biometric_on_admin_login'] ? 100 : 0,
            'Biometric sensitive actions' => $settings['require_biometric_on_sensitive_actions'] ? 100 : 0,
            'Email OTP' => $settings['enable_email_otp'] ? 100 : 0,
        ];
        $score = (int) round(array_sum($controls) / max(1, count($controls)));

        $recent = [];
        foreach ($admins as $admin) {
            $recent[] = [
                'title' => trim(sprintf('%s %s', $admin['prenom'] ?? '', $admin['nom'] ?? '')),
                'meta' => sprintf('Last online: %s', $admin['last_online_at'] ?? 'Never'),
            ];
        }

        return [
            'headline' => [
                ['label' => 'Security score', 'value' => $score.'%'],
                ['label' => 'Admins', 'value' => count($admins)],
                ['label' => 'Biometric-enabled profiles', 'value' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE biometric_enabled = 1')],
                ['label' => 'Settings file', 'value' => basename($this->adminSecuritySettingsService->getStoragePath())],
            ],
            'bars' => $this->buildBars($controls),
            'recent' => $recent !== [] ? $recent : [['title' => 'No admin profiles', 'meta' => '']],
            'settings' => $settings,
        ];
    }

    private function buildCreditStats(): array
    {
        $credits = $this->connection->fetchAllAssociative('SELECT * FROM credit ORDER BY idCredit DESC');
        $statusCounts = [];
        $totalAmount = 0.0;
        $avgRisk = 0.0;
        $countRisk = 0;

        foreach ($credits as $credit) {
            $status = trim((string) ($credit['statut'] ?? 'Unknown')) ?: 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $totalAmount += (float) ($credit['montantDemande'] ?? 0);
            $avgRisk += (float) ($credit['mensualite'] ?? 0);
            ++$countRisk;
        }

        return [
            'headline' => [
                ['label' => 'Credit dossiers', 'value' => count($credits)],
                ['label' => 'Requested amount', 'value' => number_format($totalAmount, 2, '.', ' ').' DT'],
                ['label' => 'Average mensualite', 'value' => number_format($countRisk > 0 ? $avgRisk / $countRisk : 0, 2, '.', ' ').' DT'],
                ['label' => 'Payments logged', 'value' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM transactions WHERE categorie = 'Paiement credit'")],
            ],
            'bars' => $this->buildBars($statusCounts),
            'recent' => array_map(
                static fn (array $credit): array => [
                    'title' => sprintf('Credit #%d - %s', $credit['idCredit'], $credit['typeCredit'] ?? 'Unknown'),
                    'meta' => sprintf('Status %s | %.2f DT', $credit['statut'] ?? '-', (float) ($credit['montantDemande'] ?? 0)),
                ],
                array_slice($credits, 0, 8)
            ),
        ];
    }

    private function buildBars(array $data): array
    {
        $max = 1.0;
        foreach ($data as $value) {
            $max = max($max, (float) $value);
        }

        $bars = [];
        foreach ($data as $label => $value) {
            $bars[] = [
                'label' => (string) $label,
                'value' => $value,
                'percent' => (float) $value / $max * 100,
            ];
        }

        return $bars;
    }
}
