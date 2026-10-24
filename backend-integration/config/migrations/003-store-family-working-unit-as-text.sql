-- Preserve the exact Unit text returned from employee_employment for family members.
-- Existing integer values are retained as text; their original labels cannot be
-- reconstructed because the current schema has no Unit lookup table.

ALTER TABLE employee_referrals
    MODIFY working_unit VARCHAR(255) NOT NULL;

ALTER TABLE temp_employee_referrals
    MODIFY working_unit VARCHAR(255) NULL DEFAULT NULL;
