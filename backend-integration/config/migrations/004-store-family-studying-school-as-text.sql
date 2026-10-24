-- Store the selected family-studying school label instead of requiring a numeric ID.
-- Existing numeric values are retained as text because the schema has no school lookup table.

ALTER TABLE employee_referrals
    MODIFY student_unit VARCHAR(255) NOT NULL;

ALTER TABLE temp_employee_referrals
    MODIFY student_unit VARCHAR(255) NULL DEFAULT NULL;
