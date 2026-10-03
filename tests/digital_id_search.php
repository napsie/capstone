<?php

require_once __DIR__ . '/../includes/digital_id_search.php';

function expectDigitalIdSearch(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

expectDigitalIdSearch(normalizeDigitalIdSearch("  Alberto   Santos  ") === 'Alberto Santos', 'Whitespace normalization failed.');
$name = buildDigitalIdSearch('Alberto Santos');
expectDigitalIdSearch(substr_count($name['sql'], " ESCAPE '!'") === 10, 'Both name terms must be searchable across all fields.');
expectDigitalIdSearch(count($name['params']) === 10, 'Unexpected parameter count for a two-word name.');
$token = buildDigitalIdSearch('PRX-B9T3');
expectDigitalIdSearch(in_array('%PRXB9T3%', $token['params'], true), 'Dash-insensitive token search was not generated.');
$literal = buildDigitalIdSearch('100%_match!');
expectDigitalIdSearch(in_array('%100!%!_match!!%', $literal['params'], true), 'LIKE metacharacters were not escaped.');
$empty = buildDigitalIdSearch('   ');
expectDigitalIdSearch($empty['sql'] === '' && $empty['params'] === [], 'Empty search should not add a query condition.');

echo "Digital ID search tests passed.\n";
