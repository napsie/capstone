-- Public registrations already keep their canonical documents in private
-- storage and reference those files from the applications table. Older
-- releases also copied every upload into application_documents, consuming
-- the small production database allocation until MySQL returned error 1114.
-- Remove only those redundant public-form copies; staff-uploaded document
-- versions and imported legacy documents remain untouched.
DELETE FROM application_documents
WHERE source = 'public_application';
