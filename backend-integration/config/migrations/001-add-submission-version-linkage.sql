-- One-time migration: make the existing temp_* tables append-only per submission.
-- Existing rows remain unlinked baseline rows (submission_id IS NULL).

ALTER TABLE temp_educational_qualifications
    DROP FOREIGN KEY fk_temp_educational_qualifications_employee_id;
ALTER TABLE temp_employee_contact
    DROP FOREIGN KEY fk_temp_employee_contact_employee_id;
ALTER TABLE temp_employee_employment
    DROP FOREIGN KEY fk_temp_employee_employment_employee_id;
ALTER TABLE temp_employee_identification
    DROP FOREIGN KEY fk_temp_employee_identification_employee_id;
ALTER TABLE temp_employee_previous_experience
    DROP FOREIGN KEY fk_temp_employee_previous_experience_employee_id;
ALTER TABLE temp_employee_referrals
    DROP FOREIGN KEY fk_temp_employee_referrals_employee_id;

ALTER TABLE temp_employees
    DROP INDEX uq_mobile_no,
    DROP PRIMARY KEY,
    MODIFY employee_id INT(11) NOT NULL,
    ADD COLUMN temp_row_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (temp_row_id),
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER employee_id,
    ADD UNIQUE KEY uq_temp_employees_employee_submission (employee_id, submission_id),
    ADD KEY idx_temp_employees_submission_id (submission_id),
    ADD KEY idx_temp_employees_mobile_no (mobile_no);

ALTER TABLE employee_form_submissions
    ADD UNIQUE KEY uq_employee_form_submissions_employee_id (employee_id, id);

ALTER TABLE temp_employees
    ADD CONSTRAINT fk_temp_employees_submission
        FOREIGN KEY (employee_id, submission_id)
        REFERENCES employee_form_submissions (employee_id, id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE temp_employee_contact
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER employee_id,
    ADD KEY idx_temp_employee_contact_submission (employee_id, submission_id),
    ADD CONSTRAINT fk_temp_employee_contact_submission
        FOREIGN KEY (employee_id, submission_id)
        REFERENCES temp_employees (employee_id, submission_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE temp_employee_employment
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER employee_id,
    ADD KEY idx_temp_employee_employment_submission (employee_id, submission_id),
    ADD CONSTRAINT fk_temp_employee_employment_submission
        FOREIGN KEY (employee_id, submission_id)
        REFERENCES temp_employees (employee_id, submission_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE temp_employee_identification
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER employee_id,
    ADD KEY idx_temp_employee_identification_submission (employee_id, submission_id),
    ADD CONSTRAINT fk_temp_employee_identification_submission
        FOREIGN KEY (employee_id, submission_id)
        REFERENCES temp_employees (employee_id, submission_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE temp_employee_referrals
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER employee_id,
    ADD KEY idx_temp_employee_referrals_submission (employee_id, submission_id),
    ADD CONSTRAINT fk_temp_employee_referrals_submission
        FOREIGN KEY (employee_id, submission_id)
        REFERENCES temp_employees (employee_id, submission_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE temp_educational_qualifications
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER employee_id,
    ADD KEY idx_temp_educational_qualifications_submission (employee_id, submission_id),
    ADD CONSTRAINT fk_temp_educational_qualifications_submission
        FOREIGN KEY (employee_id, submission_id)
        REFERENCES temp_employees (employee_id, submission_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE temp_employee_previous_experience
    ADD COLUMN submission_id BIGINT UNSIGNED NULL AFTER employee_id,
    ADD KEY idx_temp_employee_previous_experience_submission (employee_id, submission_id),
    ADD CONSTRAINT fk_temp_employee_previous_experience_submission
        FOREIGN KEY (employee_id, submission_id)
        REFERENCES temp_employees (employee_id, submission_id)
        ON UPDATE RESTRICT ON DELETE RESTRICT;
