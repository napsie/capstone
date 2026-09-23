<?php

/**
 * Build the standardized SENIORLINK header used by every tabular PDF report.
 * Report generators pass only their report-specific title and filter metadata.
 */
function standardPdfReportHeaderCommands(array $report, callable $escape, callable $fit, bool $hasLogo): array {
    $commands = [
        '0.04 0.14 0.28 rg 0 587 842 8 re f',
        '0.96 0.98 1 rg 35 496 72 72 re f',
        '0.77 0.83 0.90 RG 0.8 w 35 496 72 72 re S',
        '0.96 0.98 1 rg 700 496 107 72 re f',
        '0.77 0.83 0.90 RG 0.8 w 700 496 107 72 re S',
    ];
    if ($hasLogo) {
        $logoWidth = max(1.0, (float)($report['logo_width'] ?? 64));
        $logoHeight = max(1.0, (float)($report['logo_height'] ?? 64));
        $logoX = 39 + ((64 - $logoWidth) / 2);
        $logoY = 500 + ((64 - $logoHeight) / 2);
        $commands[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Logo Do Q', $logoWidth, $logoHeight, $logoX, $logoY);
    }
    if (!empty($report['has_osca_logo'])) {
        $sealWidth = max(1.0, (float)($report['osca_logo_width'] ?? 64));
        $sealHeight = max(1.0, (float)($report['osca_logo_height'] ?? 64));
        $sealX = 753 + ((54 - $sealWidth) / 2);
        $sealY = 500 + ((64 - $sealHeight) / 2);
        $commands[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /OSCA Do Q', $sealWidth, $sealHeight, $sealX, $sealY);
    }
    $coverage = preg_replace('/^Barangay Coverage:\s*/i', '', (string)($report['coverage'] ?? 'Pasig City'));
    $jurisdiction = strcasecmp($coverage, 'All Barangays') === 0 ? 'PASIG CITY' : 'BARANGAY ' . strtoupper($coverage);
    $commands[] = '0.28 0.35 0.43 rg BT /F2 8.5 Tf 304 553 Td (REPUBLIC OF THE PHILIPPINES) Tj ET';
    $commands[] = '0.05 0.17 0.32 rg BT /F2 16 Tf 257 533 Td (CITY GOVERNMENT OF PASIG) Tj ET';
    $commands[] = 'BT /F1 10.5 Tf 292 515 Td (OFFICE FOR SENIOR CITIZENS AFFAIRS) Tj ET';
    $commands[] = '0.04 0.31 0.22 rg BT /F2 11.5 Tf 320 497 Td (' . $escape($jurisdiction) . ') Tj ET';
    $commands[] = '0.28 0.35 0.43 rg BT /F3 8.5 Tf 254 487 Td (SENIORLINK - Centralized Profiling and Record Management System) Tj ET';
    $commands[] = '0.08 0.28 0.50 RG 1.2 w 35 484 m 807 484 l S';
    $commands[] = '0.05 0.17 0.32 rg BT /F2 14.5 Tf 36 460 Td (' . $escape($report['title']) . ') Tj ET';
    $commands[] = '0.30 0.38 0.46 rg BT /F3 9 Tf 36 444 Td (' . $escape($report['subtitle']) . ') Tj ET';
    $commands[] = '0.96 0.98 1 rg 35 394 772 38 re f';
    $commands[] = '0.79 0.85 0.91 RG 0.6 w 35 394 772 38 re S';
    $commands[] = '0.34 0.42 0.51 rg BT /F2 6.8 Tf 48 417 Td (GENERATED DATE) Tj ET';
    $commands[] = 'BT /F2 6.8 Tf 277 417 Td (BARANGAY COVERAGE) Tj ET';
    $commands[] = 'BT /F2 6.8 Tf 505 417 Td (APPLIED FILTERS) Tj ET';
    $commands[] = '0.10 0.18 0.28 rg BT /F1 8 Tf 48 403 Td (' . $escape(preg_replace('/^Generated:\s*/i', '', $report['generated'])) . ') Tj ET';
    $commands[] = 'BT /F1 8 Tf 277 403 Td (' . $escape($fit(preg_replace('/^Barangay Coverage:\s*/i', '', $report['coverage']), 35)) . ') Tj ET';
    $commands[] = 'BT /F1 8 Tf 505 403 Td (' . $escape($fit(preg_replace('/^Applied Filters:\s*/i', '', $report['filters']), 48)) . ') Tj ET';
    if (isset($report['page'], $report['total_pages'])) {
        $commands[] = '0.34 0.42 0.51 rg BT /F3 8 Tf 700 24 Td (' . $escape('Page ' . (int)$report['page'] . ' of ' . (int)$report['total_pages']) . ') Tj ET';
    }
    return $commands;
}
