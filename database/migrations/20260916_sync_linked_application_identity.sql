-- Synchronize existing service applications with their permanent Senior ID profile.
-- Senior-type child requests are deliberately excluded because they may contain
-- pending change details that must remain available to reviewers.
UPDATE applications child
JOIN applications senior ON senior.id_number = child.parent_senior_id
SET child.full_name = senior.full_name,
    child.lastName = senior.lastName,
    child.firstName = senior.firstName,
    child.middleName = senior.middleName,
    child.suffix = senior.suffix,
    child.birth_date = senior.birth_date,
    child.contact_number = senior.contact_number,
    child.complete_address = senior.complete_address,
    child.email_address = senior.email_address,
    child.place_of_birth = senior.place_of_birth,
    child.gender = senior.gender,
    child.civil_status = senior.civil_status,
    child.mothers_maiden_name = senior.mothers_maiden_name,
    child.house_no = senior.house_no,
    child.street = senior.street,
    child.zip_code = senior.zip_code,
    child.landmark = senior.landmark,
    child.health_status = senior.health_status,
    child.health_condition = senior.health_condition,
    child.emergency_contact_name = senior.emergency_contact_name,
    child.emergency_contact = senior.emergency_contact,
    child.claimant_relationship = senior.claimant_relationship
WHERE child.application_type <> 'senior'
  AND COALESCE(child.is_archived, 0) = 0
  AND senior.application_type = 'senior'
  AND COALESCE(senior.is_archived, 0) = 0;
