-- One-time migration: allow the existing staging tables to store only submitted changes.
-- Linkage columns stay required; omitted profile fields use NULL to mean "not changed".

ALTER TABLE temp_employees
    MODIFY mss_employee_id VARCHAR(20) NULL DEFAULT NULL,
    MODIFY full_name VARCHAR(255) NULL DEFAULT NULL,
    MODIFY alumni_detail CHAR(5) NULL DEFAULT NULL,
    MODIFY alumni_unit VARCHAR(255) NULL DEFAULT NULL,
    MODIFY added_by INT(11) NULL DEFAULT NULL,
    MODIFY updated_by INT(11) NULL DEFAULT NULL,
    MODIFY working_status CHAR(20) NULL DEFAULT NULL;

ALTER TABLE temp_employee_contact
    MODIFY local_address1 TEXT NULL DEFAULT NULL,
    MODIFY local_address2 TEXT NULL DEFAULT NULL,
    MODIFY pincode VARCHAR(10) NULL DEFAULT NULL,
    MODIFY city VARCHAR(100) NULL DEFAULT NULL,
    MODIFY state VARCHAR(100) NULL DEFAULT NULL,
    MODIFY country VARCHAR(100) NULL DEFAULT NULL,
    MODIFY permanent_address1 TEXT NULL DEFAULT NULL,
    MODIFY permanent_address2 TEXT NULL DEFAULT NULL,
    MODIFY perm_pincode VARCHAR(10) NULL DEFAULT NULL,
    MODIFY perm_city VARCHAR(100) NULL DEFAULT NULL,
    MODIFY perm_state VARCHAR(100) NULL DEFAULT NULL,
    MODIFY perm_country VARCHAR(100) NULL DEFAULT NULL;

ALTER TABLE temp_employee_employment
    MODIFY probation_period VARCHAR(50) NULL DEFAULT NULL,
    MODIFY reason_for_resignation TEXT NULL DEFAULT NULL,
    MODIFY mode_of_resignation VARCHAR(25) NULL DEFAULT NULL,
    MODIFY reason_for_termination TEXT NULL DEFAULT NULL,
    MODIFY updated_by INT(11) NULL DEFAULT NULL;

ALTER TABLE temp_employee_identification
    MODIFY aadhar_no VARCHAR(16) NULL DEFAULT NULL,
    MODIFY aadharlinked CHAR(5) NULL DEFAULT NULL,
    MODIFY pan_no VARCHAR(10) NULL DEFAULT NULL,
    MODIFY ccs VARCHAR(255) NULL DEFAULT NULL,
    MODIFY driving VARCHAR(255) NULL DEFAULT NULL,
    MODIFY others VARCHAR(255) NULL DEFAULT NULL,
    MODIFY pg_degree_status VARCHAR(255) NULL DEFAULT NULL,
    MODIFY pg_degree_institution VARCHAR(255) NULL DEFAULT NULL,
    MODIFY pg_degree_doc VARCHAR(255) NULL DEFAULT NULL,
    MODIFY created_by INT(11) NULL DEFAULT NULL,
    MODIFY updated_by INT(11) NULL DEFAULT NULL;

ALTER TABLE temp_employee_referrals
    MODIFY referral_source VARCHAR(100) NULL DEFAULT NULL,
    MODIFY working_name VARCHAR(25) NULL DEFAULT NULL,
    MODIFY working_unit INT(11) NULL DEFAULT NULL,
    MODIFY working_designation VARCHAR(25) NULL DEFAULT NULL,
    MODIFY student_name VARCHAR(25) NULL DEFAULT NULL,
    MODIFY student_unit INT(11) NULL DEFAULT NULL,
    MODIFY student_class VARCHAR(25) NULL DEFAULT NULL;

ALTER TABLE temp_educational_qualifications
    MODIFY qualification_type VARCHAR(255) NULL DEFAULT NULL,
    MODIFY qualification_details VARCHAR(255) NULL DEFAULT NULL,
    MODIFY institution VARCHAR(255) NULL DEFAULT NULL,
    MODIFY completion_year VARCHAR(4) NULL DEFAULT NULL,
    MODIFY created_by INT(11) NULL DEFAULT NULL,
    MODIFY updated_by INT(11) NULL DEFAULT NULL;

ALTER TABLE temp_employee_previous_experience
    MODIFY company_name VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
    MODIFY role VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
    MODIFY start_date DATE NULL DEFAULT NULL,
    MODIFY experience_details TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
    MODIFY pay_slip VARCHAR(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
    MODIFY created_by INT(11) NULL DEFAULT NULL;
