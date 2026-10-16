/**
 * Form Validation Module
 * Comprehensive validation for all form fields
 */
window.HRMS_Validation = (function() {
    'use strict';

    const config = window.HRMS_CONFIG;
    const patterns = Object.fromEntries(
        Object.entries(config.validation)
            .filter(([name]) => name.endsWith('Pattern'))
            .map(([name, pattern]) => [name, new RegExp(pattern)])
    );

    // Validation rules for each field
    const rules = {
        // Personal Basic
        full_name: {
            required: true,
            minLength: 2,
            maxLength: 100,
            pattern: /^[a-zA-Z\s\.]+$/,
            message: 'Enter a valid full name (letters, spaces, dots only)'
        },
        email: {
            required: true,
            pattern: patterns.emailPattern,
            message: 'Enter a valid email address'
        },
        mobile_number: {
            required: true,
            pattern: patterns.mobilePattern,
            message: 'Enter a valid 10-digit mobile number starting with 6-9'
        },
        is_alumni: { required: true, message: 'Select yes or no for alumni status' },
        alumni_school: {
            requiredIf: (state) => state.personal_basic?.is_alumni === 'Yes',
            message: 'Select an alumni school'
        },
        resume_file_id: {
            requiredIf: (state) => true, // Always required for job application
            message: 'Upload your resume'
        },

        // Address
        'curr_address_line1': { required: true, message: 'Address line 1 is required' },
        'curr_city': { required: true, message: 'City is required' },
        'curr_pincode': { required: true, pattern: patterns.pincodePattern, message: 'Enter a valid 6-digit PIN code' },
        'curr_state': { required: true, message: 'Select a state' },
        'curr_country': { required: true, message: 'Select a country' },
        same_as_permanent: { required: true, message: 'Select whether the addresses are the same' },

        'perm_address_line1': {
            requiredIf: (state) => state.address?.same_as_permanent?.original_value === 'No',
            message: 'Address line 1 is required'
        },
        'perm_city': {
            requiredIf: (state) => state.address?.same_as_permanent?.original_value === 'No',
            message: 'City is required'
        },
        'perm_pincode': {
            requiredIf: (state) => state.address?.same_as_permanent?.original_value === 'No',
            pattern: patterns.pincodePattern,
            message: 'Enter a valid 6-digit PIN code'
        },
        'perm_state': {
            requiredIf: (state) => state.address?.same_as_permanent?.original_value === 'No',
            message: 'Select a state'
        },
        'perm_country': {
            requiredIf: (state) => state.address?.same_as_permanent?.original_value === 'No',
            message: 'Select a country'
        },

        // Aadhaar & PAN
        aadhaar_name: { required: true, minLength: 2, message: 'Enter name as per Aadhaar' },
        aadhaar_number: { required: true, pattern: patterns.aadhaarPattern, message: 'Enter a valid 12-digit Aadhaar number' },
        aadhaar_file_id: { required: true, message: 'Upload Aadhaar card' },
        pan_number: { required: true, pattern: patterns.panPattern, message: 'Enter a valid PAN (e.g., ABCDE1234F)' },
        pan_file_id: { required: true, message: 'Upload PAN card' },
        aadhaar_pan_linked: { required: true, message: 'Select whether Aadhaar and PAN are linked' },

        // Personal Details
        photo_file_id: { required: true, message: 'Upload your photograph' },
        signature_file_id: { required: true, message: 'Upload your signature' },
        date_of_birth: {
            required: true,
            custom: (value) => {
                if (!value) return false;
                const dob = new Date(value);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const monthDiff = today.getMonth() - dob.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    age--;
                }
                return age >= 18 && age <= 100;
            },
            message: 'Enter a valid date of birth (age 18-100)'
        },
        gender: { required: true, message: 'Select gender' },
        marital_status: { required: true, message: 'Select marital status' },
        spouse_name: {
            requiredIf: (state) => state.personal_details?.marital_status?.original_value === 'Married',
            minLength: 2,
            message: 'Enter spouse name (required if married)'
        },
        father_name: { required: true, minLength: 2, message: 'Enter father\'s name' },
        mother_name: { required: true, minLength: 2, message: 'Enter mother\'s name' },
        blood_group: { required: true, message: 'Select blood group' },

        // Next of Kin
        nok_name_source: { required: true, message: 'Select NOK name source' },
        nok_select: {
            requiredIf: (state) => state.next_of_kin?.name_source?.original_value === 'select',
            message: 'Select a Next of Kin'
        },
        nok_manual_name: {
            requiredIf: (state) => state.next_of_kin?.name_source?.original_value === 'manual',
            minLength: 2,
            message: 'Enter NOK name'
        },
        nok_contact: { required: true, pattern: patterns.mobilePattern, message: 'Enter valid 10-digit mobile number' },
        nok_is_emergency: { required: true, message: 'Confirm if this is emergency contact' },
        nok_emergency_contact: {
            requiredIf: (state) => state.next_of_kin?.is_emergency?.original_value === 'No',
            pattern: patterns.mobilePattern,
            message: 'Enter a valid 10-digit emergency contact number'
        },
        'nok_address_line1': { required: true, message: 'NOK address line 1 is required' },
        'nok_city': { required: true, message: 'NOK city is required' },
        'nok_pincode': { required: true, pattern: patterns.pincodePattern, message: 'Enter valid 6-digit PIN code' },
        'nok_state': { required: true, message: 'Select NOK state' },
        'nok_country': { required: true, message: 'Select NOK country' },

        // Referral
        referral_source: { required: false },
        family_working: { required: true, message: 'Select yes/no' },
        'family_working_details.*.name': {
            required: true,
            message: 'Enter family member name'
        },
        'family_working_details.*.unit': {
            required: true,
            message: 'Select unit'
        },
        'family_working_details.*.designation': {
            required: true,
            message: 'Enter designation'
        },
        family_studying: { required: true, message: 'Select yes/no' },
        'family_studying_details.*.name': {
            required: true,
            message: 'Enter family member name'
        },
        'family_studying_details.*.school': {
            required: true,
            message: 'Select school'
        },
        'family_studying_details.*.class': {
            required: true,
            message: 'Enter class'
        },

        // Education
        'education.*.qualification_type': { required: true, message: 'Select qualification type' },
        'education.*.qualification_name': { required: true, message: 'Enter qualification name' },
        'education.*.institution': { required: true, message: 'Enter institution name' },
        'education.*.year_of_completion': {
            required: true,
            custom: (value) => {
                const year = parseInt(value);
                const currentYear = new Date().getFullYear();
                return !isNaN(year) && year >= 1950 && year <= currentYear;
            },
            message: 'Enter valid year of completion'
        },
        'education.*.certificate_file_id': { required: true, message: 'Upload certificate' },
        'education.*.skilled_in': {
            requiredIf: (entry) => entry.qualification_type?.original_value === 'Others',
            message: 'Enter skills (required for Grade 4/Others)'
        },

        // Teacher Documents
        is_teacher: { required: true, message: 'Select yes/no' },
        bed_status: {
            requiredIf: (state) => teacherStatus(state, 'is_teacher') === 'Yes',
            message: 'Select B.Ed status'
        },
        bed_institution: {
            requiredIf: (state) => teacherStatus(state, 'is_teacher') === 'Yes',
            message: 'Enter institution'
        },
        bed_file_id: {
            requiredIf: (state) => teacherStatus(state, 'is_teacher') === 'Yes',
            message: 'Upload B.Ed document'
        },
        bed_proposed_year: {
            requiredIf: (state) => {
                return teacherStatus(state, 'is_teacher') === 'Yes' &&
                    teacherStatus(state, 'bed_status') === 'Joined';
            },
            custom: (value) => {
                const year = parseInt(value);
                const currentYear = new Date().getFullYear();
                return !isNaN(year) && year >= currentYear && year <= currentYear + 10;
            },
            message: 'Enter valid proposed graduation year'
        },
        bed_graduation_year: {
            requiredIf: (state) => {
                return teacherStatus(state, 'is_teacher') === 'Yes' &&
                    teacherStatus(state, 'bed_status') === 'Completed';
            },
            custom: (value) => {
                const year = parseInt(value);
                const currentYear = new Date().getFullYear();
                return !isNaN(year) && year >= 1950 && year <= currentYear;
            },
            message: 'Enter valid graduation year'
        },

        // Work Experience
        experience_not_applicable: { required: false },
        'experience_entries.*.company': {
            required: true,
            message: 'Enter company name'
        },
        'experience_entries.*.designation': {
            required: true,
            message: 'Enter designation'
        },
        'experience_entries.*.start_month': {
            required: true,
            message: 'Enter start month'
        },
        'experience_entries.*.end_month': {
            required: true,
            message: 'Enter end month',
            custom: (value, entryContext, fullState, fieldPath) => {
                // entryContext is the experience entry object (the array item)
                // value is the end_month value
                if (!entryContext || !entryContext.start_month?.original_value || !value) return true;
                
                const startMonth = entryContext.start_month.original_value; // Format: YYYY-MM
                const endMonth = value; // Format: YYYY-MM
                
                return endMonth >= startMonth;
            },
            customMessage: 'Leaving month cannot be before starting month'
        },
        'experience_entries.*.details': {
            required: true,
            message: 'Enter experience details'
        },

        // Employment Details
        employment_unit: { required: true, message: 'Select a unit' },
        employment_campus: { required: true, message: 'Select a campus' },
        employment_designation: { required: true, message: 'Enter a designation' },
        employment_employee_type: { required: true, message: 'Select an employee type' },
        employment_job_type: { required: true, message: 'Select a job type' },
        employment_reporting_to: { required: true, message: 'Select who you report to' },
        employment_date_of_joining: { required: true, message: 'Select your date of joining' },
        employment_probation_period: { required: true, message: 'Select a probation period' },
        employment_probation_start_date: { required: true, message: 'Select the probation start date' },
        employment_probation_end_date: {
            required: true,
            message: 'Select the probation end date',
            custom: (value, formState) => {
                const startField = formState?.employment_details?.probation_start_date;
                const startDate = startField?.change === 'Yes'
                    ? startField.updated_value
                    : startField?.original_value;
                return !startDate || value >= startDate;
            },
            customMessage: 'Probation end date cannot be before the start date'
        },

        // Declaration
        declaration: { required: true, message: 'You must accept the declaration' },
        // Terms and Conditions
        terms_accepted: { required: true, message: 'You must accept the Terms and Conditions' }
    };

    function referralStatus(state, field) {
        const value = state.referral?.[field];
        return typeof value === 'object' && value !== null
            ? value.original_value
            : value;
    }

    function teacherStatus(state, field) {
        const value = state.teacher_documents?.[field];
        return typeof value === 'object' && value !== null
            ? value.original_value
            : value;
    }

    function effectiveEmploymentValue(state, field) {
        const employmentField = state.employment_details?.[field];
        if (!employmentField) return '';
        return employmentField.change === 'Yes'
            ? employmentField.updated_value
            : employmentField.original_value;
    }

    function validateField(path, value, state, ruleOverride, ruleContext) {
        const wildcardPath = path.replace(/\[\d+\]/g, '.*');
        const rule = ruleOverride || rules[path] || rules[wildcardPath];
        if (!rule) return { valid: true };

        const context = ruleContext || getArrayItemContext(path, state);

        // Check requiredIf
        if (rule.requiredIf && !rule.requiredIf(context)) {
            return { valid: true };
        }

        // Required check
        if (rule.required || rule.requiredIf) {
            if (value === undefined || value === null || value === '') {
                return { valid: false, message: rule.message };
            }
            // For objects with original_value
            if (typeof value === 'object' && value.original_value !== undefined) {
                if (value.original_value === '' || value.original_value === null) {
                    return { valid: false, message: rule.message };
                }
            }
        }

        // If not required and empty, valid
        if (!rule.required && (!value || (typeof value === 'object' && !value.original_value))) {
            return { valid: true };
        }

        // Get the actual value to validate
        let val = value;
        if (typeof value === 'object' && value.original_value !== undefined) {
            val = value.original_value;
        }

        // String validations
        if (typeof val === 'string') {
            if (rule.minLength && val.length < rule.minLength) {
                return { valid: false, message: rule.message };
            }
            if (rule.maxLength && val.length > rule.maxLength) {
                return { valid: false, message: rule.message };
            }
            if (rule.pattern && !rule.pattern.test(val)) {
                return { valid: false, message: rule.message };
            }
        }

        // Number validations
        if (typeof val === 'number' || (typeof val === 'string' && !isNaN(val))) {
            const num = Number(val);
            if (rule.min !== undefined && num < rule.min) {
                return { valid: false, message: rule.message };
            }
            if (rule.max !== undefined && num > rule.max) {
                return { valid: false, message: rule.message };
            }
        }

        // Custom validation
        if (rule.custom && !rule.custom(val, context, state, path)) {
            return { valid: false, message: rule.customMessage || rule.message };
        }

        return { valid: true };
    }

    function validateArrayItems(arrayPath, itemRules, state) {
        const array = getPath(state, arrayPath);
        if (!Array.isArray(array)) return [];

        const errors = [];
        array.forEach((item, index) => {
            Object.keys(itemRules).forEach(rulePath => {
                const rule = itemRules[rulePath];
                const value = getPath(item, rulePath);
                const result = validateField(
                    `${arrayPath}[${index}].${rulePath}`,
                    value,
                    state,
                    rule,
                    item
                );
                if (!result.valid) {
                    errors.push({
                        path: `${arrayPath}[${index}].${rulePath}`,
                        message: result.message,
                        index
                    });
                }
            });
        });
        return errors;
    }

    function getArrayItemContext(path, state) {
        const match = path.match(/^(.*)\[(\d+)\]\.[^.]+$/);
        return match ? getPath(state, `${match[1]}[${match[2]}]`) || state : state;
    }

    function getPath(obj, path) {
        // Handle array notation like education[0].qualification_type
        const parts = path.replace(/\[(\d+)\]/g, '.$1').split('.');
        let current = obj;
        for (const part of parts) {
            if (current === null || current === undefined) return undefined;
            current = current[part];
        }
        return current;
    }

    function validateStep(stepName, state) {
        const errors = [];

        // Define which fields belong to each step
        const stepFields = {
            'personal-basic': [
                'full_name', 'email', 'mobile_number', 'is_alumni', 'alumni_school', 'resume_file_id'
            ],
            'address': [
                'curr_address_line1', 'curr_address_line2', 'curr_address_line3',
                'curr_city', 'curr_pincode', 'curr_state', 'curr_country',
                'same_as_permanent', 'perm_address_line1', 'perm_address_line2',
                'perm_address_line3', 'perm_city', 'perm_pincode', 'perm_state', 'perm_country'
            ],
            'aadhaar-pan': [
                'aadhaar_name', 'aadhaar_number', 'aadhaar_file_id',
                'pan_number', 'pan_file_id', 'aadhaar_pan_linked'
            ],
            'personal-details': [
                'photo_file_id', 'signature_file_id',
                'date_of_birth', 'gender', 'marital_status', 'spouse_name',
                'father_name', 'mother_name', 'blood_group'
            ],
            'next-of-kin': [
                'nok_name_source', 'nok_select', 'nok_manual_name', 'nok_contact',
                'nok_is_emergency', 'nok_emergency_contact', 'nok_address_line1', 'nok_address_line2',
                'nok_address_line3', 'nok_city', 'nok_pincode', 'nok_state', 'nok_country'
            ],
            'referral': [
                'referred_by_name', 'referral_source', 'family_working',
                'family_studying'
            ],
            'education': [
                // Education array validated separately
            ],
            'teacher-documents': [
                'is_teacher', 'bed_status', 'bed_institution', 'bed_file_id',
                'bed_proposed_year', 'bed_graduation_year',
                'driving_license_file_id', 'police_verification_file_id'
            ],
            'work-experience': [
                'experience_not_applicable'
            ],
            'employment-details': [
            ],
            'review': ['declaration']
        };

        const fields = stepFields[stepName] || [];

        fields.forEach(field => {
            const statePath = window.HRMS_FormState.getFieldStatePath(field) || field;
            const value = getPath(state, statePath);
            const result = validateField(field, value, state);
            if (!result.valid) {
                errors.push({ path: field, message: result.message });
            }
        });

        if (stepName === 'employment-details') {
            const employmentFields = [
                ['unit', 'Unit'],
                ['campus', 'Campus'],
                ['designation', 'Designation'],
                ['employee_type', 'Employee Type (Grade)'],
                ['job_type', 'Job Type'],
                ['reporting_to', 'Reporting To'],
                ['date_of_joining', 'Date of Joining'],
                ['probation_period', 'Probation Period'],
                ['probation_start_date', 'Probation Start Date'],
                ['probation_end_date', 'Probation End Date']
            ];

            employmentFields.forEach(([field, label]) => {
                const change = state.employment_details?.[field]?.change;
                if (change !== 'Yes' && change !== 'No') {
                    errors.push({
                        path: `employment_${field}_change`,
                        message: `Choose Yes or No for ${label}`
                    });
                    return;
                }

                const fieldName = `employment_${field}`;
                const result = validateField(
                    fieldName,
                    effectiveEmploymentValue(state, field),
                    state,
                    rules[fieldName]
                );
                if (!result.valid) {
                    errors.push({ path: fieldName, message: result.message });
                }
            });
        }

        // Array validations per step
        if (stepName === 'education') {
            if (!Array.isArray(state.education) || state.education.length === 0) {
                errors.push({
                    path: 'education',
                    message: 'Add at least one qualification'
                });
            }

            const eduErrors = validateArrayItems('education', {
                qualification_type: rules['education.*.qualification_type'],
                qualification_name: rules['education.*.qualification_name'],
                institution: rules['education.*.institution'],
                year_of_completion: rules['education.*.year_of_completion'],
                certificate_file_id: rules['education.*.certificate_file_id'],
                skilled_in: rules['education.*.skilled_in']
            }, state);
            errors.push(...eduErrors);
        }

        if (stepName === 'referral') {
            if (referralStatus(state, 'family_working') === 'Yes') {
                const workingDetails = state.referral?.family_working_details;
                if (!Array.isArray(workingDetails) || workingDetails.length === 0) {
                    errors.push({
                        path: 'family_working',
                        message: 'Add at least one family member working in the organisation'
                    });
                } else {
                    const fwErrors = validateArrayItems('referral.family_working_details', {
                        name: rules['family_working_details.*.name'],
                        unit: rules['family_working_details.*.unit'],
                        designation: rules['family_working_details.*.designation']
                    }, state);
                    errors.push(...fwErrors);
                }
            }
            if (referralStatus(state, 'family_studying') === 'Yes') {
                const studyingDetails = state.referral?.family_studying_details;
                if (!Array.isArray(studyingDetails) || studyingDetails.length === 0) {
                    errors.push({
                        path: 'family_studying',
                        message: 'Add at least one family member studying in the institution'
                    });
                } else {
                    const fsErrors = validateArrayItems('referral.family_studying_details', {
                        name: rules['family_studying_details.*.name'],
                        school: rules['family_studying_details.*.school'],
                        class: rules['family_studying_details.*.class']
                    }, state);
                    errors.push(...fsErrors);
                }
            }
        }

        if (stepName === 'teacher-documents') {
            const otherDocErrors = validateArrayItems('teacher_documents.other_documents', {
                title: { required: true, message: 'Enter document title' },
                file_id: { required: true, message: 'Upload document' }
            }, state);
            errors.push(...otherDocErrors);
        }

        if (stepName === 'work-experience') {
            if (!state.work_experience?.not_applicable) {
                const expErrors = validateArrayItems('work_experience.entries', {
                    company: rules['experience_entries.*.company'],
                    designation: rules['experience_entries.*.designation'],
                    start_month: rules['experience_entries.*.start_month'],
                    end_month: rules['experience_entries.*.end_month'],
                    details: rules['experience_entries.*.details']
                }, state);
                errors.push(...expErrors);
            }
        }

        return errors;
    }

    function validateAll(state) {
        const allErrors = [];
        const steps = ['personal-basic', 'address', 'aadhaar-pan', 'personal-details', 'next-of-kin', 'referral', 'education', 'teacher-documents', 'work-experience', 'employment-details', 'review'];

        steps.forEach(step => {
            const errors = validateStep(step, state);
            allErrors.push(...errors);
        });

        return allErrors;
    }

    // UI Helpers
    function showError(fieldId, message) {
        const errorEl = document.getElementById(`${fieldId}-error`);
        const employmentReplacement = fieldId.startsWith('employment-')
            ? document.getElementById(`${fieldId}-replacement`)
            : null;
        const inputEl = employmentReplacement && !employmentReplacement.hidden
            ? document.getElementById(`${fieldId}-updated`)
            : document.getElementById(fieldId);

        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'flex';
        }
        if (inputEl) {
            inputEl.classList.add('error');
            inputEl.classList.remove('success');
            inputEl.setAttribute('aria-invalid', 'true');
            inputEl.setAttribute('aria-describedby', `${fieldId}-error`);
        }
    }

    function clearError(fieldId) {
        const errorEl = document.getElementById(`${fieldId}-error`);
        const inputEl = document.getElementById(fieldId);

        if (errorEl) {
            errorEl.textContent = '';
            errorEl.style.display = 'none';
        }
        if (inputEl) {
            inputEl.classList.remove('error');
            inputEl.classList.add('success');
            inputEl.removeAttribute('aria-invalid');
            inputEl.removeAttribute('aria-describedby');
        }
    }

    function showErrors(errors) {
        const errorFieldAliases = {
            is_alumni: 'alumni',
            resume_file_id: 'resume',
            nok_emergency_contact: 'emergency-contact',
            education: 'education'
        };

        errors.forEach(error => {
            if (error.index !== undefined && showArrayItemError(error)) {
                return;
            }

            // Convert path to field ID
            const fieldId = errorFieldAliases[error.path] ||
                error.path.replace(/\./g, '-').replace(/\[(\d+)\]/g, '-$1').replace(/_/g, '-');
            showError(fieldId, error.message);
        });
    }

    function showArrayItemError(error) {
        const match = error.path.match(/^(.*)\[(\d+)\]\.([^.]+)$/);
        if (!match) return false;

        const [, arrayPath, rawIndex, field] = match;
        const containerIds = {
            education: 'education-items',
            'referral.family_working_details': 'family-working-items',
            'referral.family_studying_details': 'family-studying-items',
            'teacher_documents.other_documents': 'other-documents-items',
            'work_experience.entries': 'experience-items'
        };
        const container = document.getElementById(containerIds[arrayPath]);
        const item = container?.children[Number(rawIndex)];
        if (!item) return false;

        const selectors = {
            education: {
                qualification_type: '[name="education_qualification_type[]"]',
                qualification_name: '[name="education_qualification_name[]"]',
                skilled_in: '[name="education_skilled_in[]"]',
                institution: '[name="education_institution[]"]',
                year_of_completion: '[name="education_year[]"]',
                certificate_file_id: '.education-certificate-upload .file-upload-zone'
            },
            'referral.family_working_details': {
                name: '[name="family_working_name[]"]',
                unit: '[name="family_working_unit[]"]',
                designation: '[name="family_working_designation[]"]'
            },
            'referral.family_studying_details': {
                name: '[name="family_studying_name[]"]',
                school: '[name="family_studying_school[]"]',
                class: '[name="family_studying_class[]"]'
            },
            'teacher_documents.other_documents': {
                title: '[name="other_doc_title[]"]',
                file_id: '.other-doc-upload .file-upload-zone'
            },
            'work_experience.entries': {
                company: '[name="experience_company[]"]',
                designation: '[name="experience_designation[]"]',
                start_month: '[name="experience_start_month[]"]',
                end_month: '[name="experience_end_month[]"]',
                details: '[name="experience_details[]"]',
                pay_slip_file_id: '.experience-payslip-upload .file-upload-zone'
            }
        };
        const selector = selectors[arrayPath]?.[field];
        if (!selector) return false;

        const control = item.querySelector(selector);
        if (!control) return false;

        const formGroup = control.closest('.form-group');
        const errorElement = formGroup?.querySelector('.form-error');
        if (errorElement) {
            errorElement.textContent = error.message;
            errorElement.style.display = 'flex';
        }
        control.classList.add('error');
        control.setAttribute('aria-invalid', 'true');
        if (errorElement?.id) {
            control.setAttribute('aria-describedby', errorElement.id);
        }
        return true;
    }

    function clearAllErrors(formElement) {
        if (!formElement) return;
        const errorElements = formElement.querySelectorAll('.form-error');
        const inputElements = formElement.querySelectorAll('.form-input, .form-select, .form-textarea, .employment-change-group');

        errorElements.forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
        inputElements.forEach(el => {
            el.classList.remove('error', 'success');
            el.removeAttribute('aria-invalid');
            el.removeAttribute('aria-describedby');
        });
    }

    return {
        validateField,
        validateStep,
        validateAll,
        rules,
        showError,
        clearError,
        showErrors,
        clearAllErrors,
        getPath
    };
})();