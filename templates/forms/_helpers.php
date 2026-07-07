<?php
/**
 * Shared helpers for official OSCA printable forms.
 * Expects $app array from applications table.
 */

function oscaFmtDate(?string $d, string $format = 'm/d/Y'): string {
    if (!$d) return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format($format) : htmlspecialchars($d);
}

function oscaVal(?string $v, string $fallback = ''): string {
    return htmlspecialchars($v ?: $fallback);
}

function oscaCheck(bool $cond): string {
    return $cond ? '☑' : '☐';
}

function oscaFullName(array $app, string $prefix = ''): string {
    $ln = $app[$prefix . 'lastName'] ?? $app['lastName'] ?? '';
    $fn = $app[$prefix . 'firstName'] ?? $app['firstName'] ?? '';
    $mn = $app[$prefix . 'middleName'] ?? $app['middleName'] ?? '';
    $sx = $app[$prefix . 'suffix'] ?? $app['suffix'] ?? '';
    return trim("$fn $mn $ln $sx");
}

function oscaAge(?string $birthDate): string {
    if (!$birthDate) return '';
    $bd = new DateTime($birthDate);
    return (string)(new DateTime())->diff($bd)->y;
}

function oscaPrintStyles(string $formCode): string {
    return <<<CSS
    @page { size: letter; margin: 0.4in; }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 12px; }
    .form-page { max-width: 8in; margin: 0 auto; background: #fff; }
    .form-header { text-align: center; margin-bottom: 8px; }
    .form-header .gov { font-size: 10px; }
    .form-header .agency { font-size: 11px; font-weight: bold; }
    .form-header h1 { font-size: 13px; margin: 4px 0; text-transform: uppercase; }
    .form-code { float: left; border: 2px solid #000; padding: 2px 8px; font-weight: bold; font-size: 12px; }
    .form-meta { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .field-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .field-table td, .field-table th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
    .field-table th { background: #f0f0f0; font-size: 9px; text-transform: uppercase; text-align: left; width: 18%; }
    .field-table .val { min-height: 18px; font-size: 11px; font-weight: 500; }
    .section-title { font-weight: bold; font-size: 10px; background: #e8e8e8; padding: 3px 6px; border: 1px solid #000; margin: 8px 0 4px; }
    .checkbox-row { margin: 4px 0; }
    .cert-box { border: 1px solid #000; padding: 8px; font-size: 9px; line-height: 1.4; margin: 10px 0; }
    .sig-line { border-bottom: 1px solid #000; margin-top: 30px; padding-top: 4px; font-size: 9px; text-align: center; }
    .stub { border-top: 2px dashed #000; margin-top: 20px; padding-top: 8px; font-size: 9px; }
    .print-bar { margin-bottom: 12px; }
    .print-bar button { padding: 8px 16px; margin-right: 8px; cursor: pointer; }
    @media print { .print-bar { display: none; } body { padding: 0; } }
    CSS;
}

function oscaPrintHeader(string $formCode, string $title): void {
    echo '<div class="form-meta"><div class="form-code">' . htmlspecialchars($formCode) . '</div><div style="flex:1"></div></div>';
    echo '<div class="form-header">';
    echo '<div class="gov">Republic of the Philippines</div>';
    echo '<div class="agency">City Government of Pasig</div>';
    echo '<div class="agency">OFFICE FOR THE SENIOR CITIZENS AFFAIRS (OSCA)</div>';
    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    echo '</div>';
}

function oscaFieldRow(array $fields): void {
    echo '<table class="field-table"><tr>';
    foreach ($fields as $label => $value) {
        echo '<th>' . htmlspecialchars($label) . '</th><td class="val">' . oscaVal($value) . '</td>';
    }
    echo '</tr></table>';
}
