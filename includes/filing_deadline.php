<?php
/** Count weekdays from the recorded death date up to (excluding) filing day.
 * This preserves the intake form's counting convention; review time is irrelevant.
 * Invalid, missing, or reversed dates must not pass as zero-day filings.
 */
function filingWorkingDays(?string $startValue, ?string $filedValue): ?int
{
    $parse = static function (?string $value): ?DateTimeImmutable {
        $date = substr((string)$value, 0, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $parsed : null;
    };
    $start = $parse($startValue);
    $filed = $parse($filedValue);
    if (!$start || !$filed || $start > $filed) return null;
    $days = 0;
    for ($current = $start; $current < $filed; $current = $current->modify('+1 day')) {
        if ((int)$current->format('N') < 6) $days++;
    }
    return $days;
}
