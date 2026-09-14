<?php
require_once __DIR__ . '/../includes/deadline_alerts.php';

$now = new DateTimeImmutable('2026-09-09 12:00:00');
$cases = [
    'overdue review' => [['workflow_state' => 'For Review', 'application_type' => 'senior', 'workflow_changed_at' => '2026-09-05'], 'review', 'danger'],
    'expired correction' => [['workflow_state' => 'Needs Correction', 'application_type' => 'senior', 'workflow_changed_at' => '2026-09-02'], 'correction', 'danger'],
    'overdue home visit' => [['workflow_state' => 'Received', 'application_type' => 'pension', 'home_visit_status' => 'Scheduled', 'home_visit_scheduled_at' => '2026-09-09 08:00:00'], 'home_visit', 'danger'],
    'unreleased approved benefit' => [['workflow_state' => 'Approved', 'application_type' => 'milestone_gift', 'workflow_changed_at' => '2026-09-01'], 'benefit_release', 'danger'],
    'burial filing deadline' => [['workflow_state' => 'For Review', 'application_type' => 'burial', 'date_of_death' => '2026-08-28', 'date_submitted' => '2026-09-01', 'workflow_changed_at' => '2026-09-09'], 'burial_deadline', 'info'],
];

foreach ($cases as $name => [$application, $expectedType, $expectedLevel]) {
    $alerts = applicationDeadlineAlerts($application, $now);
    $match = array_values(array_filter($alerts, static fn($alert) => $alert['type'] === $expectedType));
    if (!$match || $match[0]['level'] !== $expectedLevel) {
        fwrite(STDERR, "FAIL {$name}\n");
        exit(1);
    }
    echo "PASS {$name}\n";
}

