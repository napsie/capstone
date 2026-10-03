<?php

/** Normalize a user-entered Digital ID search without changing its meaning. */
function normalizeDigitalIdSearch(string $search): string
{
    $search = trim((string)preg_replace('/\s+/u', ' ', $search));
    return mb_strlen($search) > 100 ? mb_substr($search, 0, 100) : $search;
}

/** Escape LIKE metacharacters so %, _ and ! are searched as ordinary text. */
function digitalIdLikePattern(string $value): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
}

/**
 * Build a driver-neutral search condition for names, permanent IDs, and
 * application tokens. Every entered word must match at least one field.
 */
function buildDigitalIdSearch(string $search): array
{
    $search = normalizeDigitalIdSearch($search);
    if ($search === '') return ['sql' => '', 'params' => []];

    $terms = array_slice(preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 12);
    $conditions = [];
    $params = [];
    foreach ($terms as $term) {
        $pattern = digitalIdLikePattern($term);
        $compact = preg_replace('/[-\s]+/u', '', $term) ?? $term;
        $compactPattern = digitalIdLikePattern($compact);
        $conditions[] = "(LOWER(full_name) LIKE LOWER(?) ESCAPE '!'"
            . " OR LOWER(COALESCE(senior_id_no, '')) LIKE LOWER(?) ESCAPE '!'"
            . " OR LOWER(id_number) LIKE LOWER(?) ESCAPE '!'"
            . " OR LOWER(REPLACE(REPLACE(COALESCE(senior_id_no, ''), '-', ''), ' ', '')) LIKE LOWER(?) ESCAPE '!'"
            . " OR LOWER(REPLACE(REPLACE(id_number, '-', ''), ' ', '')) LIKE LOWER(?) ESCAPE '!')";
        array_push($params, $pattern, $pattern, $pattern, $compactPattern, $compactPattern);
    }
    return ['sql' => '(' . implode(' AND ', $conditions) . ')', 'params' => $params];
}
