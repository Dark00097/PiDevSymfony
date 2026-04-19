<?php

namespace App\Controller;

use App\Controller\Sections\AccountsController as AccountsSectionController;
use App\Controller\Sections\AdminAccountController as AdminAccountSectionController;
use App\Controller\Sections\CashbackController as CashbackSectionController;
use App\Controller\Sections\CoffrevirtuelleController as CoffrevirtuelleSectionController;
use App\Controller\Sections\CreditsController as CreditsSectionController;
use App\Controller\Sections\GarantiesController as GarantiesSectionController;
use App\Controller\Sections\NotificationsController as NotificationsSectionController;
use App\Controller\Sections\PartnersController as PartnersSectionController;
use App\Controller\Sections\ProfileController as ProfileSectionController;
use App\Controller\Sections\ReclamationController as ReclamationSectionController;
use App\Controller\Sections\TransactionsController as TransactionsSectionController;
use App\Service\ActivityService;
use App\Service\AuthService;
use App\Service\BankingService;
use App\Service\GamificationService;
use App\Service\InsightsService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PortalController extends AbstractController
{
    private const GROQ_API_KEY = 'GROQ_KEY_HERE';
    private const GROQ_MODEL   = 'llama-3.1-8b-instant';
    private const WHISPER_DIR_REL = 'vendor/whisper';
    private const WHISPER_MODEL_REL = 'models/ggml-base.bin';
    private const WHEEL_SECURITY_SESSION_KEY = 'nexora.wheel_security';
    private const WHEEL_FEEDBACK_SESSION_KEY = 'nexora.wheel_feedback';
    private const WHEEL_SECURITY_MESSAGE = 'Une modification de la date système a été détectée. Action non autorisée.';

    public function __construct(
        private readonly AccountsSectionController $accountsSectionController,
        private readonly CoffrevirtuelleSectionController $coffrevirtuelleSectionController,
        private readonly TransactionsSectionController $transactionsSectionController,
        private readonly ReclamationSectionController $reclamationSectionController,
        private readonly CreditsSectionController $creditsSectionController,
        private readonly GarantiesSectionController $garantiesSectionController,
        private readonly CashbackSectionController $cashbackSectionController,
        private readonly PartnersSectionController $partnersSectionController,
        private readonly NotificationsSectionController $notificationsSectionController,
        private readonly ProfileSectionController $profileSectionController,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // ════════════════════════════════════════════════════════════
    //  Helpers Groq partagés
    // ════════════════════════════════════════════════════════════

    private function groqApiKey(): string
    {
        // Priorité : constante hardcodée (ignore les vars système Windows potentiellement expirées)
        return self::GROQ_API_KEY;
    }

    private function groqChat(array $messages, int $maxTokens = 1024, float $temperature = 0.1): string
    {
        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::GROQ_API_KEY,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => self::GROQ_MODEL,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ],
            'timeout'     => 30,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $body = $response->getContent(false);
            $data = json_decode($body, true);
            $msg  = $data['error']['message'] ?? ('Groq API error ' . $statusCode . ': ' . $body);
            throw new \RuntimeException($msg);
        }

        $data = $response->toArray();

        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    // ════════════════════════════════════════════════════════════
    //  GET /portal/export/pdf — Export PDF utilisateur connecté
    // ════════════════════════════════════════════════════════════

    #[Route('/portal/export/pdf', name: 'portal_export_pdf', methods: ['GET'])]
    public function exportUserPdf(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        \App\Service\ExportService $exportService,
    ): \Symfony\Component\HttpFoundation\Response {
        $session = $request->getSession();
        $user    = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $userId = (int) $user['idUser'];
        $userName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

        // ── Comptes ──
        $accounts = $bankingService->listAccounts($userId);

        // ── Coffres ──
        $vaults = $bankingService->listVaults($userId);

        // ── Transactions ──
        $transactions = $bankingService->listTransactions($userId);

        // ── Stats ──
        $totalBalance = array_sum(array_map(fn($a) => (float)($a['solde'] ?? 0), $accounts));
        $stats = [
            ['label' => 'Utilisateur',      'value' => $userName],
            ['label' => 'Comptes',          'value' => count($accounts)],
            ['label' => 'Solde total',      'value' => number_format($totalBalance, 2, '.', ' ') . ' DT'],
            ['label' => 'Coffres',          'value' => count($vaults)],
            ['label' => 'Transactions',     'value' => count($transactions)],
        ];

        // ── Section comptes ──
        $html  = $this->buildPdfSectionHtml('Comptes bancaires', '#0f766e');
        $html .= $this->buildPdfTable(
            ['N° Compte', 'Type', 'Statut', 'Solde (DT)', 'Plafond Retrait', 'Plafond Virement', 'Date Ouverture'],
            array_map(fn($a) => [
                $a['numeroCompte'] ?? '—',
                $a['typeCompte'] ?? '—',
                $a['statutCompte'] ?? '—',
                number_format((float)($a['solde'] ?? 0), 2, '.', ' '),
                number_format((float)($a['plafondRetrait'] ?? 0), 2, '.', ' '),
                number_format((float)($a['plafondVirement'] ?? 0), 2, '.', ' '),
                $a['dateOuverture'] ?? '—',
            ], $accounts)
        );

        // ── Section coffres ──
        $html .= $this->buildPdfSectionHtml('Coffres virtuels', '#7c3aed');
        $html .= $this->buildPdfTable(
            ['Nom', 'Statut', 'Montant actuel (DT)', 'Objectif (DT)', 'Date création', 'Date objectif'],
            array_map(fn($v) => [
                $v['nom'] ?? '—',
                $v['status'] ?? '—',
                number_format((float)($v['montantActuel'] ?? 0), 2, '.', ' '),
                number_format((float)($v['objectifMontant'] ?? 0), 2, '.', ' '),
                $v['dateCreation'] ?? '—',
                $v['dateObjectifs'] ?? '—',
            ], $vaults)
        );

        // ── Section transactions ──
        $html .= $this->buildPdfSectionHtml('Transactions', '#2563eb');
        $html .= $this->buildPdfTable(
            ['Date', 'Catégorie', 'Type', 'Montant (DT)', 'Description'],
            array_map(fn($t) => [
                $t['dateTransaction'] ?? '—',
                $t['categorie'] ?? '—',
                $t['typeTransaction'] ?? '—',
                number_format((float)($t['montant_value'] ?? 0), 2, '.', ' '),
                mb_substr((string)($t['description'] ?? '—'), 0, 60),
            ], array_slice($transactions, 0, 50))
        );

        $pdf = $exportService->buildPdf(
            'Rapport Financier — ' . $userName,
            [], [], $stats,
            'Export complet : comptes, coffres et transactions de ' . $userName . '.',
            '#0f766e'
        );

        // Remplacer le tableau vide par nos sections enrichies
        $pdf = $this->buildUserPdfFull($exportService, $userName, $stats, $html);

        return new \Symfony\Component\HttpFoundation\Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="nexora-rapport-%d.pdf"', $userId),
        ]);
    }

    private function buildUserPdfFull(\App\Service\ExportService $exportService, string $userName, array $stats, string $sectionsHtml): string
    {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);

        $generatedAt = date('Y-m-d H:i:s');
        $safeUser = htmlspecialchars($userName, ENT_QUOTES);

        $html  = '<html><head><meta charset="UTF-8"></head>';
        $html .= '<body style="font-family:DejaVu Sans,sans-serif;color:#0a2540;margin:0;background:#eef4f8;">';
        $html .= '<div style="padding:28px 32px 36px;">';
        // Header
        $html .= '<div style="background:#0a2540;color:#fff;border-top:6px solid #0f766e;border-radius:18px;padding:24px 28px;margin-bottom:18px;">';
        $html .= '<div style="font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#b9d4ec;">Nexora Export</div>';
        $html .= '<h1 style="margin:10px 0 8px;font-size:26px;">Rapport Financier — ' . $safeUser . '</h1>';
        $html .= '<div style="font-size:12px;color:#b9d4ec;">Généré le ' . $generatedAt . '</div>';
        $html .= '</div>';
        // Stats
        $html .= '<div style="margin-bottom:18px;">';
        foreach ($stats as $s) {
            $html .= '<div style="display:inline-block;width:18%;margin-right:2%;margin-bottom:10px;vertical-align:top;">';
            $html .= '<div style="background:#fff;border:1px solid #cfe0f1;border-top:4px solid #0f766e;border-radius:14px;padding:12px 14px;">';
            $html .= '<div style="font-size:10px;color:#5d7f9a;text-transform:uppercase;letter-spacing:.7px;margin-bottom:4px;">' . htmlspecialchars($s['label'], ENT_QUOTES) . '</div>';
            $html .= '<div style="font-size:18px;font-weight:800;color:#0a2540;">' . htmlspecialchars((string)$s['value'], ENT_QUOTES) . '</div>';
            $html .= '</div></div>';
        }
        $html .= '</div>';
        // Sections
        $html .= $sectionsHtml;
        $html .= '</div></body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildPdfSectionHtml(string $title, string $color): string
    {
        return sprintf(
            '<div style="margin-top:22px;margin-bottom:8px;font-size:14px;font-weight:800;color:%s;border-left:4px solid %s;padding-left:10px;text-transform:uppercase;letter-spacing:.6px;">%s</div>',
            htmlspecialchars($color, ENT_QUOTES),
            htmlspecialchars($color, ENT_QUOTES),
            htmlspecialchars($title, ENT_QUOTES)
        );
    }

    private function buildPdfTable(array $headers, array $rows): string
    {
        $html  = '<div style="background:#fff;border:1px solid #dbe7f3;border-radius:14px;padding:14px;margin-bottom:6px;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:11px;">';
        $html .= '<thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th style="border:1px solid #dbe3ef;padding:8px 10px;background:#edf4fb;text-align:left;font-weight:800;color:#0a2540;">' . htmlspecialchars($h, ENT_QUOTES) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($rows)) {
            $html .= '<tr><td colspan="' . count($headers) . '" style="border:1px solid #dbe3ef;padding:14px;text-align:center;color:#6b86a0;">Aucune donnée.</td></tr>';
        }
        foreach ($rows as $i => $row) {
            $bg = $i % 2 === 0 ? '#fff' : '#f9fbfe';
            $html .= '<tr style="background:' . $bg . ';">';
            foreach ($row as $cell) {
                $html .= '<td style="border:1px solid #dbe3ef;padding:8px 10px;">' . htmlspecialchars((string)$cell, ENT_QUOTES) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    // ════════════════════════════════════════════════════════════
    //  POST /api/translate
    // ════════════════════════════════════════════════════════════

    #[Route('/api/translate', name: 'api_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $body  = json_decode($request->getContent(), true) ?? [];
        $texts = (array) ($body['texts'] ?? []);
        $lang  = trim((string) ($body['lang'] ?? ''));

        if ($texts === [] || $lang === '') {
            return new JsonResponse(['error' => 'Missing texts or lang'], 400);
        }

        $langNames = [
            'en' => 'English', 'ar' => 'Arabic', 'es' => 'Spanish',
            'de' => 'German',  'it' => 'Italian', 'fr' => 'French',
        ];
        $langName = $langNames[$lang] ?? $lang;

        // Numéroter les lignes pour garantir un mapping 1-pour-1
        $numbered = [];
        foreach ($texts as $i => $t) {
            $numbered[] = ($i + 1) . '. ' . $t;
        }

        $prompt = "Translate each numbered line below to {$langName}.\n"
            . "Return ONLY the translated lines with their number prefix (e.g. \"1. \"), one per line, same order.\n"
            . "Do not add any other text, explanation or blank lines.\n\n"
            . implode("\n", $numbered);

        try {
            $content = $this->groqChat([['role' => 'user', 'content' => $prompt]], 2048);

            // Parser les lignes numérotées → tableau indexé 0…N
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $content)),
                fn($l) => $l !== ''
            ));

            $translations = [];
            foreach ($lines as $line) {
                // Supprimer le préfixe "1. ", "12. ", etc.
                $clean = preg_replace('/^\d+\.\s*/', '', $line);
                $translations[] = $clean;
            }

            // Compléter si le modèle a retourné moins de lignes
            while (count($translations) < count($texts)) {
                $translations[] = $texts[count($translations)];
            }

            return new JsonResponse(['translations' => array_slice($translations, 0, count($texts))]);

        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Groq error: ' . $e->getMessage()], 502);
        }
    }

    // ════════════════════════════════════════════════════════════
    //  POST /api/voice-parse
    // ════════════════════════════════════════════════════════════

    #[Route('/api/voice-parse', name: 'api_voice_parse', methods: ['POST'])]
    public function voiceParse(Request $request): JsonResponse
    {
        $body       = json_decode($request->getContent(), true) ?? [];
        $transcript = trim((string) ($body['transcript'] ?? ''));

        if ($transcript === '') {
            return new JsonResponse(['error' => 'Empty transcript'], 400);
        }

        $fields = $this->parseAccountFieldsFromText($transcript);

        return new JsonResponse($fields);
    }

    // ════════════════════════════════════════════════════════════
    //  POST /api/voice-stt-parse (Whisper.cpp offline)
    // ════════════════════════════════════════════════════════════

    #[Route('/api/voice-stt-parse', name: 'api_voice_stt_parse', methods: ['POST'])]
    public function voiceSttParse(Request $request): JsonResponse
    {
        $file = $request->files->get('audio');
        $lang = strtolower(trim((string) $request->request->get('lang', 'fr')));

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(['error' => 'Audio manquant ou invalide (multipart/form-data, champ "audio").'], 400);
        }

        $mime = (string) $file->getMimeType();
        $allowed = ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'];
        if (!in_array($mime, $allowed, true)) {
            return new JsonResponse(['error' => 'Format audio non supporte. Envoyez un WAV PCM 16-bit.'], 415);
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $voiceDir = $projectDir.'/var/voice';
        if (!is_dir($voiceDir) && !mkdir($voiceDir, 0777, true) && !is_dir($voiceDir)) {
            return new JsonResponse(['error' => 'Impossible de preparer le dossier var/voice'], 500);
        }

        $basename = 'rec_'.bin2hex(random_bytes(8));
        $wavPath  = $voiceDir.'/'.$basename.'.wav';
        $outBase  = $voiceDir.'/'.$basename.'_out';

        $file->move($voiceDir, basename($wavPath));

        try {
            $transcript = $this->transcribeWithWhisper($wavPath, $outBase, $lang);
        } catch (\Throwable $e) {
            @unlink($wavPath);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        $fields = $this->parseAccountFieldsFromText($transcript);

        @unlink($wavPath);
        @unlink($outBase.'.txt');
        @unlink($outBase.'.json');
        @unlink($outBase.'.srt');
        @unlink($outBase.'.vtt');

        return new JsonResponse([
            'transcript' => $transcript,
            'fields' => $fields,
        ]);
    }

    private function transcribeWithWhisper(string $wavPath, string $outBase, string $lang): string
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $whisperDir = rtrim($projectDir . DIRECTORY_SEPARATOR . self::WHISPER_DIR_REL, '/\\');
        $voiceDir = dirname($wavPath);
        $outBaseName = basename($outBase);

        $exeCandidates = [
            $whisperDir . '/extracted/Release/whisper-cli.exe',
            $whisperDir . '/extracted/Release/main.exe',
            $whisperDir . '/whisper.exe',
            $whisperDir . '/main.exe',
        ];
        $exe = null;
        foreach ($exeCandidates as $c) {
            if (is_file($c) && is_readable($c)) { $exe = $c; break; }
        }
        if ($exe === null) {
            throw new \RuntimeException('Binaire whisper.cpp introuvable. Placez whisper.exe (ou main.exe) dans ' . $whisperDir);
        }

        $modelCandidates = [
            $whisperDir . DIRECTORY_SEPARATOR . ltrim(self::WHISPER_MODEL_REL, '/\\'),
            $whisperDir . '/models/ggml-base.bin',
            $whisperDir . '/ggml-base.bin',
            $whisperDir . '/models/ggml-base-q5_1.bin',
        ];
        $model = null;
        foreach ($modelCandidates as $m) {
            if (is_file($m) && is_readable($m)) { $model = $m; break; }
        }
        if ($model === null) {
            throw new \RuntimeException('Modele Whisper introuvable. Placez ggml-base.bin dans ' . $whisperDir.'/models');
        }

        $lang = $lang !== '' ? $lang : 'fr';

        $cmd = [
            $exe,
            '-m', $model,
            '-f', $wavPath,
            '-l', $lang,
            '-otxt',
            '-of', $outBaseName,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($this->escapeCmd($cmd), $descriptor, $pipes, $voiceDir);
        if (!is_resource($process)) {
            throw new \RuntimeException('Impossible de lancer whisper.exe');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Whisper a echoue (code '.$exitCode.'): '.$stderr);
        }

        $txtPath = $voiceDir . DIRECTORY_SEPARATOR . $outBaseName . '.txt';
        $txt = @file_get_contents($txtPath);
        $txt = $this->sanitizeWhisperTranscript(is_string($txt) ? $txt : '');
        if ($txt !== '') {
            return $txt;
        }

        $txt = $this->sanitizeWhisperTranscript((string) $stdout);
        if ($txt === '') {
            throw new \RuntimeException('Transcription vide ou fichier de sortie introuvable.');
        }

        return $txt;
    }

    private function escapeCmd(array $parts): string
    {
        $escaped = [];
        foreach ($parts as $p) {
            $p = (string) $p;
            if ($p === '') { $escaped[] = '""'; continue; }
            if (preg_match('/[\\s"]/', $p)) {
                $escaped[] = '"'.str_replace('"', '""', $p).'"';
            } else {
                $escaped[] = $p;
            }
        }

        return implode(' ', $escaped);
    }

    private function sanitizeWhisperTranscript(string $text): string
    {
        $text = str_replace("\r", '', $text);
        $lines = preg_split('/\n+/u', $text) ?: [];
        $cleanLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(?:whisper_|whisper[[:alnum:]_]*:|system_info:|main:|output_|open:|ggml_|load time|fallbacks|mel time|sample time|encode time|decode time|batchd time|prompt time|total time)/i', $line)) {
                continue;
            }

            $line = preg_replace('/^\[[0-9:\.\-\>\s]+\]\s*/u', '', $line) ?? $line;
            if ($line === '' || preg_match('/^\[[^\]]+\]$/u', $line)) {
                continue;
            }

            $cleanLines[] = $line;
        }

        $text = trim(implode(' ', $cleanLines));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function parseAccountFieldsFromText(string $text): array
    {
        $normalized = $this->normalizeFr($text);

        $fields = [
            'numeroCompte'    => null,
            'typeCompte'      => null,
            'statutCompte'    => null,
            'solde'           => null,
            'dateOuverture'   => null,
            'plafondRetrait'  => null,
            'plafondVirement' => null,
        ];

        if (preg_match('/cb[-\\s]?(\\d{2,})/i', strtoupper($text), $m)) {
            $fields['numeroCompte'] = 'CB-' . $m[1];
        } elseif (preg_match('/\\b(?:numero|num|n)(?:\\s+de)?(?:\\s+compte)?[^\\d]{0,20}(\\d{3,})\\b/i', $normalized, $m)) {
            $fields['numeroCompte'] = 'CB-' . $m[1];
        } elseif (preg_match('/\\bcompte[^\\d]{0,20}(\\d{3,})\\b/i', $normalized, $m)) {
            $fields['numeroCompte'] = 'CB-' . $m[1];
        }

        if (str_contains($normalized, 'courant')) {
            $fields['typeCompte'] = 'Courant';
        } elseif (str_contains($normalized, 'professionnel') || str_contains($normalized, 'pro ')) {
            $fields['typeCompte'] = 'Professionnel';
        } elseif (str_contains($normalized, 'epargne')) {
            $fields['typeCompte'] = 'Épargne';
        }

        if (str_contains($normalized, 'actif')) {
            $fields['statutCompte'] = 'Actif';
        } elseif (str_contains($normalized, 'ferme')) {
            $fields['statutCompte'] = 'Fermé';
        } elseif (str_contains($normalized, 'bloque')) {
            $fields['statutCompte'] = 'Bloqué';
        }

        $date = $this->extractDate($normalized);
        if ($date !== null) {
            $fields['dateOuverture'] = $date;
        }

        $solde = $this->extractLabeledNumber($normalized, ['solde', 'montant'], ['date', 'plafond retrait', 'plafond virement', 'virement', 'retrait']);
        if ($solde !== null) {
            $fields['solde'] = $solde;
        }

        $ret = $this->extractLabeledNumber($normalized, ['plafond retrait', 'retrait'], ['plafond virement', 'virement', 'date']);
        if ($ret !== null) {
            $fields['plafondRetrait'] = $ret;
        }

        $vir = $this->extractLabeledNumber($normalized, ['plafond virement', 'virement', 'versement', 'veriment', 'verimenet'], ['date']);
        if ($vir !== null) {
            $fields['plafondVirement'] = $vir;
        }

        return array_filter($fields, static fn($v) => $v !== null && $v !== '');
    }

    private function normalizeFr(string $v): string
    {
        $v = mb_strtolower($v, 'UTF-8');
        $v = \Normalizer::normalize($v, \Normalizer::FORM_D) ?: $v;
        $v = preg_replace('/\\p{Mn}+/u', '', $v) ?? $v;
        $v = str_replace(["'", "’"], ' ', $v);
        $v = preg_replace('/\\b(\\d+)\\s*(?:virgule|point)\\s*(\\d{1,2})\\b/u', '$1.$2', $v) ?? $v;
        $v = preg_replace('/(?<=\\d)\\s*[,\\.]\\s*(?=\\d)/u', '.', $v) ?? $v;
        $v = str_replace([
            'auqtre',
            'quattre',
            'quatree',
            'virimenet',
            'verimenet',
            'verimant',
        ], [
            'quatre',
            'quatre',
            'quatre',
            'virement',
            'virement',
            'virement',
        ], $v);
        $v = preg_replace('/[,;]+/', ' ', $v) ?? $v;
        $v = preg_replace('/\\s+/', ' ', $v) ?? $v;

        return trim($v);
    }

    private function extractDate(string $normalized): ?string
    {
        if (preg_match('/\\bdate(?:\\s+d\\s+ouverture|\\s+ouverture)?[^\\d]{0,20}(\\d{1,2})[\\/-](\\d{1,2})[\\/-](\\d{2,4})\\b/u', $normalized, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) { $y += 2000; }
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        if (preg_match('/\\b(\\d{1,2})[\\/-](\\d{1,2})[\\/-](\\d{2,4})\\b/', $normalized, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) { $y += 2000; }
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        $months = [
            'janvier'=>1,'fevrier'=>2,'février'=>2,'mars'=>3,'avril'=>4,'mai'=>5,'juin'=>6,
            'juillet'=>7,'aout'=>8,'août'=>8,'septembre'=>9,'octobre'=>10,'novembre'=>11,'decembre'=>12,'décembre'=>12,
        ];
        if (preg_match('/\\bdate(?:\\s+d\\s+ouverture|\\s+ouverture)?[^\\d]{0,20}(\\d{1,2})\\s+(janvier|fevrier|février|mars|avril|mai|juin|juillet|aout|août|septembre|octobre|novembre|decembre|décembre)\\s+(\\d{4})\\b/u', $normalized, $m)) {
            $d = (int) $m[1];
            $mo = (int) ($months[$m[2]] ?? 0);
            $y = (int) $m[3];
            if ($mo > 0) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        if (preg_match('/\\b(\\d{1,2})\\s+(janvier|fevrier|février|mars|avril|mai|juin|juillet|aout|août|septembre|octobre|novembre|decembre|décembre)\\s+(\\d{4})\\b/u', $normalized, $m)) {
            $d = (int) $m[1];
            $mo = (int) ($months[$m[2]] ?? 0);
            $y = (int) $m[3];
            if ($mo > 0) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }

        return null;
    }

    private function extractLabeledNumber(string $normalized, array $labels, array $stopLabels = []): ?string
    {
        $pattern = '/(?:' . implode('|', array_map(static fn($l) => preg_quote($l, '/'), $labels)) . ')[^\\d]{0,30}(\\d+\\s+(?:virgule|point)\\s+\\d{1,2}|\\d+\\s+\\d{1,2}|[\\d]+(?:[\\.,]\\d{1,2})?)/u';
        if (preg_match($pattern, $normalized, $m)) {
            $num = $this->normalizeNumericToken($m[1]);
            if ($num !== null) {
                return $num;
            }
        }

        $segment = $this->extractSegmentAfterLabels($normalized, $labels, $stopLabels);
        if ($segment !== null) {
            $num = $this->extractNumericValueFromSegment($segment);
            if ($num !== null) {
                return $num;
            }

            $val = $this->wordsToNumber(trim($segment));
            if ($val !== null) {
                return number_format($val, 2, '.', '');
            }
        }

        $after = '/(?:' . implode('|', array_map(static fn($l) => preg_quote($l, '/'), $labels)) . ')[^a-z0-9]{0,12}([a-z\\-\\s]+)\\b/u';
        if (preg_match($after, $normalized, $m)) {
            $val = $this->wordsToNumber(trim($m[1]));
            if ($val !== null) {
                return number_format($val, 2, '.', '');
            }
        }

        return null;
    }

    private function extractNumericValueFromSegment(string $segment): ?string
    {
        if (preg_match('/\\b(\\d+)\\s*(?:[\\.,]|virgule|point)\\s*(\\d{1,2})\\b/u', $segment, $m)) {
            return sprintf('%d.%02d', (int) $m[1], (int) str_pad($m[2], 2, '0', STR_PAD_RIGHT));
        }

        if (preg_match('/\\b(\\d+)\\s+(\\d{1,2})\\b/u', $segment, $m)) {
            return sprintf('%d.%02d', (int) $m[1], (int) str_pad($m[2], 2, '0', STR_PAD_RIGHT));
        }

        if (preg_match('/\\b(\\d+(?:[\\.,]\\d{1,2})?)\\b/u', $segment, $m)) {
            return $this->normalizeNumericToken($m[1]);
        }

        return null;
    }

    private function normalizeNumericToken(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\\d+)\\s*(?:virgule|point)\\s*(\\d{1,2})$/u', $value, $m)) {
            return sprintf('%d.%02d', (int) $m[1], (int) str_pad($m[2], 2, '0', STR_PAD_RIGHT));
        }

        if (preg_match('/^(\\d+)\\s+(\\d{1,2})$/u', $value, $m)) {
            return sprintf('%d.%02d', (int) $m[1], (int) str_pad($m[2], 2, '0', STR_PAD_RIGHT));
        }

        $value = str_replace(',', '.', $value);
        if (!preg_match('/^\\d+(?:\\.\\d{1,2})?$/', $value)) {
            return null;
        }

        return ltrim($value, '0') === '' ? '0' : $value;
    }

    private function extractSegmentAfterLabels(string $normalized, array $labels, array $stopLabels = []): ?string
    {
        $labelPattern = '(?:' . implode('|', array_map(static fn($l) => preg_quote($l, '/'), $labels)) . ')';
        if (!preg_match('/' . $labelPattern . '\\s*(.+)$/u', $normalized, $m)) {
            return null;
        }

        $segment = trim((string) $m[1]);
        if ($segment === '') {
            return null;
        }

        if ($stopLabels !== []) {
            $stopPattern = '/\\b(?:' . implode('|', array_map(static fn($l) => preg_quote($l, '/'), $stopLabels)) . ')\\b/u';
            if (preg_match($stopPattern, $segment, $stopMatch, PREG_OFFSET_CAPTURE)) {
                $segment = trim(substr($segment, 0, $stopMatch[0][1]));
            }
        }

        $segment = preg_replace('/\\b(?:dt|dinar|dinars)\\b/u', ' ', $segment) ?? $segment;
        $segment = preg_replace('/\\s+/', ' ', $segment) ?? $segment;

        return trim($segment);
    }

    private function wordsToNumber(string $words): ?float
    {
        $words = preg_replace('/[^a-z\\s\\-]/u', ' ', $words) ?? $words;
        $words = preg_replace('/\\s+/', ' ', $words) ?? $words;
        $words = trim($words);
        if ($words === '') return null;

        $map = [
            'zero'=>0,'un'=>1,'une'=>1,'deux'=>2,'trois'=>3,'quatre'=>4,'cinq'=>5,'six'=>6,'sept'=>7,'huit'=>8,'neuf'=>9,
            'dix'=>10,'onze'=>11,'douze'=>12,'treize'=>13,'quatorze'=>14,'quinze'=>15,'seize'=>16,
            'vingt'=>20,'trente'=>30,'quarante'=>40,'cinquante'=>50,'soixante'=>60,
            'soixante-dix'=>70,'soixante dix'=>70,'quatre-vingt'=>80,'quatre vingt'=>80,'quatre-vingt-dix'=>90,'quatre vingt dix'=>90,
            'cent'=>100,'cents'=>100,'mille'=>1000,
        ];

        $tokens = preg_split('/\\s+/', str_replace(['-'], [' '], $words)) ?: [];
        $total = 0;
        $current = 0;
        foreach ($tokens as $t) {
            if ($t === 'et') continue;
            if (!array_key_exists($t, $map)) continue;
            $n = $map[$t];
            if ($n === 100) {
                $current = $current === 0 ? 100 : $current * 100;
            } elseif ($n === 1000) {
                $current = $current === 0 ? 1000 : $current * 1000;
                $total += $current;
                $current = 0;
            } else {
                $current += $n;
            }
        }
        $total += $current;

        return $total > 0 ? (float) $total : null;
    }
    #[Route('/api/wheel-condition-advice', name: 'api_wheel_condition_advice', methods: ['POST'])]
    public function wheelConditionAdvice(
        Request $request,
        AuthService $authService,
        GamificationService $gamificationService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);

            return new JsonResponse(['error' => $blockedReason], 423);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $requestedKeys = array_values(array_filter(array_map('strval', (array) ($body['conditions'] ?? []))));
        $wheel = $gamificationService->getWheelStatus((int) $user['idUser']);
        $failedConditions = array_values(array_filter(
            (array) ($wheel['eligibility']['failed_conditions'] ?? []),
            static function (array $condition) use ($requestedKeys): bool {
                if ($requestedKeys === []) {
                    return true;
                }

                return in_array((string) ($condition['key'] ?? ''), $requestedKeys, true);
            }
        ));

        if ($failedConditions === []) {
            return new JsonResponse([
                'items' => [],
                'message' => 'Toutes les conditions sont deja respectees.',
            ]);
        }

        $fallback = $this->buildWheelAdviceFallback($failedConditions);

        try {
            $prompt = <<<PROMPT
Tu es un coach financier d'une application bancaire.
Explique en francais pourquoi chaque condition de roue de jeu ci-dessous n'est pas respectee
et propose une solution concrete.

Regles :
- Reponds uniquement en JSON valide.
- Format attendu :
{"items":[{"key":"budget","title":"...","reason":"...","solution":"..."}]}
- Chaque "reason" et "solution" doit rester court, clair, actionnable.
- Ne cree aucun nouvel identifiant de condition : reutilise strictement les cles donnees.

Conditions en echec :
{$this->jsonEncodeForPrompt($failedConditions)}
PROMPT;

            $content = $this->groqChat([['role' => 'user', 'content' => $prompt]], 900, 0.2);
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content ?? '');
            $content = preg_replace('/\s*```$/i', '', $content ?? '');
            $decoded = json_decode(trim((string) $content), true);

            if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
                return new JsonResponse($fallback);
            }

            $items = [];
            foreach ($decoded['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $key = (string) ($item['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $items[] = [
                    'key' => $key,
                    'title' => trim((string) ($item['title'] ?? 'Conseil IA')),
                    'reason' => trim((string) ($item['reason'] ?? '')),
                    'solution' => trim((string) ($item['solution'] ?? '')),
                ];
            }

            if ($items === []) {
                return new JsonResponse($fallback);
            }

            return new JsonResponse([
                'items' => $items,
                'message' => 'Analyse IA generee avec succes.',
            ]);
        } catch (\Throwable) {
            return new JsonResponse($fallback);
        }
    }

    #[Route('/api/surplus-explanation', name: 'api_surplus_explanation', methods: ['POST'])]
    public function surplusExplanation(
        Request $request,
        AuthService $authService,
        InsightsService $insightsService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);

            return new JsonResponse(['error' => $blockedReason], 423);
        }

        $surplus = $insightsService->detectMonthlySurplus((int) $user['idUser']);
        $fallback = $this->buildSurplusExplanationFallback($surplus);

        try {
            $prompt = <<<PROMPT
Tu es un assistant financier dans une application bancaire.
Redige une justification courte en francais pour l'interface Superplus a partir des donnees ci-dessous.

Regles:
- Retourne uniquement du JSON valide.
- Format attendu:
{"justification":"..."}
- La justification doit faire 1 ou 2 phrases maximum.
- Le texte doit etre concis, clair et rassurant.
- Si show vaut false, indique explicitement qu'aucun surplus exploitable n'est detecte.
- Ne genere jamais de nouveau montant et ne liste pas toute la donnee brute.

Donnees:
{$this->jsonEncodeForPrompt([$surplus])}
PROMPT;

            $content = $this->groqChat([['role' => 'user', 'content' => $prompt]], 700, 0.2);
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content ?? '');
            $content = preg_replace('/\s*```$/i', '', $content ?? '');
            $decoded = json_decode(trim((string) $content), true);

            if (!is_array($decoded)) {
                return new JsonResponse($fallback);
            }

            $justification = trim((string) ($decoded['justification'] ?? ''));
            if ($justification === '') {
                return new JsonResponse($fallback);
            }

            return new JsonResponse([
                'status' => $fallback['status'],
                'title' => $fallback['title'],
                'amount' => $fallback['amount'],
                'badge' => $fallback['badge'],
                'justification' => $justification,
            ]);
        } catch (\Throwable) {
            return new JsonResponse($fallback);
        }
    }

    #[Route('/api/account-goal-assistant', name: 'api_account_goal_assistant', methods: ['POST'])]
    public function accountGoalAssistant(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
    ): JsonResponse {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);

            return new JsonResponse(['error' => $blockedReason], 423);
        }

        $userId = (int) $user['idUser'];
        $fallback = $this->buildGoalAssistantFallback(
            $bankingService->listVaults($userId),
            $bankingService->listAccounts($userId),
            $bankingService->listTransactions($userId)
        );

        $overdueItems = array_values(array_filter(
            (array) ($fallback['notifications'] ?? []),
            static fn (array $item): bool => (string) ($item['type'] ?? '') === 'overdue'
        ));
        if ($overdueItems === []) {
            return new JsonResponse($fallback);
        }

        try {
            $prompt = <<<PROMPT
Tu es une assistante financiere dans une application bancaire.
Pour chaque coffre en retard ci-dessous, redige une justification courte en francais.

Regles:
- Retourne uniquement du JSON valide.
- Format attendu:
{"items":[{"idCoffre":1,"justification":"..."}]}
- Une justification par coffre.
- Une seule phrase claire, empathique et concrete par justification.
- Reutilise strictement les idCoffre fournis.
- Ne cree aucun montant ni aucune date supplementaire.

Donnees:
{$this->jsonEncodeForPrompt($overdueItems)}
PROMPT;

            $content = $this->groqChat([['role' => 'user', 'content' => $prompt]], 900, 0.2);
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content ?? '');
            $content = preg_replace('/\s*```$/i', '', $content ?? '');
            $decoded = json_decode(trim((string) $content), true);

            if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
                return new JsonResponse($fallback);
            }

            $justifications = [];
            foreach ($decoded['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $vaultId = (int) ($item['idCoffre'] ?? 0);
                $justification = trim((string) ($item['justification'] ?? ''));
                if ($vaultId > 0 && $justification !== '') {
                    $justifications[$vaultId] = $justification;
                }
            }

            if ($justifications === []) {
                return new JsonResponse($fallback);
            }

            $fallback['source'] = 'ai';
            $fallback['notifications'] = array_map(static function (array $item) use ($justifications): array {
                $vaultId = (int) ($item['idCoffre'] ?? 0);
                if ((string) ($item['type'] ?? '') === 'overdue' && isset($justifications[$vaultId])) {
                    $item['explanation'] = $justifications[$vaultId];
                }

                return $item;
            }, (array) ($fallback['notifications'] ?? []));

            return new JsonResponse($fallback);
        } catch (\Throwable) {
            return new JsonResponse($fallback);
        }
    }

    #[Route('/api/wheel-date-check', name: 'api_wheel_date_check', methods: ['POST'])]
    public function wheelDateCheck(Request $request, AuthService $authService): JsonResponse
    {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);

            return new JsonResponse(['error' => $blockedReason], 423);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $wheelDateGuard = $this->evaluateWheelClientDate(
            trim((string) ($body['client_iso'] ?? '')),
            $session
        );
        $timezoneOffsetMinutes = (int) ($body['timezone_offset_minutes'] ?? 0);

        return new JsonResponse([
            'ok' => !$wheelDateGuard['anomaly'],
            'anomaly' => $wheelDateGuard['anomaly'],
            'locked' => $this->isWheelSecurityLocked($session),
            'message' => $wheelDateGuard['message'], /*
                ? 'Une modification de la date système a été détectée.'
                : 'Date locale conforme à la date serveur.',
            */            'server_iso' => $wheelDateGuard['server_iso'],
            'server_date' => $wheelDateGuard['server_date'],
            'client_iso' => $wheelDateGuard['client_iso'],
            'timezone_offset_minutes' => $timezoneOffsetMinutes,
            'diff_seconds' => $wheelDateGuard['diff_seconds'],
        ], $wheelDateGuard['anomaly'] ? 423 : 200);
    }

    // ════════════════════════════════════════════════════════════
    //  Portal principal
    // ════════════════════════════════════════════════════════════

    #[Route('/portal', name: 'portal_dashboard', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        NotificationService $notificationService,
        ActivityService $activityService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
    ): Response {
        $session = $request->getSession();
        $user = $authService->getAuthenticatedUser($session);
        if ($user === null) {
            return $this->redirectToRoute('login');
        }

        $blockedReason = $authService->getLoginBlockReason($user);
        if ($blockedReason !== null) {
            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        if (strtoupper((string) ($user['role'] ?? '')) === 'ROLE_ADMIN') {
            return $this->redirectToRoute('admin_dashboard');
        }

        $tab = (string) $request->query->get('tab', 'dashboard');
        if ($tab === 'overview') {
            $tab = 'dashboard';
        }

        if ($request->isMethod('POST')) {
            $tab = (string) $request->request->get('tab', $tab);
            $panel = trim((string) $request->request->get('panel', ''));
            $assistant = trim((string) $request->request->get('assistant', $request->query->get('assistant', '')));
            $selectedAccount = trim((string) $request->request->get('selected_account', $request->query->get('selected_account', '')));
            $showVault = trim((string) $request->request->get('show_vault', $request->query->get('show_vault', '')));
            $showWheel = trim((string) $request->request->get('show_wheel', $request->query->get('show_wheel', '')));
            $showSurplus = trim((string) $request->request->get('show_surplus', $request->query->get('show_surplus', '')));
            $showPrediction = trim((string) $request->request->get('show_prediction', $request->query->get('show_prediction', '')));
            $editVault = trim((string) $request->request->get('edit_vault', $request->query->get('edit_vault', '')));
            $editId = trim((string) $request->request->get('edit_id', $request->query->get('edit_id', '')));
            $searchQuery = trim((string) $request->request->get('q', $request->query->get('q', '')));
            $searchIn = trim((string) $request->request->get('search_in', $request->query->get('search_in', '')));
            $filter = trim((string) $request->request->get('filter', $request->query->get('filter', '')));
            $sort = trim((string) $request->request->get('sort', $request->query->get('sort', '')));
            $dir = trim((string) $request->request->get('dir', $request->query->get('dir', '')));
            $action = (string) $request->request->get('action', '');
            $this->handlePortalAction($request, $authService, $bankingService, $notificationService, $insightsService, $gamificationService, $user);

            if ($selectedAccount === '' && $action === 'account_save') {
                $savedAccountId = (int) $request->getSession()->get('nexora.last_saved_account_id', 0);
                if ($savedAccountId > 0) {
                    $selectedAccount = (string) $savedAccountId;
                }
                $request->getSession()->remove('nexora.last_saved_account_id');
            }

            if ($selectedAccount === '' && $action === 'vault_save') {
                $selectedAccount = trim((string) $request->request->get('idCompte', ''));
            }

            $closeAssistantPanel = (bool) $request->attributes->get('close_assistant_panel', false);
            $routeParams = ['tab' => $tab];
            if ($panel !== '') {
                $routeParams['panel'] = $panel;
            }
            if (!$closeAssistantPanel && $assistant !== '') {
                $routeParams['assistant'] = $assistant;
            }
            if ($selectedAccount !== '') {
                $routeParams['selected_account'] = $selectedAccount;
            }
            if ($showVault !== '') {
                $routeParams['show_vault'] = $showVault;
            }
            if (!$closeAssistantPanel && $showWheel !== '') {
                $routeParams['show_wheel'] = $showWheel;
            }
            if (!$closeAssistantPanel && $showSurplus !== '') {
                $routeParams['show_surplus'] = $showSurplus;
            }
            if (!$closeAssistantPanel && $showPrediction !== '') {
                $routeParams['show_prediction'] = $showPrediction;
            }
            if ($editVault !== '') {
                $routeParams['edit_vault'] = $editVault;
            }
            if ($editId !== '') {
                $routeParams['edit_id'] = $editId;
            }
            if ($searchQuery !== '') {
                $routeParams['q'] = $searchQuery;
            }
            if ($searchIn !== '') {
                $routeParams['search_in'] = $searchIn;
            }
            if ($filter !== '') {
                $routeParams['filter'] = $filter;
            }
            if ($sort !== '') {
                $routeParams['sort'] = $sort;
            }
            if ($dir !== '') {
                $routeParams['dir'] = $dir;
            }

            return $this->redirectToRoute('portal_dashboard', $routeParams);
        }

        $data = $this->buildPortalTabData($tab, $request, $bankingService, $notificationService, $activityService, $insightsService, $gamificationService, $user);
        if ($tab === 'profile') {
            $profileAi = $request->getSession()->get('nexora.profile_ai_data');
            if (!is_array($profileAi)) {
                $profileAi = $this->buildProfileAiData((int) $user['idUser'], $insightsService);
                $request->getSession()->set('nexora.profile_ai_data', $profileAi);
            }
            $data['support']['profile_ai'] = $profileAi;
        }

        $tabTemplate = $this->resolvePortalTabTemplate($tab);
        $tabStylesheets = $this->resolvePortalTabStylesheets($tab);

        return $this->render('interfaces/portal/UserDashboard.html.twig', array_merge($data, [
            'mode' => 'portal',
            'route_name' => 'portal_dashboard',
            'tab_template' => $tabTemplate,
            'tab_stylesheets' => $tabStylesheets,
            'current_user' => $user,
            'feature_links' => [
                ['label' => 'Insights', 'href' => $this->generateUrl('portal_features', ['section' => 'insights'])],
                ['label' => 'Games', 'href' => $this->generateUrl('portal_features', ['section' => 'games'])],
                ['label' => 'Payments', 'href' => $this->generateUrl('portal_features', ['section' => 'payments'])],
                ['label' => 'Exports', 'href' => $this->generateUrl('portal_features', ['section' => 'exports'])],
            ],
            'tabs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard'],
                ['key' => 'accounts', 'label' => 'Comptes'],
                ['key' => 'transactions', 'label' => 'Transactions'],
                ['key' => 'credits', 'label' => 'Credits'],
                ['key' => 'cashback', 'label' => 'Recompenses'],
                ['key' => 'garanties', 'label' => 'Garanties'],
                ['key' => 'complaints', 'label' => 'Reclamations'],
                ['key' => 'vaults', 'label' => 'Coffres'],
                ['key' => 'profile', 'label' => 'Profil'],
                ['key' => 'notifications', 'label' => 'Notifications'],
            ],
        ]));
    }

    private function handlePortalAction(
        Request $request,
        AuthService $authService,
        BankingService $bankingService,
        NotificationService $notificationService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
        array $user
    ): void {
        $action = (string) $request->request->get('action', '');
        $userId = (int) $user['idUser'];
        $session = $request->getSession();
        $profileImagePath = $action === 'profile_save'
            ? $this->handleProfileImageUpload($request, 'profile_image')
            : null;

        try {
            $accountsFlash = $this->accountsSectionController->handlePortalAction($action, $request, $bankingService, $userId);

            foreach ([
                $accountsFlash,
                str_starts_with($action, 'vault_') ? null : $this->coffrevirtuelleSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->transactionsSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->reclamationSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->creditsSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->garantiesSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->cashbackSectionController->handlePortalAction($action, $request, $bankingService, $userId),
                $this->profileSectionController->handlePortalAction($action, $request, $authService, $insightsService, $user, $profileImagePath),
                $this->notificationsSectionController->handleAction($action, $notificationService, $user, $request),
            ] as $flash) {
                if ($flash !== null) {
                    $this->addFlash((string) $flash['type'], (string) $flash['message']);
                    return;
                }
            }

            switch ($action) {
                case 'wheel_spin':
                    $wheelDateGuard = $this->validateWheelClientDate($request);
                    if ($wheelDateGuard['anomaly']) { $this->setWheelFeedback($session, 'error', $wheelDateGuard['message']); /*
                        $this->addFlash('error', 'Une modification de la date système a été détectée.');
                        */ break;
                    }

                    $wheelResult = $gamificationService->spinWheel($userId, $this->requestInt($request, 'idCompteBonus'));
                    $spinMessage = (string) ($wheelResult['spin_result']['message'] ?? '');
                    $this->setWheelFeedback(
                        $session,
                        'success',
                        $spinMessage !== '' ? $spinMessage : 'Tour de roue effectue avec succes.'
                    );
                    break;
                case 'wheel_bonus':
                    $wheelDateGuard = $this->validateWheelClientDate($request);
                    if ($wheelDateGuard['anomaly']) {
                        $this->setWheelFeedback($session, 'error', $wheelDateGuard['message']);
                        break;
                    }

                    $wheelResult = $gamificationService->claimWheelBonus($userId, $this->requestInt($request, 'idCompte') ?? 0);
                    $this->setWheelFeedback(
                        $session,
                        'success',
                        (bool) ($wheelResult['bonus_ready'] ?? false) ? 'Le bonus roue est encore disponible.' : 'Bonus roue credite (+50 DT).'
                    );
                    break;
                case 'surplus_transfer':
                    $transfer = $insightsService->transferDetectedMonthlySurplusToVault($userId, $this->requestInt($request, 'idCoffre') ?? 0);
                    $this->addFlash(
                        'success',
                        sprintf(
                            '%.2f DT ont ete transferes vers le coffre %s depuis le compte %s.',
                            (float) ($transfer['amount'] ?? 0),
                            (string) ($transfer['vault_name'] ?? '#'.$this->requestInt($request, 'idCoffre')),
                            (string) (($transfer['source_account']['numeroCompte'] ?? '') !== '' ? $transfer['source_account']['numeroCompte'] : '#'.((int) ($transfer['source_account']['idCompte'] ?? 0)))
                        )
                    );
                    $request->attributes->set('close_assistant_panel', true);
                    break;
                case 'surplus_dismiss':
                    $dismissMonth = trim((string) $request->request->get('month', ''));
                    if ($dismissMonth === '') {
                        $dismissMonth = (string) (($insightsService->detectMonthlySurplus($userId))['month'] ?? '');
                    }
                    $insightsService->acknowledgeMonthlySurplus($userId, $dismissMonth);
                    $this->addFlash('info', 'Suggestion de surplus ignoree pour ce mois.');
                    $request->attributes->set('close_assistant_panel', true);
                    break;
                case 'vault_goal_transfer':
                    $transfer = $bankingService->transferVaultAmountToAccount(
                        $userId,
                        $this->requestInt($request, 'idCoffre') ?? 0,
                        $this->requestInt($request, 'idCompte') ?? 0
                    );
                    $this->addFlash(
                        'success',
                        sprintf(
                            '%.2f DT du coffre %s ont ete transferes vers le compte %s.',
                            (float) ($transfer['amount'] ?? 0),
                            (string) ($transfer['vault_name'] ?? '#'.$this->requestInt($request, 'idCoffre')),
                            (string) ($transfer['account_number'] ?? '#'.$this->requestInt($request, 'idCompte'))
                        )
                    );
                    break;
                case 'vault_goal_extend':
                    $extension = $bankingService->extendVaultGoalDate(
                        $userId,
                        $this->requestInt($request, 'idCoffre') ?? 0,
                        trim((string) $request->request->get('extension', ''))
                    );
                    $this->addFlash(
                        'success',
                        sprintf(
                            'La date objectif du coffre %s a ete prolongee jusqu au %s.',
                            (string) ($extension['vault_name'] ?? '#'.$this->requestInt($request, 'idCoffre')),
                            (string) ($extension['new_date'] ?? '')
                        )
                    );
                    break;
            }
        } catch (\Throwable $exception) {
            if (str_starts_with($action, 'wheel_')) {
                [$type, $message] = $this->resolveWheelFeedbackFromException($exception);
                $this->setWheelFeedback($session, $type, $message);

                return;
            }

            $this->addFlash('error', $exception->getMessage());
        }
    }

    private function buildPortalTabData(
        string $tab,
        Request $request,
        BankingService $bankingService,
        NotificationService $notificationService,
        ActivityService $activityService,
        InsightsService $insightsService,
        GamificationService $gamificationService,
        array $user
    ): array {
        $userId = (int) $user['idUser'];
        $summary = $bankingService->getUserDashboard($userId);
        $data = [
            'tab' => $tab,
            'summary' => $summary,
            'items' => [],
            'support' => [],
            'notifications' => $notificationService->getRecentNotificationsFor($userId, (string) $user['role'], 20),
            'notifications_count' => $notificationService->countUnreadFor($userId, (string) $user['role']),
        ];

        if ($tab === 'accounts') {
            $accountsData = $this->accountsSectionController->buildPortalData($bankingService, $activityService, $gamificationService, $userId, $request);
            $vaultsData = $this->coffrevirtuelleSectionController->buildPortalData($bankingService, $userId);
            $predictionAccountId = (int) $request->query->get('selected_account', 0);
            $data = $this->mergeTabData($data, $accountsData);
            $data = $this->mergeTabData($data, $vaultsData);
            $data['items'] = $accountsData['items'] ?? [];
            $data['support']['surplus'] = $insightsService->detectMonthlySurplus($userId);
            $data['support']['prediction'] = $insightsService->getSpendingPrediction(
                $userId,
                $predictionAccountId > 0 ? $predictionAccountId : null
            );
        } elseif ($tab === 'transactions') {
            $queryParams = $request->query->all();
            $data = $this->mergeTabData($data, $this->transactionsSectionController->buildPortalData($bankingService, $userId, $queryParams));
        } elseif ($tab === 'credits') {
            $creditsData = $this->creditsSectionController->buildPortalData($bankingService, $userId);
            $garantiesData = $this->garantiesSectionController->buildPortalData($bankingService, $userId);
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $creditsData['items'] ?? [];
        } elseif ($tab === 'garanties') {
            $garantiesData = $this->garantiesSectionController->buildPortalData($bankingService, $userId);
            $creditsData = $this->creditsSectionController->buildPortalData($bankingService, $userId);
            $data = $this->mergeTabData($data, $creditsData);
            $data = $this->mergeTabData($data, $garantiesData);
            $data['items'] = $garantiesData['items'] ?? [];
            $data['support']['credits'] = $creditsData['items'] ?? [];
        } elseif ($tab === 'cashback') {
            $cashbackData = $this->cashbackSectionController->buildPortalData($bankingService, $userId);
            $partnersData = $this->partnersSectionController->buildPortalData($bankingService);
            $data = $this->mergeTabData($data, $cashbackData);
            $data = $this->mergeTabData($data, $partnersData);
            $data['items'] = $cashbackData['items'] ?? [];
        } elseif ($tab === 'complaints') {
            $data = $this->mergeTabData($data, $this->reclamationSectionController->buildPortalData($bankingService, $userId));
        } elseif ($tab === 'vaults') {
            $vaultsData = $this->coffrevirtuelleSectionController->buildPortalData($bankingService, $userId);
            $accountsData = $this->accountsSectionController->buildPortalData($bankingService, $activityService, $gamificationService, $userId, $request);
            $data = $this->mergeTabData($data, $accountsData);
            $data = $this->mergeTabData($data, $vaultsData);
            $data['items'] = $vaultsData['items'] ?? [];
        } elseif ($tab === 'profile') {
            $data = $this->mergeTabData($data, $this->profileSectionController->buildPortalData($activityService, $userId));
        } elseif ($tab === 'notifications') {
            $data = $this->mergeTabData($data, $this->notificationsSectionController->buildPortalData($data['notifications']));
        }

        return $data;
    }

    private function mergeTabData(array $base, array $extra): array
    {
        if (isset($extra['items'])) {
            $base['items'] = $extra['items'];
        }

        if (isset($extra['support']) && is_array($extra['support'])) {
            $base['support'] = array_replace($base['support'], $extra['support']);
        }

        return $base;
    }

    private function requestInt(Request $request, string $key): ?int
    {
        $value = $request->request->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @return array<string, mixed>
     */
    private function buildWheelAdviceFallback(array $conditions): array
    {
        $items = array_map(static function (array $condition): array {
            return [
                'key' => (string) ($condition['key'] ?? ''),
                'title' => sprintf('Condition : %s', (string) ($condition['short_label'] ?? $condition['label'] ?? 'Verification')),
                'reason' => (string) ($condition['reason'] ?? 'La condition n est pas encore validee.'),
                'solution' => (string) ($condition['solution'] ?? 'Mettez a jour votre situation financiere puis reessayez.'),
            ];
        }, $conditions);

        return [
            'items' => $items,
            'message' => 'Conseils generes localement.',
        ];
    }

    /**
     * @param array<string, mixed> $surplus
     * @return array<string, mixed>
     */
    private function buildSurplusExplanationFallback(array $surplus): array
    {
        $show = (bool) ($surplus['show'] ?? false);
        $recommended = $show ? (float) ($surplus['recommended_transfer'] ?? 0.0) : 0.0;

        $justification = trim((string) ($surplus['message'] ?? 'Analyse Superplus disponible.'));
        if ($show) {
            $justification = sprintf(
                'Les credits du mois depassent clairement la moyenne recente, donc Superplus recommande d epargner %.2f DT.',
                $recommended
            );
        } elseif (!(bool) ($surplus['stability_ok'] ?? false)) {
            $justification = 'Aucun surplus detecte : les credits des trois derniers mois ne sont pas assez stables pour servir de reference.';
        } elseif ((float) ($surplus['surplus'] ?? 0.0) < (float) ($surplus['surplus_threshold'] ?? 0.0)) {
            $justification = 'Aucun surplus detecte : le credit du mois reste trop proche de la moyenne recente.';
        } elseif ((float) ($surplus['display_recommended_transfer'] ?? 0.0) <= 0.0) {
            $justification = 'Aucun surplus detecte : aucun transfert automatique n est conseille pour le moment.';
        }

        return [
            'status' => $show ? 'surplus' : 'no_surplus',
            'title' => $show ? 'Montant a epargner' : 'Aucun surplus detecte',
            'amount' => round($recommended, 2),
            'badge' => $show ? 'Suggestion active' : 'Situation stable',
            'justification' => $justification,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $vaults
     * @param array<int, array<string, mixed>> $accounts
     * @param array<int, array<string, mixed>> $transactions
     * @return array<string, mixed>
     */
    private function buildGoalAssistantFallback(array $vaults, array $accounts, array $transactions): array
    {
        $today = new \DateTimeImmutable('today');
        $accountIndex = [];
        foreach ($accounts as $account) {
            $accountId = (int) ($account['idCompte'] ?? 0);
            if ($accountId > 0) {
                $accountIndex[$accountId] = $account;
            }
        }

        $accountPayload = array_map(static function (array $account): array {
            return [
                'idCompte' => (int) ($account['idCompte'] ?? 0),
                'numeroCompte' => (string) ($account['numeroCompte'] ?? ''),
                'typeCompte' => (string) ($account['typeCompte'] ?? 'Compte'),
                'statutCompte' => (string) ($account['statutCompte'] ?? ''),
                'solde' => round((float) ($account['solde'] ?? 0.0), 2),
            ];
        }, $accounts);

        $notifications = [];
        foreach ($vaults as $vault) {
            $vaultId = (int) ($vault['idCoffre'] ?? 0);
            $goalAmount = round((float) ($vault['objectifMontant'] ?? 0.0), 2);
            $currentAmount = round((float) ($vault['montantActuel'] ?? 0.0), 2);
            if ($vaultId <= 0 || $goalAmount <= 0) {
                continue;
            }

            $progress = round(min(100, ($currentAmount * 100) / max($goalAmount, 1)), 1);
            $targetDateRaw = trim((string) ($vault['dateObjectifs'] ?? ''));
            $targetDate = $this->parseFlexibleDate($targetDateRaw);
            $linkedAccount = $accountIndex[(int) ($vault['idCompte'] ?? 0)] ?? null;

            if ($progress >= 100 && ($targetDate === null || $targetDate >= $today)) {
                $notifications[] = [
                    'type' => 'success',
                    'idCoffre' => $vaultId,
                    'title' => 'Bravo, vous avez atteint votre objectif !',
                    'message' => sprintf(
                        'Le coffre "%s" a atteint %.0f%% de son objectif avec %.2f DT disponibles.',
                        (string) ($vault['nom'] ?? 'Coffre'),
                        $progress,
                        $currentAmount
                    ),
                    'explanation' => $targetDate !== null
                        ? sprintf('L objectif a ete atteint avant ou a la date cible du %s. Vous pouvez maintenant affecter ce montant a l un de vos comptes.', $targetDate->format('Y-m-d'))
                        : 'L objectif est pleinement finance. Vous pouvez maintenant affecter ce montant a l un de vos comptes.',
                    'vault' => [
                        'idCoffre' => $vaultId,
                        'nom' => (string) ($vault['nom'] ?? 'Coffre'),
                        'montantActuel' => $currentAmount,
                        'objectifMontant' => $goalAmount,
                        'progression' => $progress,
                        'dateObjectifs' => $targetDate?->format('Y-m-d'),
                    ],
                    'transfer_amount' => $currentAmount,
                ];
            }

            if ($targetDate !== null && $targetDate < $today && $progress < 100) {
                $notifications[] = [
                    'type' => 'overdue',
                    'idCoffre' => $vaultId,
                    'title' => 'Votre objectif n a pas ete atteint a temps',
                    'message' => sprintf(
                        'Le coffre "%s" est a %.1f%% avec %.2f DT sur %.2f DT.',
                        (string) ($vault['nom'] ?? 'Coffre'),
                        $progress,
                        $currentAmount,
                        $goalAmount
                    ),
                    'explanation' => $this->buildGoalDelayExplanationFallback($vault, $transactions, $linkedAccount),
                    'vault' => [
                        'idCoffre' => $vaultId,
                        'nom' => (string) ($vault['nom'] ?? 'Coffre'),
                        'montantActuel' => $currentAmount,
                        'objectifMontant' => $goalAmount,
                        'progression' => $progress,
                        'dateObjectifs' => $targetDate->format('Y-m-d'),
                    ],
                    'extensions' => [
                        ['code' => 'P3M', 'label' => '+3 mois'],
                        ['code' => 'P6M', 'label' => '+6 mois'],
                        ['code' => 'P1Y', 'label' => '+1 an'],
                    ],
                ];
            }
        }

        usort($notifications, static function (array $left, array $right): int {
            $priority = ['success' => 0, 'overdue' => 1];

            return ($priority[$left['type']] ?? 9) <=> ($priority[$right['type']] ?? 9);
        });

        return [
            'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'source' => 'fallback',
            'accounts' => $accountPayload,
            'notifications' => $notifications,
            'summary' => [
                'vault_count' => count($vaults),
                'notification_count' => count($notifications),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $vault
     * @param array<int, array<string, mixed>> $transactions
     * @param array<string, mixed>|null $linkedAccount
     */
    private function buildGoalDelayExplanationFallback(array $vault, array $transactions, ?array $linkedAccount): string
    {
        $vaultAccountId = (int) ($vault['idCompte'] ?? 0);
        $goalAmount = (float) ($vault['objectifMontant'] ?? 0.0);
        $currentAmount = (float) ($vault['montantActuel'] ?? 0.0);
        $progress = $goalAmount > 0 ? ($currentAmount * 100) / $goalAmount : 0.0;
        $recentSavingsTransfers = 0;
        $lastSavingsDate = null;

        foreach ($transactions as $transaction) {
            if ((int) ($transaction['idCompte'] ?? 0) !== $vaultAccountId) {
                continue;
            }

            // Résolution logique du type
            $typeRaw = strtolower(trim((string) ($transaction['typeTransaction'] ?? '')));
            $resolvedType = 'UNKNOWN';
            if (str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement') || $typeRaw === 'credit') {
                $resolvedType = 'CREDIT';
            } elseif (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'retrait') || $typeRaw === 'debit' || str_contains($typeRaw, 'paimenet')) {
                $resolvedType = 'DEBIT';
            } elseif (str_contains($typeRaw, 'virement')) {
                $dest = trim((string)($transaction['idCompteDestinataire'] ?? ''));
                $resolvedType = ($dest !== '' && $dest !== '0') ? 'DEBIT' : 'CREDIT';
            }

            if ($resolvedType !== 'DEBIT') {
                continue;
            }

            $category = strtolower(trim((string) ($transaction['categorie'] ?? '')));
            $description = strtolower(trim((string) ($transaction['description'] ?? '')));
            $looksLikeSavings = str_contains($category, 'epargne')
                || str_contains($description, 'coffre')
                || str_contains($description, 'epargne');
            if (!$looksLikeSavings) {
                continue;
            }

            $date = $this->parseFlexibleDate((string) ($transaction['dateTransaction'] ?? ''));
            if ($date === null) {
                continue;
            }

            if ($date >= (new \DateTimeImmutable('today'))->modify('-90 days')) {
                $recentSavingsTransfers++;
                if ($lastSavingsDate === null || $date > $lastSavingsDate) {
                    $lastSavingsDate = $date;
                }
            }
        }

        if ($recentSavingsTransfers === 0) {
            return 'Aucun depot recent vers votre epargne n a ete detecte, ce qui a freine la progression de cet objectif.';
        }

        if ($recentSavingsTransfers < 3) {
            return 'Vos depots ont ete irreguliers ces derniers mois, ce qui explique le retard de cet objectif.';
        }

        if ($linkedAccount !== null && (float) ($linkedAccount['solde'] ?? 0.0) < max(50.0, $goalAmount * 0.1)) {
            return 'Le compte lie a cet objectif a manque de disponibilite reguliere pour maintenir le rythme d epargne prevu.';
        }

        if ($progress < 50) {
            return 'Le rythme actuel d epargne reste trop faible par rapport au montant cible, meme si quelques versements ont bien ete effectues.';
        }

        return $lastSavingsDate !== null
            ? sprintf('Le coffre progresse, mais les versements depuis le %s restent insuffisants pour atteindre l objectif dans le delai initial.', $lastSavingsDate->format('Y-m-d'))
            : 'Le coffre progresse, mais le rythme des versements reste insuffisant pour tenir le delai initial.';
    }

    private function parseFlexibleDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{anomaly:bool,message:string}
     */
    private function validateWheelClientDate(Request $request): array
    {
        return $this->evaluateWheelClientDate(
            trim((string) $request->request->get('client_local_iso', '')),
            $request->getSession()
        ); /*
        $clientIso = trim((string) $request->request->get('client_local_iso', ''));
        if ($clientIso === '') {
            return [
                'anomaly' => true,
                'message' => 'La date locale n a pas ete fournie.',
            ];
        }

        try {
            $clientDate = new \DateTimeImmutable($clientIso);
        } catch (\Throwable) {
            return [
                'anomaly' => true,
                'message' => 'La date locale est invalide.',
            ];
        }

        $serverNow = $this->getTrustedReferenceNow();
        $diffSeconds = abs($serverNow->getTimestamp() - $clientDate->getTimestamp());
        $sameCalendarDay = $serverNow->format('Y-m-d') === $clientDate->setTimezone($serverNow->getTimezone())->format('Y-m-d');

        return [
            'anomaly' => $diffSeconds > 600 || !$sameCalendarDay,
            'message' => $diffSeconds > 600 || !$sameCalendarDay
                ? 'Une modification de la date système a été détectée.'
                : 'Date client valide.',
        ];
    */ }

    private function evaluateWheelClientDate(string $clientIso, SessionInterface $session): array
    {
        $serverNow = $this->getTrustedReferenceNow();

        if ($clientIso === '') {
            $this->lockWheelForFraud($session, self::WHEEL_SECURITY_MESSAGE);

            return [
                'anomaly' => true,
                'message' => self::WHEEL_SECURITY_MESSAGE,
                'server_iso' => $serverNow->format(\DateTimeInterface::ATOM),
                'server_date' => $serverNow->format('Y-m-d H:i:s'),
                'client_iso' => '',
                'diff_seconds' => null,
            ];
        }

        try {
            $clientDate = new \DateTimeImmutable($clientIso);
        } catch (\Throwable) {
            $this->lockWheelForFraud($session, self::WHEEL_SECURITY_MESSAGE);

            return [
                'anomaly' => true,
                'message' => self::WHEEL_SECURITY_MESSAGE,
                'server_iso' => $serverNow->format(\DateTimeInterface::ATOM),
                'server_date' => $serverNow->format('Y-m-d H:i:s'),
                'client_iso' => $clientIso,
                'diff_seconds' => null,
            ];
        }

        $serverDate = $serverNow->format('Y-m-d');
        $clientDateOnly = $clientDate->setTimezone($serverNow->getTimezone())->format('Y-m-d');
        $diffSeconds = abs($serverNow->getTimestamp() - $clientDate->getTimestamp());
        $anomaly = $serverDate !== $clientDateOnly;

        if ($anomaly) {
            $this->lockWheelForFraud($session, self::WHEEL_SECURITY_MESSAGE);
        } else {
            $this->unlockWheelSecurity($session);
        }

        return [
            'anomaly' => $anomaly,
            'message' => $anomaly
                ? self::WHEEL_SECURITY_MESSAGE
                : 'Date locale conforme à la date de référence.',
            'server_iso' => $serverNow->format(\DateTimeInterface::ATOM),
            'server_date' => $serverNow->format('Y-m-d H:i:s'),
            'client_iso' => $clientDate->format(\DateTimeInterface::ATOM),
            'diff_seconds' => $diffSeconds,
        ];
    }

    private function lockWheelForFraud(SessionInterface $session, string $message): void
    {
        $session->set(self::WHEEL_SECURITY_SESSION_KEY, [
            'locked' => true,
            'message' => $message,
            'detected_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);
    }

    private function unlockWheelSecurity(SessionInterface $session): void
    {
        $session->remove(self::WHEEL_SECURITY_SESSION_KEY);
    }

    private function isWheelSecurityLocked(SessionInterface $session): bool
    {
        $state = $session->get(self::WHEEL_SECURITY_SESSION_KEY, []);

        return (bool) ($state['locked'] ?? false);
    }

    private function getWheelSecurityMessage(SessionInterface $session): string
    {
        $state = $session->get(self::WHEEL_SECURITY_SESSION_KEY, []);

        return trim((string) ($state['message'] ?? '')) !== ''
            ? (string) $state['message']
            : self::WHEEL_SECURITY_MESSAGE;
    }

    private function setWheelFeedback(SessionInterface $session, string $type, string $message): void
    {
        $normalizedType = in_array($type, ['success', 'info', 'warning', 'error'], true) ? $type : 'info';

        $session->set(self::WHEEL_FEEDBACK_SESSION_KEY, [
            'type' => $normalizedType,
            'message' => trim($message),
            'created_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveWheelFeedbackFromException(\Throwable $exception): array
    {
        $message = trim($exception->getMessage());
        $normalized = strtolower($message);

        if (str_contains($normalized, 'une seule fois par mois')) {
            return ['info', 'Vous avez deja joue ce mois-ci.'];
        }

        if (str_contains($normalized, 'trois conditions')) {
            return ['warning', $message];
        }

        return ['error', $message !== '' ? $message : 'Action roue indisponible pour le moment.'];
    }

    private function getTrustedReferenceNow(): \DateTimeImmutable
    {
        foreach ([
            'https://worldtimeapi.org/api/timezone/Etc/UTC',
            'https://timeapi.io/api/Time/current/zone?timeZone=UTC',
        ] as $endpoint) {
            try {
                $response = $this->httpClient->request('GET', $endpoint, [
                    'timeout' => 8,
                    'verify_peer' => false,
                    'verify_host' => false,
                ]);

                if ($response->getStatusCode() !== 200) {
                    continue;
                }

                $payload = $response->toArray(false);
                $candidate = trim((string) ($payload['utc_datetime'] ?? $payload['datetime'] ?? $payload['dateTime'] ?? ''));
                if ($candidate === '') {
                    continue;
                }

                return new \DateTimeImmutable($candidate);
            } catch (\Throwable) {
                continue;
            }
        }

        return new \DateTimeImmutable('now');
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     */
    private function jsonEncodeForPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return is_string($json) ? $json : '[]';
    }

    private function buildProfileAiData(int $userId, InsightsService $insightsService): array
    {
        return [
            'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'security_analysis' => $insightsService->getAccountSecurityAnalysis($userId),
            'prediction' => $insightsService->getSpendingPrediction($userId),
            'account_advice' => $insightsService->getAccountAdvisor($userId),
            'cashback_advice' => $insightsService->getCashbackAdvisor($userId),
            'surplus' => $insightsService->detectMonthlySurplus($userId),
        ];
    }

    private function handleProfileImageUpload(Request $request, string $fieldName): ?string
    {
        $file = $request->files->get($fieldName);
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \RuntimeException('Profile image upload failed.');
        }

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $mimeType = (string) $file->getMimeType();
        if (!array_key_exists($mimeType, $allowedMimeTypes)) {
            throw new \InvalidArgumentException('Only JPG, PNG, WEBP or GIF profile images are allowed.');
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $targetDirectory = $projectDir.'/public/uploads/profile';
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Unable to create profile upload directory.');
        }

        $fileName = sprintf('profile_%s.%s', bin2hex(random_bytes(10)), $allowedMimeTypes[$mimeType]);
        $file->move($targetDirectory, $fileName);

        return 'uploads/profile/'.$fileName;
    }

    private function resolvePortalTabTemplate(string $tab): string
    {
        return match ($tab) {
            'accounts' => 'interfaces/portal/tabs/accounts.html.twig',
            'transactions' => 'interfaces/portal/tabs/transactions.html.twig',
            'credits' => 'interfaces/portal/tabs/credits.html.twig',
            'cashback' => 'interfaces/portal/tabs/cashback.html.twig',
            'garanties' => 'interfaces/portal/tabs/garanties.html.twig',
            'complaints' => 'interfaces/portal/tabs/complaints.html.twig',
            'vaults' => 'interfaces/portal/tabs/vaults.html.twig',
            'profile' => 'interfaces/portal/tabs/profile.html.twig',
            'notifications' => 'interfaces/portal/tabs/notifications.html.twig',
            default => 'interfaces/portal/tabs/dashboard.html.twig',
        };
    }

    private function resolvePortalTabStylesheets(string $tab): array
    {
        return match ($tab) {
            'dashboard' => ['styles/interfaces/sections/portal-dashboard.css'],
            'accounts' => ['styles/interfaces/sections/portal-accounts.css'],
            'transactions' => ['styles/interfaces/sections/portal-transactions.css'],
            'credits' => ['styles/interfaces/sections/portal-credits.css'],
            'profile' => ['styles/interfaces/sections/portal-profile.css'],
            default => [],
        };
    }
}
