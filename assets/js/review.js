/**
 * Review Module
 * Generates the review summary and handles final submission
 */
window.HRMS_Review = (function() {
    'use strict';

    const state = window.HRMS_FormState;
    const validation = window.HRMS_Validation;
    const api = window.HRMS_API;
    const fileUpload = window.HRMS_FileUpload;

    const reviewContainer = document.getElementById('review-summary');
    const submitBtn = document.getElementById('review-submit');
    const submissionError = document.getElementById('submission-error');
    const submissionErrorMessage = document.getElementById('submission-error-message');
    const submissionErrorList = document.getElementById('submission-error-list');
    const successScreen = document.getElementById('step-success');
    const downloadBtn = document.getElementById('download-acknowledgement');
    const newFormBtn = document.getElementById('start-new-form');

    let initialized = false;
    let isSubmitting = false;
    let activePreviewUrl = null;
    let previewRequestId = 0;
    let filePreviewListenersAttached = false;
    let termsModalListenersAttached = false;

    function init() {
        if (initialized) return;
        initialized = true;

        if (submitBtn) {
            submitBtn.addEventListener('click', handleSubmit);
        }

        if (downloadBtn) {
            downloadBtn.addEventListener('click', downloadAcknowledgement);
        }

        if (newFormBtn) {
            newFormBtn.addEventListener('click', startNewForm);
        }

        attachFilePreviewModalListeners();
        attachFilePreviewListeners();
        attachTermsModalListeners();
        updateReview();

        // Subscribe to state changes to update review
        state.subscribe((path) => {
            if (path !== 'review' &&
                !(typeof path === 'string' && path.startsWith('_meta'))) {
                updateReview();
            }
        });
    }

    function updateReview() {
        if (!reviewContainer) return;

        const formState = state.getState();
        let html = '';

        // Define sections for review
        const sections = [
            {
                id: 'personal-basic',
                title: 'Personal Basic Information',
                icon: 'person',
                fields: [
                    { key: 'personal_basic.full_name', label: 'Full Name', type: 'verification' },
                    { key: 'personal_basic.email', label: 'Email', type: 'verification' },
                    { key: 'personal_basic.mobile_number', label: 'Mobile Number', type: 'verification' },
                    { key: 'personal_basic.is_alumni', label: 'Alumni Status', type: 'simple' },
                    { key: 'personal_basic.alumni_school', label: 'Alumni School', type: 'simple', condition: (s) => getEffectiveValue(s.personal_basic?.is_alumni) === 'Yes' },
                    { key: 'personal_basic.resume_file_id', label: 'Resume', type: 'file' }
                ]
            },
            {
                id: 'address',
                title: 'Address Details',
                icon: 'geo-alt',
                fields: [
                    { key: 'address.current.address_line1', label: 'Current Address Line 1', type: 'verification' },
                    { key: 'address.current.address_line2', label: 'Current Address Line 2', type: 'verification' },
                    { key: 'address.current.address_line3', label: 'Current Address Line 3', type: 'verification' },
                    { key: 'address.current.city', label: 'Current City', type: 'verification' },
                    { key: 'address.current.pincode', label: 'Current PIN Code', type: 'verification' },
                    { key: 'address.current.state', label: 'Current State', type: 'verification' },
                    { key: 'address.current.country', label: 'Current Country', type: 'verification' },
                    { key: 'address.same_as_permanent', label: 'Same as Permanent Address', type: 'simple' },
                    { key: 'address.permanent.address_line1', label: 'Permanent Address Line 1', type: 'verification' },
                    { key: 'address.permanent.address_line2', label: 'Permanent Address Line 2', type: 'verification' },
                    { key: 'address.permanent.address_line3', label: 'Permanent Address Line 3', type: 'verification' },
                    { key: 'address.permanent.city', label: 'Permanent City', type: 'verification' },
                    { key: 'address.permanent.pincode', label: 'Permanent PIN Code', type: 'verification' },
                    { key: 'address.permanent.state', label: 'Permanent State', type: 'verification' },
                    { key: 'address.permanent.country', label: 'Permanent Country', type: 'verification' }
                ]
            },
            {
                id: 'aadhaar-pan',
                title: 'Aadhaar & PAN Details',
                icon: 'credit-card',
                fields: [
                    { key: 'aadhaar_pan.aadhaar.name', label: 'Name as per Aadhaar', type: 'verification' },
                    { key: 'aadhaar_pan.aadhaar.number', label: 'Aadhaar Number', type: 'verification', mask: true },
                    { key: 'aadhaar_pan.aadhaar.file_id', label: 'Aadhaar Document', type: 'file' },
                    { key: 'aadhaar_pan.pan.number', label: 'PAN Number', type: 'verification', mask: true },
                    { key: 'aadhaar_pan.pan.file_id', label: 'PAN Document', type: 'file' },
                    { key: 'aadhaar_pan.aadhaar_pan_linked', label: 'Aadhaar & PAN Linked', type: 'simple' }
                ]
            },
            {
                id: 'personal-details',
                title: 'Personal Details',
                icon: 'person-fill',
                fields: [
                    { key: 'personal_details.photo_file_id', label: 'Photograph', type: 'file' },
                    { key: 'personal_details.signature_file_id', label: 'Signature', type: 'file' },
                    { key: 'personal_details.date_of_birth', label: 'Date of Birth', type: 'verification' },
                    { key: 'personal_details.gender', label: 'Gender', type: 'verification' },
                    { key: 'personal_details.marital_status', label: 'Marital Status', type: 'verification' },
                    { key: 'personal_details.spouse_name', label: 'Spouse Name', type: 'verification', condition: (s) => getEffectiveValue(s.personal_details?.marital_status) === 'Married' },
                    { key: 'personal_details.father_name', label: "Father's Name", type: 'verification' },
                    { key: 'personal_details.mother_name', label: "Mother's Name", type: 'verification' },
                    { key: 'personal_details.blood_group', label: 'Blood Group', type: 'verification' }
                ]
            },
            {
                id: 'next-of-kin',
                title: 'Next of Kin & Emergency Contact',
                icon: 'person-heart',
                fields: [
                    { key: 'next_of_kin.name_source', label: 'NOK Name Source', type: 'simple' },
                    { key: 'next_of_kin.selected_name', label: 'Selected NOK', type: 'simple', condition: (s) => getEffectiveValue(s.next_of_kin?.name_source) === 'select' },
                    { key: 'next_of_kin.manual_name', label: 'Manual NOK Name', type: 'simple', condition: (s) => getEffectiveValue(s.next_of_kin?.name_source) === 'manual' },
                    { key: 'next_of_kin.contact', label: 'NOK Contact No.', type: 'verification' },
                    { key: 'next_of_kin.is_emergency', label: 'Is Emergency Contact', type: 'simple' },
                    { key: 'next_of_kin.address.address_line1', label: 'NOK Address Line 1', type: 'verification' },
                    { key: 'next_of_kin.address.address_line2', label: 'NOK Address Line 2', type: 'verification' },
                    { key: 'next_of_kin.address.address_line3', label: 'NOK Address Line 3', type: 'verification' },
                    { key: 'next_of_kin.address.city', label: 'NOK City', type: 'verification' },
                    { key: 'next_of_kin.address.pincode', label: 'NOK PIN Code', type: 'verification' },
                    { key: 'next_of_kin.address.state', label: 'NOK State', type: 'verification' },
                    { key: 'next_of_kin.address.country', label: 'NOK Country', type: 'verification' }
                ]
            },
            {
                id: 'referral',
                title: 'Referral Information',
                icon: 'people',
                fields: [
                    { key: 'referral.referred_by_name', label: 'Referred By', type: 'verification' },
                    // { key: 'referral.referral_source', label: 'Referral Source', type: 'simple' },
                    { key: 'referral.family_working', label: 'Family Members Working', type: 'simple' },
                    { key: 'referral.family_working_details', label: 'Family Working Details', type: 'array', itemFields: ['tmss_id', 'name', 'unit', 'designation'], condition: (s) => getEffectiveValue(s.referral?.family_working) === 'Yes' },
                    { key: 'referral.family_studying', label: 'Family Members Studying', type: 'simple' },
                    { key: 'referral.family_studying_details', label: 'Family Studying Details', type: 'array', itemFields: ['name', 'school', 'class'], condition: (s) => getEffectiveValue(s.referral?.family_studying) === 'Yes' }
                ]
            },
            {
                id: 'education',
                title: 'Educational Qualifications',
                icon: 'mortarboard',
                fields: [
                    { key: 'education', label: 'Qualifications', type: 'education-array' }
                ]
            },
            {
                id: 'teacher-documents',
                title: 'Teacher Details & Other Documents',
                icon: 'book',
                fields: [
                    { key: 'teacher_documents.is_teacher', label: 'Are you a teacher?', type: 'simple' },
                    { key: 'teacher_documents.bed_status', label: 'B.Ed Status', type: 'simple', condition: (s) => getEffectiveValue(s.teacher_documents?.is_teacher) === 'Yes' },
                    { key: 'teacher_documents.bed_institution', label: 'B.Ed Institution', type: 'simple', condition: (s) => getEffectiveValue(s.teacher_documents?.is_teacher) === 'Yes' },
                    { key: 'teacher_documents.bed_file_id', label: 'B.Ed Document', type: 'file', condition: (s) => getEffectiveValue(s.teacher_documents?.is_teacher) === 'Yes' },
                    { key: 'teacher_documents.bed_proposed_year', label: 'Proposed Graduation Year', type: 'simple', condition: (s) => getEffectiveValue(s.teacher_documents?.is_teacher) === 'Yes' && getEffectiveValue(s.teacher_documents?.bed_status) === 'Joined' },
                    { key: 'teacher_documents.bed_graduation_year', label: 'Graduation Year', type: 'simple', condition: (s) => getEffectiveValue(s.teacher_documents?.is_teacher) === 'Yes' && getEffectiveValue(s.teacher_documents?.bed_status) === 'Completed' },
                    { key: 'teacher_documents.driving_license_file_id', label: 'Driving License', type: 'file' },
                    { key: 'teacher_documents.police_verification_file_id', label: 'Police Verification', type: 'file' },
                    { key: 'teacher_documents.other_documents', label: 'Other Documents', type: 'document-array' }
                ]
            },
            {
                id: 'work-experience',
                title: 'Work Experience',
                icon: 'briefcase',
                fields: [
                    { key: 'work_experience.not_applicable', label: 'Not Applicable', type: 'simple' },
                    { key: 'work_experience.entries', label: 'Experience Entries', type: 'experience-array', condition: (s) => !s.work_experience?.not_applicable }
                ]
            },
            {
                id: 'employment-details',
                title: 'Employment Details',
                icon: 'briefcase',
                fields: [
                    { key: 'employment_details.unit', label: 'Unit', type: 'employment' },
                    { key: 'employment_details.campus', label: 'Campus', type: 'employment' },
                    { key: 'employment_details.designation', label: 'Designation', type: 'employment' },
                    { key: 'employment_details.employee_type', label: 'Employee Type (Grade)', type: 'employment' },
                    { key: 'employment_details.job_type', label: 'Job Type', type: 'employment' },
                    { key: 'employment_details.reporting_to', label: 'Reporting To', type: 'employment' },
                    { key: 'employment_details.date_of_joining', label: 'Date of Joining', type: 'employment' }
                ]
            }
        ];

        sections.forEach(section => {
            const sectionHtml = renderSection(section, formState);
            if (sectionHtml) {
                html += sectionHtml;
            }
        });

        reviewContainer.innerHTML = html;

        // Attach edit button listeners
        attachEditListeners();
    }

    function resolvePermanentAddress(formState) {
        if (!formState.address) return formState;

        const sameAsPermanent = getEffectiveValue(formState.address.same_as_permanent);
        if (sameAsPermanent !== 'Yes') return formState;

        // Create a deep clone to avoid mutating the original state
        const resolved = JSON.parse(JSON.stringify(formState));

        // Copy current address values to permanent address
        if (resolved.address && resolved.address.current && resolved.address.permanent) {
            const current = resolved.address.current;
            const permanent = resolved.address.permanent;

            ['address_line1', 'address_line2', 'address_line3', 'city', 'pincode', 'state', 'country'].forEach(field => {
                const currentValue = getEffectiveValue(current[field]);
                if (currentValue !== null && currentValue !== undefined && String(currentValue).trim() !== '') {
                    permanent[field] = { ...current[field] };
                }
            });
        }

        return resolved;
    }

    function renderSection(section, formState) {
        let hasContent = false;
        let fieldsHtml = '';

        // Resolve permanent address values when same_as_permanent is "Yes"
        const resolvedFormState = resolvePermanentAddress(formState);

        section.fields.forEach(field => {
            // Check condition
            if (field.condition && !field.condition(resolvedFormState)) return;

            const value = getValue(resolvedFormState, field.key);
            const displayValue = field.type === 'simple'
                ? getSimpleDisplayValue(value)
                : field.type === 'employment'
                    ? value?.change === 'Yes' ? value.updated_value : getEffectiveValue(value)
                    : value;
            if ((displayValue === undefined || displayValue === null || displayValue === '') &&
                field.type !== 'file') {
                // Still show empty fields for required ones
                if (field.type !== 'array' && field.type !== 'education-array' && field.type !== 'experience-array' && field.type !== 'document-array') {
                    fieldsHtml += renderField(field.label, '', true);
                    hasContent = true;
                }
                return;
            }

            hasContent = true;

            switch (field.type) {
                case 'verification':
                    fieldsHtml += renderVerificationField(field.label, value, field.mask);
                    break;
                case 'simple':
                    fieldsHtml += renderField(field.label, displayValue);
                    break;
                case 'employment':
                    fieldsHtml += renderEmploymentField(field.label, value);
                    break;
                case 'file':
                    fieldsHtml += renderFileField(field.label, value, field.key);
                    break;
                case 'array':
                    fieldsHtml += renderArrayField(field.label, value, field.itemFields);
                    break;
                case 'education-array':
                    fieldsHtml += renderEducationArray(value);
                    break;
                case 'experience-array':
                    fieldsHtml += renderExperienceArray(value);
                    break;
                case 'document-array':
                    fieldsHtml += renderDocumentArray(value);
                    break;
            }
        });

        if (!hasContent) return '';

        return `
            <div class="review-section" data-section="${section.id}">
                <div class="review-section-header">
                    <h3 class="review-section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="me-2">
                            <use href="#icon-${section.icon}"></use>
                        </svg>
                        ${section.title}
                    </h3>
                    <button type="button" class="review-section-edit" data-target-step="${section.id}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit
                    </button>
                </div>
                <div class="review-fields">
                    ${fieldsHtml}
                </div>
            </div>
        `;
    }

    function renderField(label, value, isEmpty = false) {
        return `
            <div class="review-field">
                <span class="review-field-label">${escapeHtml(label)}</span>
                <span class="review-field-value ${isEmpty ? 'empty' : ''}">${isEmpty ? '<em>Not provided</em>' : escapeHtml(value)}</span>
            </div>
        `;
    }

    function renderEmploymentField(label, value) {
        if (value?.change !== 'Yes') {
            const effectiveValue = getEffectiveValue(value) || '';
            return renderField(label, effectiveValue, !effectiveValue);
        }

        return `
            <div class="review-field">
                <span class="review-field-label">${escapeHtml(label)}</span>
                <div class="review-field-value">${escapeHtml(value.updated_value || '')}</div>
                <div class="review-field-value text-secondary">Updated (original: ${escapeHtml(value.original_value || 'Not provided')})</div>
            </div>
        `;
    }

    function getSimpleDisplayValue(value) {
        if (typeof value === 'boolean') {
            return value ? 'Yes' : 'No';
        }

        if (value && typeof value === 'object') {
            return getEffectiveValue(value) ?? '';
        }

        return value;
    }

    function getEffectiveValue(value) {
        if (!value || typeof value !== 'object' || !Object.prototype.hasOwnProperty.call(value, 'original_value')) {
            return value;
        }

        if (value.verification === 'wrong' &&
            value.corrected_value !== null &&
            value.corrected_value !== undefined &&
            value.corrected_value !== '') {
            return value.corrected_value;
        }

        return value.original_value;
    }

    function renderVerificationField(label, value, mask = false) {
        const original = value.original_value || '';
        const verification = value.verification || 'pending';
        const corrected = value.corrected_value;

        const effectiveValue = getEffectiveValue(value) || '';
        let displayValue = mask ? maskValue(effectiveValue) : effectiveValue;
        let verificationHtml = '';

        if (verification !== 'pending') {
            verificationHtml = `<span class="verification-badge ${verification}">${capitalize(verification)}</span>`;
        }

        let correctionHtml = '';
        if (verification === 'wrong' && corrected) {
            correctionHtml = `<div class="review-field-value correction">Updated (original: ${escapeHtml(original || 'Not provided')})</div>`;
        }

        return `
            <div class="review-field">
                <span class="review-field-label">${escapeHtml(label)}</span>
                <div class="review-field-value verification">
                    ${displayValue ? escapeHtml(displayValue) : '<em>Not provided</em>'}
                    ${verificationHtml}
                </div>
                ${correctionHtml}
            </div>
        `;
    }

    function renderFileField(label, fileId, statePath) {
        const fileName = fileId ? fileUpload.getFileName(fileId) : '';
        const displayName = fileName || 'Uploaded file';
        const existingNamePaths = {
            'aadhaar_pan.aadhaar.file_id': {
                name: 'aadhaar_pan.aadhaar.existing_document_name',
                available: 'aadhaar_pan.aadhaar.existing_document_available',
                type: 'aadhaar'
            },
            'aadhaar_pan.pan.file_id': {
                name: 'aadhaar_pan.pan.existing_document_name',
                available: 'aadhaar_pan.pan.existing_document_available',
                type: 'pan'
            },
            'personal_details.photo_file_id': {
                name: 'personal_details.photo_existing_document_name',
                available: 'personal_details.photo_existing_document_available',
                type: 'photo'
            },
            'personal_details.signature_file_id': {
                name: 'personal_details.signature_existing_document_name',
                available: 'personal_details.signature_existing_document_available',
                type: 'signature'
            },
            'personal_basic.resume_file_id': {
                name: 'personal_basic.resume_existing_document_name',
                available: 'personal_basic.resume_existing_document_available',
                type: 'resume'
            }
        };
        const existingDocument = existingNamePaths[statePath];
        const hasExistingDocument = !fileId && existingDocument &&
            state.get(existingDocument.available);
        const existingName = hasExistingDocument
            ? state.get(existingDocument.name)
            : '';
        const existingPreviewUrl = existingName && existingDocument
            ? `${window.HRMS_CONFIG.basePath}/backend-integration/api/view-existing-document.php?type=${encodeURIComponent(existingDocument.type)}`
            : '';
        return `
            <div class="review-field">
                <span class="review-field-label">${escapeHtml(label)}</span>
                <span class="review-field-value file">
                    ${fileId ?
                        `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 20 8 20 8"></polyline></svg> ${escapeHtml(displayName)}` :
                        hasExistingDocument ?
                            existingName
                                ? `<a href="${escapeHtml(existingPreviewUrl)}" target="_blank" rel="noopener noreferrer" aria-label="Preview ${escapeHtml(existingName)} in a new tab" title="Preview ${escapeHtml(existingName)}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                ${escapeHtml(existingName)}
                            </a>`
                                : '<em>Existing document on file</em>'
                            :
                            '<em>Not provided</em>'
                    }
                    ${fileId ?
                        `<button type="button" class="btn btn-ghost btn-sm file-preview-btn" data-file-id="${escapeHtml(fileId)}" data-file-name="${escapeHtml(displayName)}" aria-label="Preview ${escapeHtml(displayName)}" title="Preview ${escapeHtml(displayName)}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>` : ''
                    }
                </span>
            </div>
        `;
    }

    function renderArrayField(label, array, itemFields) {
        if (!Array.isArray(array) || array.length === 0) {
            return renderField(label, '<em>No entries</em>', true);
        }

        let itemsHtml = '';
        array.forEach((item, index) => {
            let itemHtml = `<div style="margin-bottom: 0.75rem; padding: 0.75rem; background: var(--color-bg-page); border-radius: var(--radius-md);">`;
            itemHtml += `<strong>Entry ${index + 1}:</strong><br>`;
            itemFields.forEach(f => {
                const val = getEffectiveValue(item[f]) || '';
                itemHtml += `<span class="review-field-label">${escapeHtml(f)}:</span> <span class="review-field-value">${escapeHtml(val) || '<em>Not provided</em>'}</span><br>`;
            });
            itemHtml += '</div>';
            itemsHtml += itemHtml;
        });

        return `
            <div class="review-field">
                <span class="review-field-label">${escapeHtml(label)}</span>
                <div class="review-field-value">${itemsHtml}</div>
            </div>
        `;
    }

    function renderEducationArray(education) {
        if (!Array.isArray(education) || education.length === 0) {
            return renderField('Qualifications', '<em>No qualifications added</em>', true);
        }

        let itemsHtml = '';
        education.forEach((edu, index) => {
            const qualType = getEffectiveValue(edu.qualification_type) || '';
            const qualName = getEffectiveValue(edu.qualification_name) || '';
            const institution = getEffectiveValue(edu.institution) || '';
            const year = getEffectiveValue(edu.year_of_completion) || '';
            const cert = edu.certificate_file_id
                ? escapeHtml(fileUpload.getFileName(edu.certificate_file_id) || 'Uploaded file')
                : edu.existing_certificate_available
                    ? `<em>Existing document on file${edu.legacy_certificate_name ? `: ${escapeHtml(edu.legacy_certificate_name)}` : ''}</em>`
                    : '<em>Not uploaded</em>';

            let itemHtml = `<div style="margin-bottom: 0.75rem; padding: 0.75rem; background: var(--color-bg-page); border-radius: var(--radius-md);">`;
            itemHtml += `<strong>Qualification ${index + 1}:</strong><br>`;
            itemHtml += `<span class="review-field-label">Type:</span> <span class="review-field-value">${escapeHtml(qualType) || '<em>Not provided</em>'}</span><br>`;
            itemHtml += `<span class="review-field-label">Name:</span> <span class="review-field-value">${escapeHtml(qualName) || '<em>Not provided</em>'}</span><br>`;

            if (qualType === 'Others') {
                const skilled = getEffectiveValue(edu.skilled_in) || '';
                const talents = getEffectiveValue(edu.special_talents) || '';
                itemHtml += `<span class="review-field-label">Skilled In:</span> <span class="review-field-value">${escapeHtml(skilled) || '<em>Not provided</em>'}</span><br>`;
                itemHtml += `<span class="review-field-label">Special Talents:</span> <span class="review-field-value">${escapeHtml(talents) || '<em>Not provided</em>'}</span><br>`;
            }

            itemHtml += `<span class="review-field-label">Institution:</span> <span class="review-field-value">${escapeHtml(institution) || '<em>Not provided</em>'}</span><br>`;
            itemHtml += `<span class="review-field-label">Year:</span> <span class="review-field-value">${escapeHtml(year) || '<em>Not provided</em>'}</span><br>`;
            itemHtml += `<span class="review-field-label">Certificate:</span> <span class="review-field-value">${cert}</span>`;
            itemHtml += '</div>';
            itemsHtml += itemHtml;
        });

        return `
            <div class="review-field">
                <span class="review-field-label">Qualifications</span>
                <div class="review-field-value">${itemsHtml}</div>
            </div>
        `;
    }

    function renderExperienceArray(entries) {
        if (!Array.isArray(entries) || entries.length === 0) {
            return renderField('Experience Entries', '<em>No experience entries added</em>', true);
        }

        let itemsHtml = '';
        entries.forEach((exp, index) => {
            const company = getEffectiveValue(exp.company) || '';
            const designation = getEffectiveValue(exp.designation) || '';
            const start = getEffectiveValue(exp.start_month) || '';
            const end = getEffectiveValue(exp.end_month) || '';
            const details = getEffectiveValue(exp.details) || '';
            const letter = exp.experience_letter_file_id
                ? escapeHtml(fileUpload.getFileName(exp.experience_letter_file_id) || 'Uploaded file')
                : '<em>Not uploaded</em>';
            const payslip = exp.pay_slip_file_id
                ? escapeHtml(fileUpload.getFileName(exp.pay_slip_file_id) || 'Uploaded file')
                : '<em>Not uploaded</em>';

            let itemHtml = `<div style="margin-bottom: 0.75rem; padding: 0.75rem; background: var(--color-bg-page); border-radius: var(--radius-md);">`;
            itemHtml += `<strong>Experience ${index + 1}:</strong><br>`;
            itemHtml += `<span class="review-field-label">Company:</span> <span class="review-field-value">${escapeHtml(company) || '<em>Not provided</em>'}</span><br>`;
            itemHtml += `<span class="review-field-label">Designation:</span> <span class="review-field-value">${escapeHtml(designation) || '<em>Not provided</em>'}</span><br>`;
            itemHtml += `<span class="review-field-label">Period:</span> <span class="review-field-value">${escapeHtml(start)} to ${escapeHtml(end)}</span><br>`;
            itemHtml += `<span class="review-field-label">Details:</span> <span class="review-field-value">${escapeHtml(details) || '<em>Not provided</em>'}</span><br>`;
            itemHtml += `<span class="review-field-label">Experience Letter:</span> <span class="review-field-value">${letter}</span><br>`;
            itemHtml += `<span class="review-field-label">Pay Slip:</span> <span class="review-field-value">${payslip}</span>`;
            itemHtml += '</div>';
            itemsHtml += itemHtml;
        });

        return `
            <div class="review-field">
                <span class="review-field-label">Experience Entries</span>
                <div class="review-field-value">${itemsHtml}</div>
            </div>
        `;
    }

    function renderDocumentArray(documents) {
        if (!Array.isArray(documents) || documents.length === 0) {
            return renderField('Other Documents', '<em>No documents added</em>', true);
        }

        let itemsHtml = '';
        documents.forEach((doc, index) => {
            const title = getEffectiveValue(doc.title) || '';
            const fileId = doc.file_id;
            const fileName = fileId ? fileUpload.getFileName(fileId) : '';
            const displayName = fileName || 'Uploaded file';

            let itemHtml = `<div style="margin-bottom: 0.75rem; padding: 0.75rem; background: var(--color-bg-page); border-radius: var(--radius-md);">`;
            itemHtml += `<strong>Document ${index + 1}:</strong><br>`;
            itemHtml += `<span class="review-field-label">Title:</span> <span class="review-field-value">${escapeHtml(title) || '<em>Not provided</em>'}</span><br>`;
            itemHtml += `<span class="review-field-label">File:</span> <span class="review-field-value file">`;
            if (fileId) {
                itemHtml += `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 20 8 20 8"></polyline></svg> ${escapeHtml(displayName)}`;
                itemHtml += `<button type="button" class="btn btn-ghost btn-sm file-preview-btn" data-file-id="${escapeHtml(fileId)}" data-file-name="${escapeHtml(displayName)}" aria-label="Preview ${escapeHtml(displayName)}" title="Preview ${escapeHtml(displayName)}" style="margin-left: 0.5rem;">`;
                itemHtml += `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
                itemHtml += `</button>`;
            } else {
                itemHtml += '<em>Not uploaded</em>';
            }
            itemHtml += `</span>`;
            itemHtml += '</div>';
            itemsHtml += itemHtml;
        });

        return `
            <div class="review-field">
                <span class="review-field-label">Other Documents</span>
                <div class="review-field-value">${itemsHtml}</div>
            </div>
        `;
    }

    function attachEditListeners() {
        document.querySelectorAll('.review-section-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetStep = btn.dataset.targetStep;
                window.HRMS_Wizard.goToStep(targetStep);
            });
        });
    }

    function attachFilePreviewListeners() {
        if (filePreviewListenersAttached) return;
        filePreviewListenersAttached = true;
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.file-preview-btn');
            if (!button) return;

            event.preventDefault();
            event.stopPropagation();
            openFilePreview(button.dataset.fileId, button.dataset.fileName);
        });
    }

    function attachFilePreviewModalListeners() {
        const modal = document.getElementById('file-preview-modal');
        const closeBtn = document.getElementById('file-preview-close');
        const closeBtn2 = document.getElementById('file-preview-close-btn');

        if (closeBtn) {
            closeBtn.addEventListener('click', closeFilePreview);
        }
        if (closeBtn2) {
            closeBtn2.addEventListener('click', closeFilePreview);
        }
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeFilePreview();
                }
            });
        }

        // Handle Escape key
        document.addEventListener('keydown', handleFilePreviewKeydown);
    }

    function closeFilePreview() {
        const modal = document.getElementById('file-preview-modal');
        previewRequestId++;
        if (activePreviewUrl) {
            URL.revokeObjectURL(activePreviewUrl);
            activePreviewUrl = null;
        }
        if (modal) {
            modal.hidden = true;
            modal.classList.remove('active');
            const modalBody = document.getElementById('file-preview-body');
            if (modalBody) modalBody.innerHTML = '';
        }
    }

    function handleFilePreviewKeydown(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('file-preview-modal');
            if (modal && !modal.hidden) {
                closeFilePreview();
            }
        }
    }

    // Terms and Conditions Modal Functions
    function openTermsModal() {
        const modal = document.getElementById('terms-modal');
        if (modal) {
            modal.hidden = false;
            modal.classList.add('active');
        }
    }

    function closeTermsModal() {
        const modal = document.getElementById('terms-modal');
        if (modal) {
            modal.hidden = true;
            modal.classList.remove('active');
        }
    }

    function attachTermsModalListeners() {
        if (termsModalListenersAttached) return;
        const termsLink = document.getElementById('terms-link');
        const termsModal = document.getElementById('terms-modal');
        const closeBtn = document.getElementById('terms-modal-close');
        const cancelBtn = document.getElementById('terms-modal-cancel');
        const agreeBtn = document.getElementById('terms-modal-agree');

        if (!termsLink || !termsModal) {
            console.error('Terms and Conditions elements not found.');
            return;
        }
        termsModalListenersAttached = true;

        // Open the modal
        termsLink.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            openTermsModal();
        });

        // Close the modal
        function closeModal() {
            closeTermsModal();
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeModal);
        }

        // Agree and close
        if (agreeBtn) {
            agreeBtn.addEventListener('click', function () {
                const checkbox = document.getElementById('terms-checkbox');

                if (checkbox) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }

                closeModal();
            });
        }

        // Close when clicking the overlay
        termsModal.addEventListener('click', function (event) {
            if (event.target === termsModal) {
                closeModal();
            }
        });

        // Close with Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !termsModal.hidden) {
                closeModal();
            }
        });
    }

    function openFilePreview(fileId, fileName) {
        const modal = document.getElementById('file-preview-modal');
        const modalBody = document.getElementById('file-preview-body');
        const modalTitle = document.getElementById('file-preview-title');

        if (!modal || !modalBody || !modalTitle) return;

        modalTitle.textContent = fileName || 'Document Preview';
        modalBody.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner mx-auto"></div>
                <p class="mt-2 text-secondary">Loading document...</p>
            </div>
        `;

        modal.hidden = false;
        modal.classList.add('active');
        const requestId = ++previewRequestId;
        if (activePreviewUrl) {
            URL.revokeObjectURL(activePreviewUrl);
            activePreviewUrl = null;
        }

        // Get the file blob
        getFilePreviewBlob(fileId).then(blob => {
            if (requestId !== previewRequestId) return;
            if (!blob) {
                modalBody.innerHTML = `
                    <div class="alert alert-error text-center p-4">
                        <svg class="alert-icon mx-auto mb-2" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <div class="alert-content">
                            <div class="alert-title">Unable to Preview Document</div>
                            <div class="alert-message">The document could not be loaded for preview. Please download it instead.</div>
                        </div>
                    </div>
                `;
                return;
            }

            const fileUrl = URL.createObjectURL(blob);
            activePreviewUrl = fileUrl;
            const contentType = String(blob.type || '').toLowerCase();
            const isImage = contentType === 'image/jpeg' || contentType === 'image/png' ||
                /\.(jpg|jpeg|png)$/i.test(fileName || '');
            const isPdf = contentType === 'application/pdf' || /\.pdf$/i.test(fileName || '');

            if (isImage) {
                const image = document.createElement('img');
                image.src = fileUrl;
                image.alt = fileName || 'Uploaded document';
                image.style.maxWidth = '100%';
                image.style.height = 'auto';
                image.style.maxHeight = '70vh';
                image.addEventListener('error', () => {
                    if (requestId !== previewRequestId) return;

                    const alert = document.createElement('div');
                    alert.className = 'alert alert-error text-center p-4';
                    const content = document.createElement('div');
                    content.className = 'alert-content';
                    const title = document.createElement('div');
                    title.className = 'alert-title';
                    title.textContent = 'Failed to Load Image';
                    const message = document.createElement('div');
                    message.className = 'alert-message';
                    message.textContent = 'The image could not be loaded.';
                    content.append(title, message);
                    alert.appendChild(content);
                    modalBody.replaceChildren(alert);
                });
                modalBody.replaceChildren(image);
            } else if (isPdf) {
                modalBody.innerHTML = `
                    <iframe src="${fileUrl}" style="width: 100%; height: 70vh; border: none;" title="${escapeHtml(fileName)}"></iframe>
                `;
            } else {
                modalBody.innerHTML = `
                    <div class="text-center p-4">
                        <a href="${fileUrl}" target="_blank" class="btn btn-primary">Open Document</a>
                        <p class="text-secondary mt-2">Preview not available for this file type. Click the button to open.</p>
                    </div>
                `;
            }
        }).catch(error => {
            if (requestId !== previewRequestId) return;
            console.error('Error loading file preview:', error);
            modalBody.innerHTML = `
                <div class="alert alert-error text-center p-4">
                    <svg class="alert-icon mx-auto mb-2" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div class="alert-content">
                        <div class="alert-title">Unable to Preview Document</div>
                        <div class="alert-message">The document could not be loaded for preview. Please download it instead.</div>
                    </div>
                </div>
            `;
        });
    }

    async function getFilePreviewBlob(fileId) {
        if (!fileId) return null;

        // If it's a local file ID (starts with local-file:), retrieve from IndexedDB
        if (String(fileId).startsWith('local-file:')) {
            const record = await window.HRMS_FileUpload.getPendingFile(fileId);
            if (record && record.file) {
                return record.file;
            }
            return null;
        }

        // For server files, fetch from download endpoint
        return api.downloadDocument(fileId);
    }

    async function handleSubmit() {
        if (isSubmitting) return;
        if (!window.HRMS_Wizard?.isEmployeeDataReady()) {
            showToast('error', 'Employee Details Not Loaded', 'Refresh the page or contact HR before submitting your form.');
            return;
        }

        // Validate all steps
        const errors = validation.validateAll(state.getState());

        if (errors.length > 0) {
            validation.showErrors(errors);
            const firstError = errors[0];
            const firstErrorStep = getStepForValidationPath(firstError.path);
            if (firstErrorStep) {
                window.HRMS_Wizard?.goToStep(firstErrorStep);
                const step = document.getElementById(`step-${firstErrorStep}`);
                const invalidControl = step?.querySelector(
                    '.form-input.error, .form-select.error, .form-textarea.error, [aria-invalid="true"]'
                );
                if (invalidControl) {
                    invalidControl.focus({ preventScroll: true });
                    invalidControl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            window.HRMS_Wizard?.showToast(
                'error',
                'Form Not Ready to Submit',
                `${errors.length} required item(s) need attention. We opened the first step with an issue.`
            );
            return;
        }

        // Check declaration
        const declaration = document.getElementById('declaration-checkbox');
        if (!declaration?.checked) {
            validation.showError('declaration-checkbox', 'You must accept the declaration');
            return;
        }

        // Check Terms and Conditions
        const termsCheckbox = document.getElementById('terms-checkbox');
        if (!termsCheckbox?.checked) {
            validation.showError('terms-checkbox', 'You must accept the Terms and Conditions');
            return;
        }

        // Confirm submission
        const confirmed = await confirmSubmit();
        if (!confirmed) return;

        clearSubmissionError();
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner spinner-sm me-2"></span>Submitting...';

        try {
            const formData = state.toJSON();
            formData.submission.declaration = true;
            formData.submission.terms_accepted = true;
            formData.submission.status = 'pending';
            formData.submission.submitted_at = new Date().toISOString();

            await fileUpload.uploadPendingFiles(formData);
            const result = await api.submitForm(formData);

            if (result.success) {
                window.HRMS_Wizard?.clearDraft();
                const pendingFilesCleared = await fileUpload.clearPendingFiles();
                if (!pendingFilesCleared) {
                    showToast('error', 'Local File Cleanup Failed', 'Your form was submitted for HR approval, but saved files remain in this browser.');
                }
                state.set('submission.status', 'pending');
                state.set('submission.reference_number', result.reference_number);
                state.set('submission.submitted_at', new Date().toISOString());
                state.markClean();

                showSuccessScreen(result);
            } else {
                throw new Error(result.message || 'Submission failed');
            }
        } catch (error) {
            console.error('Submission error:', error);
            showSubmissionError(error);
        } finally {
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Submit Form
            `;
        }
    }

    function clearSubmissionError() {
        if (submissionError) submissionError.style.display = 'none';
        if (submissionErrorMessage) submissionErrorMessage.textContent = '';
        if (submissionErrorList) {
            submissionErrorList.replaceChildren();
            submissionErrorList.hidden = true;
        }
    }

    function showSubmissionError(error) {
        if (!submissionError || !submissionErrorMessage || !submissionErrorList) {
            showToast('error', 'Submission Failed', error.message || 'The submission could not be completed. Please try again.');
            return;
        }

        submissionErrorMessage.textContent = error.message ||
            'The submission could not be completed. Please review the details and try again.';
        submissionErrorList.replaceChildren();

        (error.errors || []).forEach(item => {
            const entry = document.createElement('li');
            const field = typeof item.field === 'string' ? item.field.replace(/[._]/g, ' ') : '';
            entry.textContent = field
                ? `${field}: ${item.message || 'Please review this item.'}`
                : item.message || 'Please review the form and try again.';
            submissionErrorList.appendChild(entry);
        });

        if (error.supportReference) {
            const reference = document.createElement('li');
            reference.textContent = `Support reference: ${error.supportReference}`;
            submissionErrorList.appendChild(reference);
        }

        submissionErrorList.hidden = submissionErrorList.childElementCount === 0;
        submissionError.style.display = 'flex';
        submissionError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function confirmSubmit() {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirm-modal');
            const messageEl = document.getElementById('confirm-modal-message');
            const confirmBtn = document.getElementById('confirm-modal-confirm');
            const cancelBtn = document.getElementById('confirm-modal-cancel');
            const closeBtn = document.getElementById('confirm-modal-close');

            messageEl.innerHTML = `
                <p>Are you sure you want to submit this form?</p>
                <p class="text-secondary mt-2">Once submitted, you cannot modify the information without contacting HR.</p>
            `;
            confirmBtn.textContent = 'Yes, Submit';
            confirmBtn.className = 'btn btn-success';

            modal.hidden = false;
            modal.classList.add('active');

            const cleanup = () => {
                modal.hidden = true;
                modal.classList.remove('active');
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
                closeBtn.removeEventListener('click', handleCancel);
                confirmBtn.textContent = 'Confirm';
                confirmBtn.className = 'btn btn-danger';
            };

            const handleConfirm = () => {
                cleanup();
                resolve(true);
            };

            const handleCancel = () => {
                cleanup();
                resolve(false);
            };

            confirmBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
            closeBtn.addEventListener('click', handleCancel);
        });
    }

    function getStepForValidationPath(path) {
        const field = String(path).split('[')[0];
        const statePath = state.getFieldStatePath(field) || field;
        const stateRoot = statePath.split('.')[0];
        const stepByStateRoot = {
            personal_basic: 'personal-basic',
            address: 'address',
            aadhaar_pan: 'aadhaar-pan',
            personal_details: 'personal-details',
            next_of_kin: 'next-of-kin',
            referral: 'referral',
            education: 'education',
            teacher_documents: 'teacher-documents',
            work_experience: 'work-experience',
            employment_details: 'employment-details',
            submission: 'review'
        };

        return stepByStateRoot[stateRoot] || null;
    }

    function showSuccessScreen(result) {
        const wizard = document.getElementById('form-wizard');
        const stepper = document.querySelector('.stepper');

        if (wizard) wizard.style.display = '';
        if (stepper) stepper.style.display = 'none';

        if (successScreen) {
            document.querySelectorAll('.wizard-step').forEach(step => {
                const isSuccess = step === successScreen;
                step.hidden = !isSuccess;
                step.classList.toggle('active', isSuccess);
            });
            successScreen.hidden = false;

            document.getElementById('success-ref').textContent = result.reference_number || 'N/A';
            document.getElementById('success-date').textContent = new Date().toLocaleDateString('en-IN', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('success-status').textContent = 'Waiting for HR approval';

            showToast('success', 'Submission Received', 'Your employee information is waiting for HR approval.');
        }
    }

    function downloadAcknowledgement() {
        // Generate a simple acknowledgement PDF or print
        const ref = state.get('submission.reference_number');
        const date = state.get('submission.submitted_at');

        const content = `
Employee Information Acknowledgement
=====================================
Reference Number: ${ref || 'N/A'}
Submission Date: ${date ? new Date(date).toLocaleString() : 'N/A'}
Employee ID: ${state.get('employee_id') || 'N/A'}

This acknowledges receipt of your employee information form.
Please keep this for your records.
        `;

        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `acknowledgement-${ref || 'form'}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function startNewForm() {
        if (confirm('Start a new form? This will clear all current data.')) {
            state.reset();
            window.location.href = window.HRMS_CONFIG.basePath + '/index.php?step=mobile-verification';
        }
    }

    function showToast(type, title, message) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <svg class="toast-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                ${type === 'success' ? '<polyline points="20 6 9 17 4 12"></polyline>' :
                  type === 'error' ? '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>' :
                  '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>'}
            </svg>
            <div class="toast-content">
                <div class="toast-title">${escapeHtml(title)}</div>
                <div class="toast-message">${escapeHtml(message)}</div>
            </div>
            <button class="toast-close" aria-label="Dismiss">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        `;

        container.appendChild(toast);

        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.remove();
        });

        setTimeout(() => toast.remove(), 5000);
    }

    function getValue(obj, path) {
        const parts = path.replace(/\[(\d+)\]/g, '.$1').split('.');
        let current = obj;
        for (const part of parts) {
            if (current === null || current === undefined) return undefined;
            current = current[part];
        }
        return current;
    }

    function maskValue(value) {
        if (!value) return '';
        if (value.length <= 4) return '*'.repeat(value.length);
        return '*'.repeat(value.length - 4) + value.slice(-4);
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(fn, delay) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn(...args), delay);
        };
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init,
        updateReview,
        previewFile: openFilePreview
    };
})();