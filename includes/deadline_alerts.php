<?php
require_once __DIR__ . '/filing_deadline.php';

const REVIEW_WARNING_DAYS = 2;
const REVIEW_OVERDUE_DAYS = 3;
const CORRECTION_WARNING_DAYS = 5;
const CORRECTION_EXPIRY_DAYS = 7;
const BENEFIT_RELEASE_WARNING_DAYS = 3;
const BENEFIT_RELEASE_OVERDUE_DAYS = 5;

function deadlineAlertAgeDays(?string $date, DateTimeImmutable $now): ?int {
    if (!$date) return null;
    try {
        $then = new DateTimeImmutable($date);
        return max(0, (int)$then->setTime(0, 0)->diff($now->setTime(0, 0))->format('%r%a'));
    } catch (Throwable $e) {
        return null;
    }
}

function applicationDeadlineAlerts(array $application, ?DateTimeImmutable $now = null): array {
    $now = $now ?: new DateTimeImmutable('now');
    $state = trim((string)($application['workflow_state'] ?? $application['effective_state'] ?? $application['status'] ?? 'Received'));
    $type = trim((string)($application['application_type'] ?? ''));
    $changedAt = $application['workflow_changed_at'] ?? $application['date_submitted'] ?? null;
    $ageDays = deadlineAlertAgeDays($changedAt, $now);
    $alerts = [];

    if ($type === 'burial' && !empty($application['date_of_death'])) {
        $filingStart = $application['date_of_death'];
        $filingDays = filingWorkingDays((string)$filingStart, (string)($application['date_submitted'] ?? $now->format('Y-m-d')));
        if ($filingDays !== null) {
            $remaining = 30 - $filingDays;
            $alerts[] = [
                'type' => 'burial_deadline',
                'level' => $remaining < 0 ? 'danger' : ($remaining <= 5 ? 'warning' : 'info'),
                'label' => $remaining < 0 ? 'Burial filing overdue' : "Burial deadline: {$remaining} working day" . ($remaining === 1 ? '' : 's') . ' left',
                'detail' => "Filed {$filingDays} of 30 working days after the date of passing.",
            ];
        }
    }

    if ($state === 'For Review' && $ageDays !== null && $ageDays >= REVIEW_WARNING_DAYS) {
        $overdue = $ageDays >= REVIEW_OVERDUE_DAYS;
        $alerts[] = [
            'type' => 'review', 'level' => $overdue ? 'danger' : 'warning',
            'label' => $overdue ? "Review overdue by " . ($ageDays - REVIEW_OVERDUE_DAYS + 1) . ' day(s)' : 'Review due soon',
            'detail' => "Waiting for department review for {$ageDays} day(s).",
        ];
    }

    $visitStatus = trim((string)($application['home_visit_status'] ?? ''));
    $scheduledAt = $application['home_visit_scheduled_at'] ?? null;
    if ($scheduledAt && !in_array($visitStatus, ['Completed', 'Rejected', 'Cancelled'], true)) {
        try {
            $schedule = new DateTimeImmutable((string)$scheduledAt);
            $seconds = $schedule->getTimestamp() - $now->getTimestamp();
            $alerts[] = [
                'type' => 'home_visit',
                'level' => $seconds < 0 ? 'danger' : ($seconds <= 86400 ? 'warning' : 'info'),
                'label' => $seconds < 0 ? 'Home visit overdue' : ($seconds <= 86400 ? 'Home visit within 24 hours' : 'Home visit scheduled'),
                'detail' => $schedule->format('M j, Y g:i A'),
            ];
        } catch (Throwable $e) { /* Ignore invalid legacy schedules. */ }
    }

    if ($state === 'Needs Correction' && $ageDays !== null && $ageDays >= CORRECTION_WARNING_DAYS) {
        $remaining = CORRECTION_EXPIRY_DAYS - $ageDays;
        $alerts[] = [
            'type' => 'correction', 'level' => $remaining <= 0 ? 'danger' : 'warning',
            'label' => $remaining <= 0 ? 'Correction period expired' : "Correction expires in {$remaining} day(s)",
            'detail' => 'Follow up with the barangay applicant before the correction window closes.',
        ];
    }

    $cashBenefitTypes = ['pension', 'national_pension', 'milestone_gift', 'burial'];
    if (in_array($type, $cashBenefitTypes, true) && $state === 'Approved'
        && $ageDays !== null && $ageDays >= BENEFIT_RELEASE_WARNING_DAYS) {
        $overdue = $ageDays >= BENEFIT_RELEASE_OVERDUE_DAYS;
        $alerts[] = [
            'type' => 'benefit_release', 'level' => $overdue ? 'danger' : 'warning',
            'label' => $overdue ? 'Approved benefit not released' : 'Benefit release due soon',
            'detail' => "Approved {$ageDays} day(s) ago and not marked Released.",
        ];
    }

    usort($alerts, static fn($a, $b) => (['danger' => 0, 'warning' => 1, 'info' => 2][$a['level']] ?? 3)
        <=> (['danger' => 0, 'warning' => 1, 'info' => 2][$b['level']] ?? 3));
    return $alerts;
}
