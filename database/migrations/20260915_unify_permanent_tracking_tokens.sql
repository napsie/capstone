-- Reuse the original Senior ID registration token for every linked service.
-- Internal application IDs remain unique and continue to drive staff workflows.
UPDATE applications child
JOIN applications senior ON senior.id_number = child.parent_senior_id
SET child.proxy_token = COALESCE(NULLIF(senior.proxy_token, ''), senior.id_number)
WHERE child.parent_senior_id IS NOT NULL
  AND child.parent_senior_id <> ''
  AND COALESCE(child.proxy_token, '') <> COALESCE(NULLIF(senior.proxy_token, ''), senior.id_number);

