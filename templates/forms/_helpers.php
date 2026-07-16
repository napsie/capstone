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
    @page { size: letter; margin: 0.35in; }
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; color: #172033; margin: 0; padding: 20px; background: #eef3f9; }
    .form-page { max-width: 8in; margin: 0 auto; background: #fff; border: 1px solid #d6e0ec; border-radius: 14px; overflow: hidden; box-shadow: 0 12px 32px rgba(15,23,42,.12); padding: 22px 24px; }
    .form-header { text-align: center; margin: -22px -24px 18px; padding: 19px 24px 16px; background: linear-gradient(135deg, #102a4c, #234b78); color: #fff; border-bottom: 4px solid #3b82f6; }
    .form-header .gov { font-size: 10px; opacity: .86; }
    .form-header .agency { font-size: 11px; font-weight: 700; letter-spacing: .015em; }
    .form-header h1 { font-size: 15px; margin: 6px 0 0; text-transform: uppercase; letter-spacing: .01em; }
    .form-code { display: none; }
    .form-meta { display: none; }
    .field-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0 0 9px; border: 1px solid #d6e0ec; border-radius: 8px; overflow: hidden; }
    .field-table td, .field-table th { border-right: 1px solid #d6e0ec; border-bottom: 1px solid #d6e0ec; padding: 7px 8px; vertical-align: top; }
    .field-table tr:last-child td, .field-table tr:last-child th { border-bottom: none; }
    .field-table td:last-child, .field-table th:last-child { border-right: none; }
    .field-table th { background: #f1f5f9; font-size: 8px; letter-spacing: .045em; text-transform: uppercase; text-align: left; color: #51627a; width: 18%; }
    .field-table .val { min-height: 20px; font-size: 11px; font-weight: 600; color: #172033; }
    .section-title { font-weight: 800; font-size: 10px; color: #173b66; background: #eaf2fc; padding: 7px 9px; border: 1px solid #cbdced; border-radius: 7px; margin: 14px 0 7px; letter-spacing: .03em; }
    .checkbox-row { margin: 8px 0; padding: 8px 10px; background: #f8fbff; border: 1px solid #dce8f5; border-radius: 7px; line-height: 1.55; }
    .cert-box { border: 1px solid #d6e0ec; border-left: 4px solid #3b82f6; border-radius: 7px; padding: 10px 12px; font-size: 10px; line-height: 1.5; margin: 13px 0; background: #f8fbff; }
    .sig-line { border-bottom: 1px solid #64748b; margin: 34px auto 0; padding-top: 4px; font-size: 9px; text-align: center; max-width: 300px; }
    .stub { border-top: 2px dashed #94a3b8; margin-top: 22px; padding: 10px 0 0; font-size: 9px; color: #475569; }
    .print-bar { max-width: 8in; margin: 0 auto 12px; display:flex; gap:8px; }
    .print-bar button { padding: 9px 15px; border: 0; border-radius: 8px; background: #1e4775; color:#fff; font: 700 12px "Segoe UI", Arial, sans-serif; cursor: pointer; }
    .print-bar button + button { background: #64748b; }
    @media print { .print-bar { display: none; } body { padding: 0; background: #fff; } .form-page { max-width: none; border: 0; border-radius: 0; box-shadow: none; } }
    CSS;
}

function oscaPrintHeader(string $formCode, string $title): void {
    if ($formCode !== '') echo '<div class="form-meta"><div class="form-code">' . htmlspecialchars($formCode) . '</div><div style="flex:1"></div></div>';
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
