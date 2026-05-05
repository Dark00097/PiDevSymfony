<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Nucleos\DompdfBundle\Factory\DompdfFactoryInterface;

final class ExportService
{
    public function __construct(
        private readonly DompdfFactoryInterface $dompdfFactory,
    ) {
    }

    public function buildPdf(
        string $title,
        array $headers,
        array $rows,
        array $stats = [],
        ?string $subtitle = null,
        string $accent = '#1565c0'
    ): string
    {
        $dompdf = $this->dompdfFactory->create();
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);
        $safeSubtitle = htmlspecialchars($subtitle ?? 'Export detaille genere depuis Nexora.', ENT_QUOTES);
        $generatedAt = date('Y-m-d H:i:s');
        $safeAccent = htmlspecialchars($accent, ENT_QUOTES);
        $columnCount = max(1, count($headers));
        $paperOrientation = $columnCount > 6 ? 'landscape' : 'portrait';
        $tableFontSize = $columnCount > 12 ? 8.5 : ($columnCount > 8 ? 9.5 : 10.5);

        $html = '<html><head><meta charset="UTF-8">';
        $html .= sprintf(
            '<style>
                @page { margin: 16px; }
                body {
                    font-family: DejaVu Sans, sans-serif;
                    color: #0a2540;
                    margin: 0;
                    background: #eef4f8;
                }
                .nx-export-table {
                    width: 100%%;
                    border-collapse: collapse;
                    table-layout: fixed;
                    margin-top: 8px;
                    font-size: %.1fpx;
                }
                .nx-export-table thead {
                    display: table-header-group;
                }
                .nx-export-table tr {
                    page-break-inside: avoid;
                }
                .nx-export-table th,
                .nx-export-table td {
                    border: 1px solid #dbe3ef;
                    padding: 7px 8px;
                    text-align: left;
                    vertical-align: top;
                    overflow-wrap: anywhere;
                    word-break: break-word;
                    white-space: normal;
                    line-height: 1.3;
                }
                .nx-export-table th {
                    background: #edf4fb;
                    font-weight: 800;
                    color: #0a2540;
                }
             </style>',
            $tableFontSize
        );
        $html .= '</head><body>';
        $html .= '<div style="padding:28px 32px 36px;">';
        $html .= sprintf(
            '<div style="background:#0a2540; color:#ffffff; border-top:6px solid %s; border-radius:18px; padding:24px 28px; box-shadow:0 10px 30px rgba(10,37,64,.18);">',
            $safeAccent
        );
        $html .= '<div style="font-size:12px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:#b9d4ec;">Nexora Export</div>';
        $html .= sprintf('<h1 style="margin:10px 0 8px; font-size:30px; line-height:1.15;">%s</h1>', $safeTitle);
        $html .= sprintf('<div style="font-size:14px; color:#d8e6f3; margin-bottom:10px;">%s</div>', $safeSubtitle);
        $html .= sprintf('<div style="font-size:12px; color:#b9d4ec;">Generated at %s</div>', htmlspecialchars($generatedAt, ENT_QUOTES));
        $html .= '</div>';

        if ($stats !== []) {
            $html .= '<div style="margin-top:18px; margin-bottom:4px;">';
            foreach ($stats as $stat) {
                $label = htmlspecialchars((string) ($stat['label'] ?? ''), ENT_QUOTES);
                $value = htmlspecialchars((string) ($stat['value'] ?? ''), ENT_QUOTES);
                $html .= '<div style="display:inline-block; width:30%; margin-right:3%; margin-bottom:12px; vertical-align:top;">';
                $html .= sprintf('<div style="background:#ffffff; border:1px solid #cfe0f1; border-top:4px solid %s; border-radius:16px; padding:14px 16px;">', $safeAccent);
                $html .= sprintf('<div style="font-size:11px; color:#5d7f9a; text-transform:uppercase; letter-spacing:.7px; margin-bottom:6px;">%s</div>', $label);
                $html .= sprintf('<div style="font-size:22px; font-weight:800; color:#0a2540;">%s</div>', $value);
                $html .= '</div></div>';
            }
            $html .= '</div>';
        }

        $html .= '<div style="background:#ffffff; border:1px solid #dbe7f3; border-radius:18px; padding:18px; margin-top:18px;">';
        $html .= sprintf('<div style="font-size:13px; font-weight:800; color:#0a2540; text-transform:uppercase; letter-spacing:.8px; margin-bottom:12px; border-left:4px solid %s; padding-left:10px;">Details</div>', $safeAccent);
        $html .= '<table class="nx-export-table">';
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= sprintf(
                '<th>%s</th>',
                htmlspecialchars($header, ENT_QUOTES)
            );
        }
        $html .= '</tr></thead><tbody>';
        if ($rows === []) {
            $html .= sprintf(
                '<tr><td colspan="%d" style="padding:18px 12px; text-align:center; color:#6b86a0;">Aucune donnee disponible.</td></tr>',
                count($headers)
            );
        }
        foreach ($rows as $index => $row) {
            $bg = $index % 2 === 0 ? '#ffffff' : '#f9fbfe';
            $html .= sprintf('<tr style="background:%s;">', $bg);
            foreach ($row as $cell) {
                $html .= sprintf(
                    '<td>%s</td>',
                    htmlspecialchars((string) $cell, ENT_QUOTES)
                );
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '</div>';
        $html .= '</div></body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $paperOrientation);
        $dompdf->render();

        return $dompdf->output();
    }

    public function buildTransactionQrSvg(array $transaction): string
    {
        $payload = sprintf(
            "Transaction #%s\nCategory: %s\nDate: %s\nAmount: %s DT\nStatus: %s",
            $transaction['idTransaction'] ?? '-',
            $transaction['categorie'] ?? '-',
            $transaction['dateTransaction'] ?? '-',
            $transaction['montant_value'] ?? '0.00',
            $transaction['statutTransaction'] ?? '-'
        );

        $builder = new Builder(
            writer: new SvgWriter(),
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 260,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $result = $builder->build();

        return $result->getString();
    }

    /**
     * @param array<string, mixed> $bundle
     */
    public function buildCashbackBundlePdf(array $bundle, string $hash): string
    {
        $dompdf = $this->dompdfFactory->create();
        $summary = (array) ($bundle['summary'] ?? []);
        $history = is_array($bundle['history'] ?? null) ? $bundle['history'] : [];
        $recommended = is_array($bundle['recommended_partners'] ?? null) ? $bundle['recommended_partners'] : [];
        $generatedAt = htmlspecialchars((string) ($bundle['generated_at'] ?? date('c')), ENT_QUOTES);
        $safeHash = htmlspecialchars($hash, ENT_QUOTES);

        $html = '<html><head><meta charset="UTF-8"><style>
            @page { margin: 18px; }
            body { font-family: DejaVu Sans, sans-serif; color:#10233b; background:#eef5fb; margin:0; }
            .page { padding: 26px 28px 34px; }
            .hero { background:linear-gradient(135deg,#0f766e,#0891b2,#2563eb); color:#fff; border-radius:20px; padding:22px 24px; }
            .hero h1 { margin:8px 0 6px; font-size:28px; }
            .hero small { color:rgba(255,255,255,.8); font-size:12px; }
            .kpis { margin-top:18px; }
            .kpi { display:inline-block; width:22%; margin-right:2%; margin-bottom:12px; vertical-align:top; background:#fff; border:1px solid #d9e6f3; border-radius:16px; padding:14px 16px; }
            .kpi span { display:block; font-size:11px; text-transform:uppercase; color:#688199; margin-bottom:6px; }
            .kpi strong { font-size:21px; color:#10233b; }
            .section { margin-top:20px; background:#fff; border:1px solid #dbe7f3; border-radius:18px; padding:16px; }
            .section h2 { margin:0 0 12px; font-size:14px; text-transform:uppercase; color:#0f4c81; }
            table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10px; }
            th, td { border:1px solid #dbe3ef; padding:6px 7px; text-align:left; vertical-align:top; word-break:break-word; }
            th { background:#edf4fb; font-weight:800; }
            .muted { color:#60788e; font-size:12px; line-height:1.6; }
            .offer { margin-bottom:10px; padding:10px 12px; border:1px solid #dce8f5; border-radius:14px; background:#f8fbff; }
            .offer strong { display:block; font-size:13px; margin-bottom:4px; }
        </style></head><body><div class="page">';

        $html .= '<div class="hero">';
        $html .= '<small>Nexora Cashback Export</small>';
        $html .= '<h1>Historique cashback</h1>';
        $html .= '<div>Hash bundle: '.$safeHash.'</div>';
        $html .= '<div>Genere le '.$generatedAt.'</div>';
        $html .= '</div>';

        $kpis = [
            ['Cashback total', number_format((float) ($summary['total_cashback'] ?? 0), 2, '.', ' ').' DT'],
            ['Valide / credite', number_format((float) ($summary['approved_cashback'] ?? 0), 2, '.', ' ').' DT'],
            ['Dossiers', (string) ((int) ($summary['count'] ?? 0))],
            ['En attente', (string) ((int) ($summary['pending_count'] ?? 0))],
        ];
        $html .= '<div class="kpis">';
        foreach ($kpis as [$label, $value]) {
            $html .= '<div class="kpi"><span>'.htmlspecialchars($label, ENT_QUOTES).'</span><strong>'.htmlspecialchars($value, ENT_QUOTES).'</strong></div>';
        }
        $html .= '</div>';

        $html .= '<div class="section"><h2>Resume</h2><p class="muted">';
        $html .= 'Partenaire fort: '.htmlspecialchars((string) (($summary['best_partner'] ?? '') !== '' ? $summary['best_partner'] : 'Aucun'), ENT_QUOTES);
        $html .= '</p></div>';

        $html .= '<div class="section"><h2>Partenaires recommandes</h2>';
        if ($recommended === []) {
            $html .= '<p class="muted">Aucune recommandation disponible.</p>';
        } else {
            foreach (array_slice($recommended, 0, 5) as $partner) {
                if (!is_array($partner)) {
                    continue;
                }
                $name = htmlspecialchars((string) ($partner['name'] ?? 'Partenaire'), ENT_QUOTES);
                $city = trim((string) ($partner['city'] ?? ''));
                $html .= '<div class="offer">';
                $html .= '<strong>'.$name.($city !== '' ? ' - '.htmlspecialchars($city, ENT_QUOTES) : '').'</strong>';
                $html .= '<div class="muted">';
                $html .= 'Categorie: '.htmlspecialchars((string) ($partner['category'] ?? '-'), ENT_QUOTES).' | ';
                $html .= 'Cashback: '.number_format((float) ($partner['cashback'] ?? 0), 2, '.', ' ').'% | ';
                $html .= 'Cashback max: '.number_format((float) ($partner['cashback_max'] ?? 0), 2, '.', ' ').'% | ';
                $html .= 'Note: '.number_format((float) ($partner['rating'] ?? 0), 1, '.', ' ').'/5';
                $html .= '</div></div>';
            }
        }
        $html .= '</div>';

        $html .= '<div class="section"><h2>Historique complet</h2><table><thead><tr>';
        foreach (['ID', 'Partenaire', 'Ville', 'Achat', 'Cashback', 'Taux', 'Statut', 'Date achat', 'Date credit', 'Avis'] as $header) {
            $html .= '<th>'.htmlspecialchars($header, ENT_QUOTES).'</th>';
        }
        $html .= '</tr></thead><tbody>';

        if ($history === []) {
            $html .= '<tr><td colspan="10">Aucune donnee disponible.</td></tr>';
        } else {
            foreach ($history as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $html .= '<tr>';
                $html .= '<td>'.(int) ($entry['id_cashback'] ?? 0).'</td>';
                $html .= '<td>'.htmlspecialchars((string) ($entry['partner_name'] ?? '-'), ENT_QUOTES).'</td>';
                $html .= '<td>'.htmlspecialchars((string) ($entry['partner_city'] ?? '-'), ENT_QUOTES).'</td>';
                $html .= '<td>'.number_format((float) ($entry['purchase_amount'] ?? 0), 2, '.', ' ').' DT</td>';
                $html .= '<td>'.number_format((float) ($entry['cashback_amount'] ?? 0), 2, '.', ' ').' DT</td>';
                $html .= '<td>'.number_format((float) ($entry['rate'] ?? 0), 2, '.', ' ').'%</td>';
                $html .= '<td>'.htmlspecialchars((string) ($entry['status'] ?? '-'), ENT_QUOTES).'</td>';
                $html .= '<td>'.htmlspecialchars((string) ($entry['purchase_date'] ?? '-'), ENT_QUOTES).'</td>';
                $html .= '<td>'.htmlspecialchars((string) ($entry['credit_date'] ?? '-'), ENT_QUOTES).'</td>';
                $html .= '<td>'.htmlspecialchars((string) ($entry['user_rating_comment'] ?? '-'), ENT_QUOTES).'</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table></div>';
        $html .= '</div></body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
