<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

final class ExportService
{
    public function buildPdf(
        string $title,
        array $headers,
        array $rows,
        array $stats = [],
        ?string $subtitle = null,
        string $accent = '#1565c0'
    ): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
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
}
