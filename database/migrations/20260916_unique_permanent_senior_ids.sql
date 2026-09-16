-- Linked service requests intentionally repeat the senior's PRX token and ID.
-- Only the original senior record owns each permanent identity. NULL values
-- allow applications awaiting an issued ID to coexist.
ALTER TABLE applications
  ADD COLUMN IF NOT EXISTS root_prx_identity VARCHAR(255)
    GENERATED ALWAYS AS (
      CASE WHEN application_type = 'senior' AND COALESCE(parent_senior_id, '') = ''
        THEN NULLIF(UPPER(TRIM(proxy_token)), '') ELSE NULL END
    ) PERSISTENT,
  ADD COLUMN IF NOT EXISTS root_senior_id_identity VARCHAR(50)
    GENERATED ALWAYS AS (
      CASE WHEN application_type = 'senior' AND COALESCE(parent_senior_id, '') = ''
        THEN NULLIF(UPPER(TRIM(senior_id_no)), '') ELSE NULL END
    ) PERSISTENT,
  ADD UNIQUE INDEX IF NOT EXISTS uq_root_prx_identity (root_prx_identity),
  ADD UNIQUE INDEX IF NOT EXISTS uq_root_senior_id_identity (root_senior_id_identity);
