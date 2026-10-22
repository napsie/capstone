<?php
/** Only unfinished, active applications belong in document verification. */
function verificationQueueCondition(): string
{
    return "(COALESCE(is_archived, 0) = 0
        AND LOWER(TRIM(COALESCE(NULLIF(TRIM(workflow_state), ''), NULLIF(TRIM(status), ''), 'Received')))
            IN ('received', 'submitted', 'for review', 'needs correction', 'pending')
        AND LOWER(TRIM(COALESCE(status, '')))
            NOT IN ('verified', 'approved', 'released', 'completed', 'done', 'deceased', 'rejected', 'cancelled')
    )";
}
