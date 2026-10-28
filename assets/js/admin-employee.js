(function() {
    'use strict';

    const config = window.ADMIN_EMPLOYEE_CONFIG;
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    let csrfToken = csrfMeta ? csrfMeta.content : '';
    let profileData = null;
    let submission = null;
    let editFields = {};
    let fieldOptions = {};
    const pincodeLookupCache = new Map();
    const pincodeLookupTimers = new Map();

    const sections = [
        {
            title: 'Personal Basic Information',
            documents: ['resume'],
            fields: [
                ['Full name', 'employees.full_name', 'full_name', 'personal_basic.full_name'],
                ['Email', 'employee_contact.personal_email', 'email', 'personal_basic.email'],
                ['Mobile number', 'employee_contact.mobile_no', 'mobile', 'personal_basic.mobile_number'],
                ['Alumni status', 'employees.alumni_detail', 'alumni_detail', 'personal_basic.is_alumni'],
                ['Alumni school', 'employees.alumni_unit', 'alumni_unit', 'personal_basic.alumni_school']
            ]
        },
        {
            title: 'Address Details',
            fields: [
                ['Current address line 1', 'employee_contact.local_address', 'current_address', 'address.current.address_line1'],
                ['Current address line 2', 'employee_contact.local_address1', 'current_address_line2', 'address.current.address_line2'],
                ['Current address line 3', 'employee_contact.local_address2', 'current_address_line3', 'address.current.address_line3'],
                ['Current city', 'employee_contact.city', 'current_city', 'address.current.city'],
                ['Current PIN code', 'employee_contact.pincode', 'current_pincode', 'address.current.pincode'],
                ['Current state', 'employee_contact.state', 'current_state', 'address.current.state'],
                ['Current country', 'employee_contact.country', 'current_country', 'address.current.country'],
                ['Same as permanent address', 'submission.address.same_as_permanent', 'same_as_permanent', 'address.same_as_permanent'],
                ['Permanent address line 1', 'employee_contact.permanent_address', 'permanent_address', 'address.permanent.address_line1'],
                ['Permanent address line 2', 'employee_contact.permanent_address1', 'permanent_address_line2', 'address.permanent.address_line2'],
                ['Permanent address line 3', 'employee_contact.permanent_address2', 'permanent_address_line3', 'address.permanent.address_line3'],
                ['Permanent city', 'employee_contact.perm_city', 'permanent_city', 'address.permanent.city'],
                ['Permanent PIN code', 'employee_contact.perm_pincode', 'permanent_pincode', 'address.permanent.pincode'],
                ['Permanent state', 'employee_contact.perm_state', 'permanent_state', 'address.permanent.state'],
                ['Permanent country', 'employee_contact.perm_country', 'permanent_country', 'address.permanent.country']
            ]
        },
        {
            title: 'Aadhaar & PAN Details',
            documents: ['aadhaar', 'pan'],
            fields: [
                ['Name as per Aadhaar', 'submission.documents.aadhaar.name', 'aadhaar_name', 'aadhaar_pan.aadhaar.name'],
                ['Aadhaar number', 'employee_identification.aadhar_no', 'aadhaar_number', 'aadhaar_pan.aadhaar.number'],
                ['PAN number', 'employee_identification.pan_no', 'pan_number', 'aadhaar_pan.pan.number'],
                ['Aadhaar & PAN linked', 'employee_identification.aadharlinked', 'aadhaar_pan_linked', 'aadhaar_pan.aadhaar_pan_linked']
            ]
        },
        {
            title: 'Personal Details',
            documents: ['photo', 'signature'],
            fields: [
                ['Date of birth', 'employees.date_of_birth', 'date_of_birth', 'personal_details.date_of_birth'],
                ['Gender', 'employees.gender', 'gender', 'personal_details.gender'],
                ['Marital status', 'employees.marital_status', 'marital_status', 'personal_details.marital_status'],
                ['Spouse name', 'employees.spouse_name', 'spouse_name', 'personal_details.spouse_name'],
                ['Father name', 'employees.father_name', 'father_name', 'personal_details.father_name'],
                ['Mother name', 'employees.mother_name', 'mother_name', 'personal_details.mother_name'],
                ['Blood group', 'employees.blood_group', 'blood_group', 'personal_details.blood_group']
            ]
        },
        {
            title: 'Next of Kin & Emergency Contact',
            fields: [
                ['NOK name', 'employee_contact.nok_name', 'nok_name', 'next_of_kin.selected_name'],
                ['NOK contact number', 'employee_contact.nok_contact_no', 'nok_contact', 'next_of_kin.contact'],
                ['Is emergency contact', 'submission.nok.is_emergency_contact', 'nok_is_emergency', 'next_of_kin.is_emergency'],
                ['Emergency contact number', 'employee_contact.nok_emergency_no', 'nok_emergency_contact', 'next_of_kin.emergency_contact'],
                ['NOK address line 1', 'employee_contact.nok_address', 'nok_address', 'next_of_kin.address.address_line1'],
                ['NOK address line 2', 'submission.nok.address.address2', 'nok_address_line2', 'next_of_kin.address.address_line2'],
                ['NOK address line 3', 'submission.nok.address.address3', 'nok_address_line3', 'next_of_kin.address.address_line3'],
                ['NOK city', 'submission.nok.address.city', 'nok_city', 'next_of_kin.address.city'],
                ['NOK PIN code', 'submission.nok.address.pincode', 'nok_pincode', 'next_of_kin.address.pincode'],
                ['NOK state', 'submission.nok.address.state', 'nok_state', 'next_of_kin.address.state'],
                ['NOK country', 'submission.nok.address.country', 'nok_country', 'next_of_kin.address.country']
            ]
        },
        {
            title: 'Referral Information',
            fields: [
                ['Referred by', 'submission.referral.referred_by_name', 'referred_by', 'referral.referred_by_name'],
                ['Family members working', 'submission.referral.family_working', 'family_working', 'referral.family_working'],
                ['Family working details', 'submission.referral.family_working_details', 'family_working_details', 'referral.family_working_details'],
                ['Family members studying', 'submission.referral.family_studying', 'family_studying', 'referral.family_studying'],
                ['Family studying details', 'submission.referral.family_studying_details', 'family_studying_details', 'referral.family_studying_details']
            ]
        },
        {
            title: 'Educational Qualifications',
            documents: ['education_certificate'],
            fields: [['Qualifications', 'educational_qualifications.all', 'education', 'education']]
        },
        {
            title: 'Teacher Details & Other Documents',
            documents: ['bed', 'driving', 'police_verification', 'other_document', 'ccs'],
            fields: [
                ['Are you a teacher?', 'submission.teacher_documents.is_teacher', 'is_teacher', 'teacher_documents.is_teacher'],
                ['B.Ed status', 'submission.teacher_documents.bed_status', 'pg_degree_status', 'teacher_documents.bed_status'],
                ['B.Ed institution', 'submission.teacher_documents.bed_institution', 'pg_degree_institution', 'teacher_documents.bed_institution'],
                ['Proposed graduation year', 'submission.teacher_documents.bed_proposed_year', 'bed_proposed_year', 'teacher_documents.bed_proposed_year'],
                ['Graduation year', 'submission.teacher_documents.bed_graduation_year', 'bed_graduation_year', 'teacher_documents.bed_graduation_year'],
                ['Other documents', 'submission.other_documents', 'other_documents', 'teacher_documents.other_documents']
            ]
        },
        {
            title: 'Work Experience',
            documents: ['experience_letter', 'experience_payslip'],
            fields: [
                ['Not applicable', 'submission.work_experience.not_applicable', 'experience_not_applicable', 'work_experience.not_applicable'],
                ['Experience entries', 'employee_previous_experience.all', 'work_experience', 'work_experience.entries']
            ]
        },
        {
            title: 'Employment Details',
            visible: false,
            fields: [
                ['Unit', 'employee_employment.unit', 'employment_unit', 'employment_details.unit'],
                ['Campus', 'employee_employment.branch', 'employment_campus', 'employment_details.campus'],
                ['Designation', 'employee_employment.designation', 'employment_designation', 'employment_details.designation'],
                ['Employee type (grade)', 'employee_employment.employee_type', 'employment_employee_type', 'employment_details.employee_type'],
                ['Job type', 'employee_employment.job_type', 'employment_job_type', 'employment_details.job_type'],
                ['Reporting to', 'employee_employment.reporting_to', 'employment_reporting_to', 'employment_details.reporting_to'],
                ['Date of joining', 'employee_employment.date_of_joining', 'employment_date_of_joining', 'employment_details.date_of_joining']
            ]
        }
    ];

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function showMessage(text, isError) {
        const message = document.getElementById('profile-message');
        message.textContent = text || '';
        message.classList.toggle('error', Boolean(isError));
    }

    async function api(path, options) {
        const response = await fetch(`${config.basePath}/backend-integration/api/${path}`, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                ...(options && options.headers ? options.headers : {})
            }
        });
        const data = await response.json();
        if (!response.ok || data.success === false) {
            throw new Error(data.message || `Request failed (${response.status}).`);
        }
        if (data.csrf_token) {
            csrfToken = data.csrf_token;
            if (csrfMeta) csrfMeta.content = csrfToken;
        }
        return data;
    }

    function readableValue(value) {
        if (value === null || value === undefined || value === '') return '—';
        if (Array.isArray(value)) {
            if (!value.length) return '—';
            return value.map((record, index) => {
                if (record === null || typeof record !== 'object') return String(record);
                const details = Object.entries(record)
                    .map(([key, item]) => `${key.replace(/_/g, ' ')}: ${readableValue(item)}`)
                    .join(', ');
                return `${value.length > 1 ? `Entry ${index + 1}: ` : ''}${details}`;
            }).join(' · ');
        }
        if (typeof value === 'object') return Object.entries(value)
            .map(([key, item]) => `${key.replace(/_/g, ' ')}: ${readableValue(item)}`)
            .join(' · ');
        return String(value);
    }

    function submissionChange(field) {
        return (submission?.changed_field_details || []).find((change) => change.field === field) || null;
    }

    function hasChangedField(field) {
        return Array.isArray(submission?.changed_fields) && submission.changed_fields.includes(field);
    }

    function booleanValue(value) {
        const effective = effectiveValue(value);
        return effective === true || effective === 1 ||
            ['1', 'yes', 'true'].includes(String(effective ?? '').trim().toLowerCase());
    }

    function collectionColumnChanged(field, column, rowIndex = null) {
        const change = submissionChange(field);
        if (!change) return false;
        const requested = Array.isArray(change.requested) ? change.requested : [];
        const original = Array.isArray(change.original) ? change.original : [];
        if (rowIndex !== null) {
            return String(requested[rowIndex]?.[column] ?? '').trim() !==
                String(original[rowIndex]?.[column] ?? '').trim();
        }
        if (!original.length && requested.length) {
            return String(requested[0]?.[column] ?? '').trim() !== '';
        }
        const rowCount = Math.min(requested.length, original.length);
        for (let index = 0; index < rowCount; index += 1) {
            if (String(requested[index]?.[column] ?? '').trim() !== String(original[index]?.[column] ?? '').trim()) {
                return true;
            }
        }
        return false;
    }

    function collectionOriginalValue(field, column) {
        const original = submissionChange(field)?.original;
        return Array.isArray(original) ? original[0]?.[column] : undefined;
    }

    function changedCollectionEntries(field, key) {
        const displayFields = {
            family_working_details: [
                ['TMSS ID', 'working_tmss_id'],
                ['Name', 'working_name'],
                ['Unit', 'working_unit'],
                ['Designation', 'working_designation']
            ],
            family_studying_details: [
                ['Name', 'student_name'],
                ['School', 'student_unit'],
                ['Class', 'student_class']
            ],
            education: [
                ['Type', 'qualification_type'],
                ['Name', 'qualification_details'],
                ['Institution', 'institution'],
                ['Year', 'completion_year']
            ],
            work_experience: [
                ['Company', 'company_name'],
                ['Designation', 'role'],
                ['Start date', 'start_date'],
                ['End date', 'end_date'],
                ['Details', 'experience_details']
            ]
        };
        const change = submissionChange(field);
        const fields = displayFields[key];
        if (!fields || (key !== 'education' && !change)) return [];

        const requested = Array.isArray(change?.requested) ? change.requested : [];
        const original = Array.isArray(change?.original) ? change.original : [];
        const submittedEducation = key === 'education' && Array.isArray(profileData?.submission_data?.education)
            ? profileData.submission_data.education
            : [];
        const submittedFamilyMembers = key === 'family_working_details' &&
            Array.isArray(profileData?.submission_data?.referral?.family_members)
            ? profileData.submission_data.referral.family_members
            : [];
        const rowCount = Math.max(
            requested.length,
            submittedEducation.length,
            submittedFamilyMembers.length
        );
        const entries = Array.from({ length: rowCount }, (_, index) => {
            const record = { ...(requested[index] || {}) };
            if (key === 'family_working_details' &&
                record.working_tmss_id === undefined &&
                submittedFamilyMembers[index]?.tmss_id !== undefined) {
                record.working_tmss_id = effectiveValue(submittedFamilyMembers[index].tmss_id);
            }
            const previous = original[index] || {};
            const values = change && requested[index]
                ? fields
                    .filter(([, column]) =>
                        String(record?.[column] ?? '').trim() !== String(previous[column] ?? '').trim() &&
                        record?.[column] !== null && record?.[column] !== undefined && record?.[column] !== ''
                    )
                    .map(([label, column]) => [label, record[column]])
                : [];
            if (key === 'education') {
                const submittedEntry = submittedEducation[index];
                for (const [label, field] of [
                    ['Skilled in', 'skilled_in'],
                    ['Special talents', 'special_talents']
                ]) {
                    const value = effectiveValue(submittedEntry?.[field]);
                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        values.push([label, value]);
                    }
                }
            }
            if (['family_working_details', 'family_studying_details'].includes(key)) {
                const nameColumn = key === 'family_working_details' ? 'working_name' : 'student_name';
                if (!String(record?.[nameColumn] ?? '').trim()) return null;
            }
            if (key === 'work_experience' &&
                !String(record?.company_name ?? '').trim() &&
                !String(record?.role ?? '').trim()) return null;
            if (key === 'education' &&
                !String(record?.qualification_type ?? '').trim() &&
                !String(record?.qualification_details ?? '').trim()) return null;
            return values.length ? { index, values } : null;
        }).filter(Boolean);

        if (change) {
            for (let index = requested.length; index < original.length; index += 1) {
                entries.push({ index, removed: true, values: [] });
            }
        }
        return entries;
    }

    function profileFieldChanged(field, key) {
        if (key === 'is_teacher') {
            const snapshot = snapshotValue('teacher_documents.is_teacher');
            return snapshot.found && snapshot.value !== null && snapshot.value !== undefined && snapshot.value !== '';
        }
        if ([
            'pg_degree_status',
            'pg_degree_institution',
            'bed_proposed_year',
            'bed_graduation_year'
        ].includes(key)) {
            if (!booleanValue(snapshotValue('teacher_documents.is_teacher').value)) return false;
            const snapshot = snapshotValue({
                pg_degree_status: 'teacher_documents.bed_status',
                pg_degree_institution: 'teacher_documents.bed_institution',
                bed_proposed_year: 'teacher_documents.bed_proposed_year',
                bed_graduation_year: 'teacher_documents.bed_graduation_year'
            }[key]);
            const value = effectiveValue(snapshot.value);
            return value !== null && value !== undefined && String(value).trim() !== '';
        }
        if (key === 'experience_not_applicable') {
            const snapshot = snapshotValue('work_experience.not_applicable');
            return snapshot.found && snapshot.value !== null && snapshot.value !== undefined;
        }
        if (key === 'same_as_permanent') {
            const snapshot = snapshotValue('address.same_as_permanent');
            return snapshot.found && snapshot.value !== null && snapshot.value !== undefined &&
                String(snapshot.value).trim() !== '';
        }
        if (key === 'referred_by') {
            const snapshot = snapshotValue('referral.referred_by_name');
            return snapshot.found && String(effectiveValue(snapshot.value) ?? '').trim() !== '';
        }
        if (key === 'other_documents') {
            return Array.isArray(profileData?.submission_data?.other_documents) &&
                profileData.submission_data.other_documents.length > 0;
        }
        if (key === 'alumni_unit') {
            const status = snapshotValue('personal_basic.is_alumni');
            if (status.found && !booleanValue(status.value)) return false;
        }
        if (key === 'aadhaar_name') {
            const snapshot = snapshotValue('aadhaar_pan.aadhaar.name');
            if (!snapshot.found || String(effectiveValue(snapshot.value) ?? '').trim() === '') return false;

            const submittedName = profileData?.submission_data?.documents?.aadhaar?.name ??
                profileData?.submission_data?.aadhaar_pan?.aadhaar?.name;
            const submittedValue = effectiveValue(submittedName);
            if (submittedName && typeof submittedName === 'object' &&
                Object.prototype.hasOwnProperty.call(submittedName, 'original_value')) {
                return String(submittedValue ?? '').trim() !==
                    String(submittedName.original_value ?? '').trim();
            }

            const originalName = profileData?.employee?.aadhaar_name ?? profileData?.employee?.full_name;
            return originalName === null || originalName === undefined ||
                String(submittedValue ?? '').trim() !== String(originalName).trim();
        }
        if (key === 'family_working') {
            const snapshot = snapshotValue('referral.family_working');
            return snapshot.found && String(effectiveValue(snapshot.value) ?? '').trim() !== '';
        }
        if (key === 'family_studying') {
            const snapshot = snapshotValue('referral.family_studying');
            return snapshot.found && String(effectiveValue(snapshot.value) ?? '').trim() !== '';
        }
        if (key === 'family_working_details' || key === 'family_studying_details') {
            return changedCollectionEntries('employee_referrals.all', key).length > 0;
        }
        if (key === 'education') {
            return changedCollectionEntries('educational_qualifications.all', key).length > 0;
        }
        if (key === 'work_experience') {
            return changedCollectionEntries('employee_previous_experience.all', key).length > 0;
        }
        return Boolean(field) && hasChangedField(field);
    }

    function renderChangedCollection(container, field, key) {
        changedCollectionEntries(field, key).forEach((entry) => {
            const block = element('div', 'profile-collection-entry');
            const title = key === 'education' ? 'Qualification' : key === 'work_experience' ? 'Experience' : 'Entry';
            block.append(element(
                'strong',
                'collection-heading',
                `${title} ${entry.index + 1}${entry.removed ? ' removed' : ''}`
            ));
            entry.values.forEach(([label, value]) => {
                const row = element('p', 'profile-collection-value');
                row.append(
                    element('span', 'profile-collection-label', `${label}: `),
                    document.createTextNode(readableValue(value))
                );
                block.append(row);
            });
            container.append(block);
        });
    }

    function profileValue(field, profile) {
        const definition = editFields[field];
        if (!definition) return '';
        if (definition.storage === 'submission') {
            if (definition.type === 'collection') {
                return JSON.stringify(profileCollectionValue(field));
            }
            let value = profileData?.submission_data;
            for (const part of definition.path || []) {
                if (!value || typeof value !== 'object' || !(part in value)) return '';
                value = value[part];
            }
            value = effectiveValue(value);
            if (field === 'submission.nok.is_emergency_contact') {
                if (value === null || value === undefined || value === '') return '';
                return value === true || value === 1 || value === '1' || value === 'Yes' ? 'Yes' : 'No';
            }
            if (field === 'submission.work_experience.not_applicable') {
                return value === true || value === 1 || value === '1' || value === 'Yes' ? 'Yes' : 'No';
            }
            value = effectiveValue(value);
            if (value === null || value === undefined) return '';
            if (typeof value === 'boolean') return value ? 'Yes' : 'No';
            return typeof value === 'object' ? JSON.stringify(value) : String(value);
        }
        const requestedChange = submissionChange(field);
        if (requestedChange) return requestedChange.requested === null ? '' : String(requestedChange.requested);
        const aliases = {
            'employee_contact.personal_email': 'email',
            'employee_contact.mobile_no': 'mobile',
            'employee_contact.local_address': 'current_address',
            'employee_contact.local_address1': 'current_address_line2',
            'employee_contact.local_address2': 'current_address_line3',
            'employee_contact.pincode': 'current_pincode',
            'employee_contact.city': 'current_city',
            'employee_contact.state': 'current_state',
            'employee_contact.country': 'current_country',
            'employee_contact.permanent_address': 'permanent_address',
            'employee_contact.permanent_address1': 'permanent_address_line2',
            'employee_contact.permanent_address2': 'permanent_address_line3',
            'employee_contact.perm_pincode': 'permanent_pincode',
            'employee_contact.perm_city': 'permanent_city',
            'employee_contact.perm_state': 'permanent_state',
            'employee_contact.perm_country': 'permanent_country',
            'employee_contact.nok_contact_no': 'nok_contact',
            'employee_contact.nok_emergency_no': 'nok_emergency_contact',
            'employee_identification.aadharlinked': 'aadhaar_pan_linked',
            'employee_employment.unit': 'employment_unit',
            'employees.alumni_detail': 'is_alumni',
            'employee_employment.branch': 'employment_campus',
            'employee_employment.designation': 'employment_designation',
            'employee_employment.employee_type': 'employment_employee_type',
            'employee_employment.job_type': 'employment_job_type',
            'employee_employment.reporting_to': 'employment_reporting_to',
            'employee_employment.date_of_joining': 'employment_date_of_joining'
        };
        const key = aliases[field] || definition.column;
        return profile[key] === null || profile[key] === undefined ? '' : String(profile[key]);
    }

    function profileCollectionValue(field) {
        const definition = editFields[field];
        let value = profileData?.submission_data;
        for (const part of definition.path || []) {
            if (!value || typeof value !== 'object' || !(part in value)) return [];
            value = value[part];
        }
        if (!Array.isArray(value)) return [];
        return value.map((entry) => {
            if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return {};
            const normalized = {};
            Object.entries(entry).forEach(([key, item]) => {
                normalized[key] = effectiveValue(item);
            });
            return normalized;
        });
    }

    function latestHistoryByField(history) {
        const latest = new Map();
        history.forEach((item) => {
            if (!latest.has(item.field_name)) latest.set(item.field_name, item);
        });
        return latest;
    }

    function buildInput(field, currentValue) {
        const definition = editFields[field];
        if (definition.type === 'collection') {
            return buildCollectionEditor(field, currentValue);
        }
        if (['select', 'unit', 'school', 'state', 'country'].includes(definition.type)) {
            const select = document.createElement('select');
            select.setAttribute('data-edit-field', field);
            const options = [...(fieldOptions[field] || [])];
            if (currentValue === '') {
                const placeholder = element('option', '', 'Select an option');
                placeholder.value = '';
                placeholder.selected = true;
                select.append(placeholder);
            }
            if (currentValue !== '' && !options.includes(currentValue)) options.unshift(currentValue);
            options.forEach((value) => {
                const option = element('option', '', value);
                option.value = value;
                option.selected = value === currentValue;
                select.append(option);
            });
            return select;
        }
        if (definition.type === 'pincode') {
            const input = document.createElement('input');
            input.type = 'text';
            input.inputMode = 'numeric';
            input.maxLength = 6;
            input.pattern = '[0-9]{6}';
            input.placeholder = '6-digit PIN';
            input.required = Boolean(definition.required);
            input.value = currentValue;
            input.setAttribute('data-edit-field', field);
            return input;
        }
        if (definition.max_length > 255 && field.includes('address')) {
            const textarea = document.createElement('textarea');
            textarea.maxLength = definition.max_length;
            textarea.value = currentValue;
            textarea.setAttribute('data-edit-field', field);
            return textarea;
        }
        const input = document.createElement('input');
        input.type = ['tel', 'mobile'].includes(definition.type) ? 'tel' : definition.type;
        if (!['email', 'date', 'tel'].includes(input.type)) input.type = 'text';
        input.maxLength = Math.min(definition.max_length, 65535);
        if (definition.type === 'aadhaar') {
            input.inputMode = 'numeric';
            input.pattern = '[0-9]{12}';
        }
        if (definition.type === 'pan') {
            input.maxLength = 10;
            input.pattern = '[A-Za-z]{5}[0-9]{4}[A-Za-z]';
            input.autocapitalize = 'characters';
            input.addEventListener('input', () => {
                const start = input.selectionStart;
                const end = input.selectionEnd;
                input.value = input.value.toUpperCase();
                if (start !== null && end !== null) input.setSelectionRange(start, end);
            });
        }
        if (definition.type === 'mobile') {
            input.maxLength = 10;
            input.inputMode = 'numeric';
            input.pattern = '[6-9][0-9]{9}';
        }
        input.value = currentValue;
        input.required = Boolean(definition.required);
        input.setAttribute('data-edit-field', field);
        return input;
    }

    function buildCollectionEditor(field, currentValue) {
        let rows;
        try {
            rows = JSON.parse(currentValue || '[]');
        } catch (error) {
            throw new Error(`The ${editFields[field].label} values could not be loaded for editing.`);
        }
        if (!Array.isArray(rows)) rows = [];

        const schemas = {
            'submission.referral.family_working_details': [
                ['MSS Employee ID', 'tmss_id', 'text', true],
                ['Name', 'name', 'text', true],
                ['Unit', 'unit', 'unit', true],
                ['Designation', 'designation', 'text', true]
            ],
            'submission.referral.family_studying_details': [
                ['Name', 'name', 'text', true],
                ['School', 'school', 'unit', true],
                ['Class', 'class', 'text', true]
            ],
            'educational_qualifications.all': [
                ['Qualification type', 'qualification_type', 'select', true],
                ['Qualification name', 'qualification_name', 'text', true],
                ['Institution', 'institution', 'text', true],
                ['Year of completion', 'year_of_completion', 'year', true],
                ['Skilled in', 'skilled_in', 'text', false],
                ['Special talents', 'special_talents', 'text', false]
            ],
            'employee_previous_experience.all': [
                ['Company/Institution', 'company', 'text', true],
                ['Role/Designation', 'designation', 'text', true],
                ['Starting month', 'start_month', 'month', true],
                ['Leaving month', 'end_month', 'month', true],
                ['Experience details', 'details', 'textarea', true]
            ],
            'submission.other_documents': [
                ['Document title', 'title', 'text', true]
            ]
        };
        const schema = schemas[field] || [];
        const editor = element('div', 'profile-collection-editor');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.value = JSON.stringify(rows);
        hidden.setAttribute('data-edit-field', field);
        editor.append(hidden);

        const renderRows = () => {
            editor.querySelectorAll('.profile-collection-edit-entry').forEach((row) => row.remove());
            rows.forEach((row, index) => {
                const block = element('div', 'profile-collection-edit-entry');
                block.append(element('strong', 'collection-heading', `Entry ${index + 1}`));
                schema.forEach(([label, key, type, required]) => {
                    const group = element('label', 'profile-collection-edit-field');
                    group.append(element('span', '', label));
                    group.dataset.collectionField = key;
                    let control;
                    if (type === 'select' || type === 'unit') {
                        control = document.createElement('select');
                        control.className = 'form-select';
                        const values = type === 'unit'
                            ? (fieldOptions.family_units || [])
                            : (fieldOptions['educational_qualifications.all'] || [
                                'X', 'XII', 'UG', 'PG', 'Doc', 'Diploma', 'Others'
                            ]);
                        const options = [...values];
                        if (row[key] && !options.includes(String(row[key]))) options.unshift(String(row[key]));
                        const placeholder = element('option', '', 'Select qualification type');
                        placeholder.value = '';
                        control.append(placeholder);
                        options.forEach((optionValue) => {
                            const option = element('option', '', optionValue);
                            option.value = optionValue;
                            control.append(option);
                        });
                    } else if (type === 'textarea') {
                        control = document.createElement('textarea');
                        control.className = 'form-input';
                    } else {
                        control = document.createElement('input');
                        control.className = 'form-input';
                        control.type = type === 'year' ? 'text' : type;
                    }
                    control.value = row[key] === null || row[key] === undefined ? '' : String(row[key]);
                    control.required = Boolean(required);
                    if (type === 'year') {
                        control.maxLength = 4;
                        control.pattern = '[0-9]{4}';
                    }
                    control.addEventListener('input', updateValue);
                    control.addEventListener('change', updateValue);
                    group.append(control);
                    block.append(group);
                    if (field === 'educational_qualifications.all' &&
                        ['skilled_in', 'special_talents'].includes(key)) {
                        group.hidden = row.qualification_type !== 'Others';
                        control.disabled = group.hidden;
                    }
                    function updateValue() {
                        row[key] = control.value;
                        hidden.value = JSON.stringify(rows);
                        if (field === 'educational_qualifications.all' && key === 'qualification_type') {
                            block.querySelectorAll('[data-collection-field="skilled_in"], [data-collection-field="special_talents"]')
                                .forEach((group) => {
                                    group.hidden = control.value !== 'Others';
                                    group.querySelector('input,select,textarea').disabled = group.hidden;
                                });
                        }
                    }
                });
                const remove = element('button', 'profile-collection-remove', '');
                remove.type = 'button';
                remove.setAttribute('aria-label', `Delete entry ${index + 1}`);
                remove.title = `Delete entry ${index + 1}`;
                const deleteIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                deleteIcon.setAttribute('viewBox', '0 0 24 24');
                deleteIcon.setAttribute('fill', 'none');
                deleteIcon.setAttribute('stroke', 'currentColor');
                deleteIcon.setAttribute('stroke-width', '2');
                deleteIcon.setAttribute('stroke-linecap', 'round');
                deleteIcon.setAttribute('stroke-linejoin', 'round');
                deleteIcon.setAttribute('aria-hidden', 'true');
                deleteIcon.innerHTML = '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 14H6L5 6"></path><path d="M10 11v5"></path><path d="M14 11v5"></path>';
                remove.append(deleteIcon);
                remove.addEventListener('click', () => {
                    rows.splice(index, 1);
                    hidden.value = JSON.stringify(rows);
                    renderRows();
                });
                block.append(remove);
                editor.insertBefore(block, hidden);
            });
        };

        const add = element('button', 'button button-secondary', 'Add entry');
        add.type = 'button';
        add.addEventListener('click', () => {
            rows.push({});
            hidden.value = JSON.stringify(rows);
            renderRows();
        });
        editor.append(add);
        renderRows();
        return editor;
    }

    function renderSections() {
        const container = document.getElementById('profile-sections');
        const latestHistory = latestHistoryByField(profileData.history);
        container.replaceChildren();

        sections.forEach((section) => {
            if (section.visible === false) return;

            const card = element('section', 'panel profile-section');
            const header = element('div', 'profile-section-header');
            header.append(element('h2', '', section.title));
            const editable = section.fields.filter(([, field]) => editFields[field]);
            const editButton = element('button', 'profile-edit-button', 'Edit');
            editButton.type = 'button';
            editButton.hidden = editable.length === 0;
            editButton.addEventListener('click', () => renderSectionEdit(card, section, editable));
            header.append(editButton);
            card.append(header);

            const grid = element('dl', 'profile-field-grid');
            let visibleFieldCount = 0;
            section.fields.forEach(([label, field, key, snapshotPath]) => {
                let visible = profileFieldChanged(field, key);

                if (visible && ['family_working_details', 'family_studying_details'].includes(key)) {
                    const statusPath = key === 'family_working_details'
                        ? 'referral.family_working'
                        : 'referral.family_studying';
                    visible = String(effectiveValue(snapshotValue(statusPath).value) ?? '')
                        .trim().toLowerCase() === 'yes';
                }
                if (visible && key === 'alumni_unit') {
                    visible = booleanValue(snapshotValue('personal_basic.is_alumni').value);
                }
                if (visible && [
                    'pg_degree_status',
                    'pg_degree_institution',
                    'bed_proposed_year',
                    'bed_graduation_year'
                ].includes(key)) {
                    visible = booleanValue(snapshotValue('teacher_documents.is_teacher').value);
                }
                if (visible && key === 'work_experience' &&
                    booleanValue(snapshotValue('work_experience.not_applicable').value)) {
                    visible = false;
                }

                const item = element('div', 'profile-field');
                item.hidden = !visible;
                item.append(element('dt', '', label));
                const change = field ? submissionChange(field) : null;
                const snapshot = snapshotPath ? snapshotValue(snapshotPath) : { found: false };
                const value = change
                    ? change.requested
                    : snapshot.found
                        ? snapshot.value
                        : (profileData.employee[key] ?? profileFallbackValue(key) ?? '');
                const dd = element('dd');
                if (!visible) {
                    item.append(dd);
                    grid.append(item);
                    return;
                }
                if (['family_working_details', 'family_studying_details'].includes(key)) {
                    renderChangedCollection(dd, 'employee_referrals.all', key);
                } else if (key === 'education') {
                    renderChangedCollection(dd, 'educational_qualifications.all', key);
                } else if (key === 'work_experience') {
                    renderChangedCollection(dd, 'employee_previous_experience.all', key);
                } else {
                    renderProfileValue(dd, key, value);
                }
                const teacherOriginalField = key === 'pg_degree_status'
                    ? 'pg_degree_status'
                    : key === 'pg_degree_institution'
                        ? 'pg_degree_institution'
                        : null;
                const teacherOriginalValue = teacherOriginalField
                    ? profileData.employee[teacherOriginalField]
                    : undefined;
                const teacherModified = teacherOriginalField !== null &&
                    teacherOriginalValue !== undefined &&
                    String(effectiveValue(value) ?? '') !== String(teacherOriginalValue ?? '');
                const referralStatusColumn = key === 'family_working'
                    ? 'family_members_working'
                    : key === 'family_studying'
                        ? 'family_members_studying'
                        : null;
                const referralCollectionChange = referralStatusColumn ||
                    ['family_working_details', 'family_studying_details'].includes(key)
                    ? submissionChange('employee_referrals.all')
                    : null;
                const changed = change || referralCollectionChange || teacherModified ||
                    (field ? latestHistory.get(field) : null);
                if (changed) {
                    dd.append(element('span', 'modified-badge', 'Modified'));
                    if (teacherModified) {
                        dd.append(element(
                            'span',
                            'original-value',
                            `Previous value: ${readableValue(teacherOriginalValue)}`
                        ));
                    } else if (referralStatusColumn) {
                        const originalValue = collectionOriginalValue('employee_referrals.all', referralStatusColumn);
                        if (originalValue !== undefined) {
                            dd.append(element('span', 'original-value', `Previous value: ${readableValue(originalValue)}`));
                        }
                    } else if (!referralCollectionChange &&
                        !['education', 'work_experience'].includes(key)) {
                        const originalValue = change ? change.original : changed.old_value;
                        const originalAvailable = !change || change.original_available !== false;
                        const originalDisplay = key === 'aadhaar_number' || key === 'pan_number'
                            ? maskIdentifier(originalValue)
                            : readableValue(originalValue);
                        dd.append(element(
                            'span',
                            'original-value',
                            `Previous value: ${originalAvailable ? originalDisplay : 'Unavailable'}`
                        ));
                    }
                }
                item.append(dd);
                grid.append(item);
                visibleFieldCount += 1;
            });
            card.append(grid);
            const hasDocuments = renderSectionDocuments(card, section.documents || []);
            if (visibleFieldCount || hasDocuments || editable.length) container.append(card);
        });

        if (!container.childElementCount) {
            container.append(element('p', 'empty-state', 'No changed employee information or submission documents were found.'));
        }
    }

    function snapshotValue(path) {
        let value = profileData?.submission_data;
        if (path === 'personal_basic.mobile_number') {
            const mobile = value?.personal_basic?.mobile_number ?? value?.personal_basic?.mobile;
            return mobile === undefined ? { found: false } : { found: true, value: effectiveValue(mobile) };
        }
        const aliases = {
            'work_experience.not_applicable': 'work_experience_not_applicable',
            'personal_basic.full_name': 'personal_basic.full_name.value',
            'personal_basic.email': 'personal_basic.email.value',
            'address.current.address_line1': 'address.current.address1',
            'address.current.address_line2': 'address.current.address2',
            'address.current.address_line3': 'address.current.address3',
            'address.permanent.address_line1': 'address.permanent.address1',
            'address.permanent.address_line2': 'address.permanent.address2',
            'address.permanent.address_line3': 'address.permanent.address3',
            'aadhaar_pan.aadhaar.name': 'documents.aadhaar.name',
            'aadhaar_pan.aadhaar.number': 'documents.aadhaar.number',
            'aadhaar_pan.pan.number': 'documents.pan.number',
            'aadhaar_pan.aadhaar_pan_linked': 'documents.aadhaar_pan_linked',
            'personal_details.date_of_birth': 'personal_details.dob',
            'next_of_kin.name_source': 'nok.name_source',
            'next_of_kin.selected_name': 'nok.name',
            'next_of_kin.manual_name': 'nok.name',
            'next_of_kin.contact': 'nok.contact_no',
            'next_of_kin.is_emergency': 'nok.is_emergency_contact',
            'next_of_kin.emergency_contact': 'nok.emergency_contact_no',
            'next_of_kin.address.address_line1': 'nok.address.address1',
            'next_of_kin.address.address_line2': 'nok.address.address2',
            'next_of_kin.address.address_line3': 'nok.address.address3',
            'next_of_kin.address.city': 'nok.address.city',
            'next_of_kin.address.pincode': 'nok.address.pincode',
            'next_of_kin.address.state': 'nok.address.state',
            'next_of_kin.address.country': 'nok.address.country',
            'referral.referred_by_name': 'referral.referred_by.name',
            'referral.family_working': 'referral.family_members_working',
            'referral.family_working_details': 'referral.family_members',
            'referral.family_studying': 'referral.family_members_studying',
            'referral.family_studying_details': 'referral.students',
            'education': 'education',
            'teacher_documents.is_teacher': 'teacher_details.is_teacher',
            'teacher_documents.bed_status': 'teacher_details.bed_status',
            'teacher_documents.bed_institution': 'teacher_details.institution',
            'teacher_documents.bed_proposed_year': 'teacher_details.proposed_graduation_year',
            'teacher_documents.bed_graduation_year': 'teacher_details.graduation_year',
            'teacher_documents.other_documents': 'other_documents',
            'work_experience.entries': 'work_experience'
        }
        const resolvedPath = aliases[path] || path;
        const parts = resolvedPath.replace(/\[(\d+)\]/g, '.$1').split('.');
        for (const part of parts) {
            if (!value || typeof value !== 'object' || !(part in value)) {
                if (path === 'next_of_kin.name_source') {
                    return { found: true, value: profileData?.employee?.nok_name_source || '' };
                }
                if (path === 'next_of_kin.selected_name') {
                    return { found: true, value: profileData?.employee?.nok_selected_name || '' };
                }
                if (path === 'next_of_kin.manual_name') {
                    return { found: true, value: profileData?.employee?.nok_manual_name || '' };
                }
                return { found: false };
            }
            value = value[part];
        }
        return { found: true, value: effectiveValue(value) };
    }

    function effectiveValue(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            if (value.change === 'Yes') return value.updated_value ?? '';
            if (Object.prototype.hasOwnProperty.call(value, 'value')) {
                return value.value ?? '';
            }
            if (Object.prototype.hasOwnProperty.call(value, 'original_value')) {
                if (value.verification === 'wrong' && value.corrected_value !== null &&
                    value.corrected_value !== undefined && value.corrected_value !== '') {
                    return value.corrected_value;
                }
                return value.original_value ?? '';
            }
        }
        return value;
    }

    function profileFallbackValue(key) {
        const referrals = profileData.employee.referrals || [];
        if (key === 'family_working_details') {
            return referrals.filter((record) => record.working_name).map((record) => ({
                working_tmss_id: record.working_tmss_id,
                working_name: record.working_name,
                working_unit: record.working_unit,
                working_designation: record.working_designation
            }));
        }
        if (key === 'family_studying_details') {
            return referrals.filter((record) => record.student_name).map((record) => ({
                student_name: record.student_name,
                student_unit: record.student_unit,
                student_class: record.student_class
            }));
        }
        return undefined;
    }

    function renderProfileValue(container, key, value) {
        if (key === 'mobile') {
            const number = effectiveValue(value);
            container.textContent = readableValue(number);
            const submittedMobile = profileData?.submission_data?.personal_basic?.mobile_number ??
                profileData?.submission_data?.personal_basic?.mobile;
            if (submittedMobile && typeof submittedMobile === 'object' && submittedMobile.verification === 'correct') {
                container.append(element('span', 'profile-verification-badge', 'Correct'));
            }
            return;
        }
        if (key === 'aadhaar_number' || key === 'pan_number') {
            container.textContent = maskIdentifier(effectiveValue(value));
            return;
        }
        if (['education', 'family_working_details', 'family_studying_details', 'other_documents', 'work_experience'].includes(key)) {
            renderProfileCollection(container, key, value);
            return;
        }
        if (typeof effectiveValue(value) === 'boolean') {
            container.textContent = effectiveValue(value) ? 'Yes' : 'No';
            return;
        }
        container.textContent = readableValue(effectiveValue(value));
    }

    function maskIdentifier(value) {
        const text = String(effectiveValue(value) ?? '').replace(/\s/g, '');
        if (!text) return '—';
        return `${'*'.repeat(Math.max(0, text.length - 4))}${text.slice(-4)}`;
    }

    function normalizeCollection(key, records) {
        if (!Array.isArray(records)) return [];
        return records
            .filter((record) => key !== 'other_documents' || String(record?.title || '').trim() !== '')
            .map((record) => {
                const get = (...keys) => {
                    for (const candidate of keys) {
                        if (record?.[candidate] !== undefined) return effectiveValue(record[candidate]);
                    }
                    return '';
                };
                if (key === 'education') {
                    return {
                        Type: get('qualification_type'),
                        Name: get('qualification_name', 'qualification_details'),
                        'Skilled in': get('skilled_in'),
                        'Special talents': get('special_talents'),
                        Institution: get('institution'),
                        Year: get('year_of_completion', 'completion_year'),
                        Certificate: get('certificate_name', 'legacy_certificate_name')
                    };
                }
                if (key === 'work_experience') {
                    return {
                        Company: get('company', 'company_name'),
                        Designation: get('designation', 'role'),
                        Period: `${get('start_month', 'start_date') || '—'} to ${get('end_month', 'end_date') || '—'}`,
                        Details: get('details', 'experience_details')
                    };
                }
                if (key === 'family_working_details') {
                    return {
                        'TMSS ID': get('tmss_id', 'working_tmss_id'),
                        Name: get('name', 'working_name'),
                        Unit: get('unit', 'working_unit'),
                        Designation: get('designation', 'working_designation')
                    };
                }
                if (key === 'family_studying_details') {
                    return {
                        Name: get('name', 'student_name'),
                        School: get('school', 'student_unit'),
                        Class: get('class', 'student_class')
                    };
                }
                return { Title: get('title') };
            });
    }

    function renderProfileCollection(container, key, records) {
        const collection = normalizeCollection(key, records);
        if (!collection.length) {
            container.textContent = 'No information recorded.';
            return;
        }
        collection.forEach((record, index) => {
            const block = element('div', 'profile-collection-entry');
            block.append(element('strong', 'collection-heading', `${key === 'education' ? 'Qualification' : key === 'work_experience' ? 'Experience' : key === 'other_documents' ? 'Document' : 'Entry'} ${index + 1}`));
            Object.entries(record).forEach(([label, itemValue]) => {
                if (itemValue === null || itemValue === undefined || itemValue === '') return;
                const row = element('p', 'profile-collection-value');
                row.append(element('span', 'profile-collection-label', `${label}: `), document.createTextNode(readableValue(itemValue)));
                block.append(row);
            });
            container.append(block);
        });
    }

    function documentTypeKey(value) {
        const type = String(value || '').toLowerCase().replace(/[^a-z]/g, '');
        const aliases = {
            aadhaar: 'aadhaar', aadhar: 'aadhaar', aadharcopy: 'aadhaar',
            pan: 'pan', pancopy: 'pan', photo: 'photo', resume: 'resume',
            signature: 'signature', staffsign: 'signature',
            bed: 'bed', pgdegreedocument: 'bed', pgdegreedoc: 'bed',
            driving: 'driving', drivingdocument: 'driving', drivinglicense: 'driving',
            policeverification: 'police_verification', otherdocument: 'other_document',
            others: 'other_document', ccs: 'ccs',
            educationcertificate: 'education_certificate',
            experienceletter: 'experience_letter', experiencepayslip: 'experience_payslip'
        };
        return aliases[type] || '';
    }

    function submissionDocumentChanged(type, storedPath, rowIndex, uploadedDocument) {
        const directFields = {
            aadhaar: 'employee_identification.aadhar_copy',
            pan: 'employee_identification.pan_copy',
            photo: 'employee_identification.photo',
            resume: 'employee_identification.resume',
            signature: 'employee_identification.staff_sign',
            driving: 'employee_identification.driving'
        };
        if (directFields[type] && hasChangedField(directFields[type])) return true;
        if (type === 'education_certificate' &&
            collectionColumnChanged('educational_qualifications.all', 'certificate_path', rowIndex)) return true;
        if (['experience_letter', 'experience_payslip'].includes(type) &&
            collectionColumnChanged(
                'employee_previous_experience.all',
                type === 'experience_letter' ? 'experience_letter_path' : 'pay_slip',
                rowIndex
            )) {
            return true;
        }

        const normalizedPath = String(storedPath || '').replace(/\\/g, '/');
        return /(^|\/)employees_temp_documents\//.test(normalizedPath) ||
            uploadedDocument?.is_temporary === true ||
            uploadedDocument?.is_temporary === 1 ||
            uploadedDocument?.is_temporary === '1';
    }

    function submissionDocumentLinks(types, includeUnchanged = false) {
        const links = [];
        const data = profileData.submission_data || {};
        const add = (type, storedPath, rowIndex = null, column = '') => {
            if (!types.includes(type) || typeof storedPath !== 'string' || !storedPath.trim()) return;
            const storedName = storedPath.replace(/\\/g, '/').split('/').pop();
            const uploadedDocument = (profileData.documents || []).find((item) =>
                item.stored_name === storedName && documentTypeKey(item.document_type) === type
            );
            if (!includeUnchanged &&
                !submissionDocumentChanged(type, storedPath, rowIndex, uploadedDocument)) return;
            const name = uploadedDocument?.original_name || storedName;
            if (!name || links.some((item) => item.name === name && item.type === type)) return;
            const params = new URLSearchParams({
                submission_id: String(config.submissionId),
                document: type
            });
            if (rowIndex !== null) {
                params.set('row', String(rowIndex));
            }
            if (column) params.set('column', column);
            links.push({
                name,
                type,
                rowId: rowIndex,
                url: `${config.basePath}/backend-integration/api/preview-submission-document.php?${params}`
            });
        };
        add('resume', data.documents?.resume?.file_path);
        add('aadhaar', data.documents?.aadhaar?.file_path);
        add('pan', data.documents?.pan?.file_path);
        add('photo', data.personal_details?.photo_file_path);
        add('signature', data.personal_details?.signature_file_path);
        add('bed', data.teacher_details?.document_file_path);
        (Array.isArray(data.other_documents) ? data.other_documents : []).forEach((entry, index) => {
            const type = documentTypeKey(entry?.type) || 'other_document';
            add(type, entry?.file_path, index, 'file_path');
        });
        if (types.includes('education_certificate') && Array.isArray(data.education)) {
            data.education.forEach((entry, index) => {
                add('education_certificate', entry?.certificate_file_path, index, 'certificate_file_path');
            });
        }
        if (Array.isArray(data.work_experience)) {
            data.work_experience.forEach((entry, index) => {
                add('experience_letter', entry?.experience_letter_file_path, index, 'experience_letter_file_path');
                add('experience_payslip', entry?.pay_slip_file_path, index, 'pay_slip_file_path');
            });
        }
        return links;
    }

    function renderSectionDocuments(card, types, includeUnchanged = false) {
        const documents = submissionDocumentLinks(types, includeUnchanged);
        if (!documents.length) return false;
        const list = element('div', 'profile-document-list');
        documents.forEach((item) => {
            const row = element('div', 'profile-document-row');
            const link = element('a', 'document-item');
            if (item.url) {
                link.href = item.url;
            } else {
                const params = new URLSearchParams({
                    type: item.type,
                    employee_id: String(config.employeeId)
                });
                if (item.rowId) params.set('row_id', String(item.rowId));
                link.href = `${config.basePath}/backend-integration/api/view-existing-document.php?${params.toString()}`;
            }
            link.target = '_blank';
            link.rel = 'noopener';
            link.setAttribute('aria-label', `Preview ${item.name}`);
            link.title = `Preview ${item.name}`;
            link.append(
                element('span', 'document-item-name', item.name),
                createEyeIcon()
            );
            row.append(link);
            list.append(row);
        });
        card.append(list);
        return true;
    }

    function createEyeIcon() {
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('stroke-width', '2');
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        return icon;
    }

    function renderSectionEdit(card, section, editableFields) {
        const grid = card.querySelector('.profile-field-grid');
        const editButton = card.querySelector('.profile-edit-button');
        editButton.hidden = true;
        editableFields.forEach(([label, field, key]) => {
            const item = Array.from(grid.children).find((child) => child.querySelector('dt')?.textContent === label);
            if (!item) return;
            item.hidden = false;
            const value = profileValue(field, profileData.employee);
            item.querySelector('dd').replaceChildren(buildInput(field, value));
        });
        const syncConditionalFields = () => {
            const getValue = (field) => getEditInputValue(card, field);
            const rules = [
                {
                    field: 'employees.alumni_unit',
                    show: getValue('employees.alumni_detail') === 'Yes',
                    required: true,
                    clearOnHide: true
                },
                {
                    field: 'employees.spouse_name',
                    show: getValue('employees.marital_status') === 'Married',
                    required: true,
                    clearOnHide: true
                },
                {
                    field: 'employee_contact.nok_emergency_no',
                    show: getValue('submission.nok.is_emergency_contact') === 'No',
                    required: true,
                    clearOnHide: true
                },
                ...[
                    'employee_contact.permanent_address',
                    'employee_contact.permanent_address1',
                    'employee_contact.permanent_address2',
                    'employee_contact.perm_city',
                    'employee_contact.perm_pincode',
                    'employee_contact.perm_state',
                    'employee_contact.perm_country'
                ].map((field) => ({
                    field,
                    show: getValue('submission.address.same_as_permanent') === 'No',
                    required: false,
                    clearOnHide: false
                })),
                {
                    field: 'submission.referral.family_working_details',
                    show: getValue('submission.referral.family_working') === 'Yes',
                    required: false,
                    clearOnHide: false
                },
                {
                    field: 'submission.referral.family_studying_details',
                    show: getValue('submission.referral.family_studying') === 'Yes',
                    required: false,
                    clearOnHide: false
                },
                ...[
                    'submission.teacher_documents.bed_status',
                    'submission.teacher_documents.bed_institution'
                ].map((field) => ({
                    field,
                    show: getValue('submission.teacher_documents.is_teacher') === 'Yes',
                    required: true,
                    clearOnHide: true
                })),
                {
                    field: 'submission.teacher_documents.bed_proposed_year',
                    show: getValue('submission.teacher_documents.is_teacher') === 'Yes' &&
                        getValue('submission.teacher_documents.bed_status') === 'Joined',
                    required: true,
                    clearOnHide: true
                },
                {
                    field: 'submission.teacher_documents.bed_graduation_year',
                    show: getValue('submission.teacher_documents.is_teacher') === 'Yes' &&
                        getValue('submission.teacher_documents.bed_status') === 'Completed',
                    required: true,
                    clearOnHide: true
                },
                {
                    field: 'submission.other_documents',
                    show: getValue('submission.teacher_documents.is_teacher') === 'No' ||
                        (getValue('submission.teacher_documents.is_teacher') === 'Yes' &&
                            ['prem vihar', 'shanthi sadan'].includes(
                                String(profileData.employee.employment_unit || '').trim().toLowerCase()
                            )),
                    required: false,
                    clearOnHide: false
                },
                {
                    field: 'employee_previous_experience.all',
                    show: getValue('submission.work_experience.not_applicable') === 'No',
                    required: false,
                    clearOnHide: false
                }
            ];
            rules.forEach(({ field, show, required, clearOnHide }) => {
                const input = Array.from(card.querySelectorAll('[data-edit-field]'))
                    .find((candidate) => candidate.dataset.editField === field);
                if (!input) return;
                const item = input.closest('.profile-field');
                if (item) item.hidden = !show;
                input.disabled = !show;
                input.required = show && required;
                if (!show && clearOnHide) input.value = '';
            });
        };
        card.addEventListener('input', syncConditionalFields);
        card.addEventListener('change', syncConditionalFields);
        syncConditionalFields();
        setupAddressAutocomplete(card, section);
        const actions = element('div', 'profile-edit-actions');
        const cancel = element('button', 'button button-secondary', 'Cancel');
        cancel.type = 'button';
        cancel.addEventListener('click', renderSections);
        const save = element('button', 'button', 'Save changes');
        save.type = 'button';
        save.addEventListener('click', async () => {
            const invalidInput = Array.from(card.querySelectorAll('[data-edit-field]'))
                .find((input) => !input.disabled && !input.checkValidity());
            const invalidCollectionInput = Array.from(card.querySelectorAll(
                '.profile-collection-edit-entry input, .profile-collection-edit-entry select, .profile-collection-edit-entry textarea'
            )).find((input) =>
                !input.disabled && !input.closest('.profile-field')?.hidden && !input.checkValidity()
            );
            if (invalidInput || invalidCollectionInput) {
                (invalidInput || invalidCollectionInput).reportValidity();
                return;
            }
            const changes = {};
            card.querySelectorAll('[data-edit-field]').forEach((input) => {
                const field = input.dataset.editField;
                if (input.disabled || input.value === profileValue(field, profileData.employee)) return;
                changes[field] = editFields[field].type === 'collection'
                    ? JSON.parse(input.value || '[]')
                    : input.value;
            });
            const emergencyStatus = getEditInputValue(card, 'submission.nok.is_emergency_contact');
            if (emergencyStatus === 'No') {
                changes['employee_contact.nok_emergency_no'] =
                    getEditInputValue(card, 'employee_contact.nok_emergency_no');
            } else if (emergencyStatus === 'Yes') {
                changes['employee_contact.nok_emergency_no'] = '';
            }

            const alumniStatus = getEditInputValue(card, 'employees.alumni_detail');
            if (alumniStatus === 'Yes') {
                changes['employees.alumni_unit'] = getEditInputValue(card, 'employees.alumni_unit');
            }
            const maritalStatus = getEditInputValue(card, 'employees.marital_status');
            if (maritalStatus === 'Married') {
                changes['employees.spouse_name'] = getEditInputValue(card, 'employees.spouse_name');
            }
            const teacherStatus = getEditInputValue(card, 'submission.teacher_documents.is_teacher');
            const bedStatus = getEditInputValue(card, 'submission.teacher_documents.bed_status');
            if (teacherStatus === 'No') {
                changes['submission.teacher_documents.bed_status'] = '';
                changes['submission.teacher_documents.bed_institution'] = '';
                changes['submission.teacher_documents.bed_proposed_year'] = '';
                changes['submission.teacher_documents.bed_graduation_year'] = '';
                changes['submission.teacher_details.document_file_path'] = '';
            } else if (teacherStatus === 'Yes' && bedStatus === 'Joined') {
                changes['submission.teacher_documents.bed_graduation_year'] = '';
            } else if (teacherStatus === 'Yes' && bedStatus === 'Completed') {
                changes['submission.teacher_documents.bed_proposed_year'] = '';
            }
            if (!Object.keys(changes).length) {
                renderSections();
                showMessage('No values changed.', false);
                return;
            }
            save.disabled = true;
            try {
                const result = await api('admin-employee.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'update',
                        employee_id: config.employeeId,
                        submission_id: config.submissionId,
                        changes
                    })
                });
                showMessage(result.message, false);
                await Promise.all([loadProfile(), loadSubmission()]);
            } catch (error) {
                showMessage(error.message, true);
                save.disabled = false;
            }
        });
        actions.append(cancel, save);
        card.append(actions);
    }

    function getEditInputValue(card, field) {
        const input = Array.from(card.querySelectorAll('[data-edit-field]'))
            .find((candidate) => candidate.dataset.editField === field);
        return input ? input.value : '';
    }

    function setupAddressAutocomplete(card, section) {
        const addressGroups = section.title === 'Address Details'
            ? [
                {
                    kind: 'current',
                    pincode: 'employee_contact.pincode',
                    fields: {
                        city: 'employee_contact.city',
                        state: 'employee_contact.state',
                        country: 'employee_contact.country'
                    }
                },
                {
                    kind: 'permanent',
                    pincode: 'employee_contact.perm_pincode',
                    fields: {
                        city: 'employee_contact.perm_city',
                        state: 'employee_contact.perm_state',
                        country: 'employee_contact.perm_country'
                    }
                }
            ]
            : section.title === 'Next of Kin & Emergency Contact'
                ? [{
                    kind: 'nok',
                    pincode: 'submission.nok.address.pincode',
                    fields: {
                        city: 'submission.nok.address.city',
                        state: 'submission.nok.address.state',
                        country: 'submission.nok.address.country'
                    }
                }]
                : [];
        addressGroups.forEach(({ kind, pincode: pincodeField, fields }) => {
            const pincode = card.querySelector(`[data-edit-field="${pincodeField}"]`);
            if (!pincode) return;
            pincode.dataset.addressKind = kind;
            pincode.dataset.originalPincode = pincode.value;
            pincode.addEventListener('input', () => schedulePincodeLookup(pincode));
            pincode.addEventListener('blur', () => {
                if (pincode.value !== pincode.dataset.originalPincode && /^[0-9]{6}$/.test(pincode.value)) {
                    lookupPincode(pincode);
                }
            });
            Object.values(fields).forEach((field) => {
                const target = card.querySelector(`[data-edit-field="${field}"]`);
                if (!target) return;
                target.dataset.autoFilled = 'true';
                const markManualEdit = () => {
                    target.dataset.autoFilled = 'false';
                };
                target.addEventListener('input', markManualEdit);
                target.addEventListener('change', markManualEdit);
            });
        });
    }

    function schedulePincodeLookup(input) {
        const timerKey = input.dataset.addressKind || input.dataset.editField;
        clearTimeout(pincodeLookupTimers.get(timerKey));
        if (!/^[0-9]{6}$/.test(input.value)) return;
        pincodeLookupTimers.set(timerKey, setTimeout(() => lookupPincode(input), 300));
    }

    async function lookupPincode(input) {
        const pincode = input.value;
        const addressKind = input.dataset.addressKind;
        if (!addressKind || !/^[0-9]{6}$/.test(pincode)) return;
        if (pincodeLookupCache.has(pincode)) {
            fillAddressFromPincode(addressKind, pincodeLookupCache.get(pincode));
            return;
        }

        input.classList.add('pincode-loading');
        try {
            const response = await fetch(
                `${config.basePath}/backend-integration/api/pincode-lookup.php?pincode=${encodeURIComponent(pincode)}`,
                { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const result = await response.json();
            if (!response.ok || !result.success || !result.data) {
                throw new Error(result.message || 'PIN code lookup failed.');
            }
            if (input.value !== pincode || !input.isConnected) return;
            pincodeLookupCache.set(pincode, result.data);
            fillAddressFromPincode(addressKind, result.data);
        } catch (error) {
            showMessage(error.message || 'Unable to look up this PIN code.', true);
        } finally {
            input.classList.remove('pincode-loading');
        }
    }

    function fillAddressFromPincode(addressKind, address) {
        const card = Array.from(document.querySelectorAll('.profile-section'))
            .find((section) => section.querySelector(`[data-address-kind="${addressKind}"]`));
        if (!card) return;
        const fieldGroups = {
            current: {
                city: 'employee_contact.city',
                state: 'employee_contact.state',
                country: 'employee_contact.country'
            },
            permanent: {
                city: 'employee_contact.perm_city',
                state: 'employee_contact.perm_state',
                country: 'employee_contact.perm_country'
            },
            nok: {
                city: 'submission.nok.address.city',
                state: 'submission.nok.address.state',
                country: 'submission.nok.address.country'
            }
        };
        const fields = fieldGroups[addressKind];
        if (!fields) return;

        Object.entries(fields).forEach(([name, field]) => {
            if (!address[name]) return;
            const input = card.querySelector(`[data-edit-field="${field}"]`);
            if (!input || input.disabled) return;
            if (input.value && input.dataset.autoFilled !== 'true') return;
            if (input.tagName === 'SELECT') {
                const match = Array.from(input.options)
                    .find((option) => option.value.toLowerCase() === String(address[name]).toLowerCase());
                if (!match) return;
                input.value = match.value;
            } else {
                input.value = address[name];
            }
            input.dataset.autoFilled = 'true';
        });
    }

    function renderHistory() {
        const container = document.getElementById('history-list');
        if (!container) return;
        container.replaceChildren();
        if (!profileData.history.length) {
            container.append(element('p', 'empty-state', 'No administrator changes have been recorded.'));
            return;
        }
        profileData.history.forEach((item) => {
            const entry = element('div', 'history-item');
            entry.append(element('strong', 'history-field', editFields[item.field_name]?.label || item.field_name));
            const values = element('div', 'history-values');
            values.append(
                element('span', '', `Previous: ${readableValue(item.old_value)}`),
                element('strong', '', `Current: ${readableValue(item.new_value)}`)
            );
            entry.append(values);
            entry.append(element('span', 'history-actor', `${item.changed_by_name} · ${item.changed_by_role} · ${item.created_at}`));
            container.append(entry);
        });
    }

    async function loadProfile() {
        const query = new URLSearchParams({
            employee_id: String(config.employeeId),
            submission_id: String(config.submissionId)
        });
        const data = await api(`admin-employee.php?${query.toString()}`, { method: 'GET' });
        profileData = data;
        editFields = data.fields || {};
        fieldOptions = data.field_options || {};
        renderHistory();
        if (submission) renderSections();
        document.getElementById('employee-name').textContent = data.employee.full_name || 'Employee profile';
        document.getElementById('employee-identifiers').textContent =
            `Employee ID: ${data.employee.mss_employee_id || data.employee.employee_id} · ${data.employee.employment_unit || 'Unit not recorded'} · ${data.employee.employment_designation || 'Designation not recorded'}`;
    }

    async function loadSubmission() {
        const data = await api(`review-submission.php?status=all&submission_id=${encodeURIComponent(config.submissionId)}`, { method: 'GET' });
        submission = (data.submissions || []).find((item) => item.id === config.submissionId);
        if (!submission) throw new Error('This employee submission could not be found.');
        const badge = document.getElementById('employee-status');
        badge.textContent = submission.status;
        badge.className = `status-badge status-${submission.status}`;
        document.getElementById('employee-name').textContent = submission.employee_name || `Employee ${submission.employee_id}`;
        document.getElementById('employee-identifiers').textContent =
            `Employee ID: ${submission.employee_number || submission.employee_id} · ${submission.unit || 'Unit not recorded'} · ${submission.reference_number || `Submission ${submission.id}`}`;
        document.getElementById('profile-approve').hidden = submission.status !== 'pending';
        document.getElementById('profile-approve').disabled = submission.status !== 'pending';
        const deleteButton = document.getElementById('profile-delete');
        if (deleteButton) deleteButton.hidden = submission.status !== 'pending';
        if (profileData) renderSections();
    }

    async function approveSubmission() {
        if (!submission || submission.status !== 'pending') return;
        if (!window.confirm('Approve this employee submission and apply the displayed changes?')) return;
        const button = document.getElementById('profile-approve');
        button.disabled = true;
        button.textContent = 'Approving…';
        try {
            const result = await api('review-submission.php', {
                method: 'POST',
                body: JSON.stringify({
                    submission_id: config.submissionId,
                    decision: 'approve'
                })
            });
            showMessage(result.message, false);
            await Promise.all([loadSubmission(), loadProfile()]);
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            if (submission && submission.status === 'pending') {
                button.disabled = false;
                button.textContent = 'Approve submission';
            }
        }
    }

    function openDeleteConfirmation() {
        if (!submission || submission.status !== 'pending') return;
        document.getElementById('delete-confirmation').showModal();
    }

    async function deleteSubmission() {
        if (!submission || submission.status !== 'pending') return;
        const button = document.getElementById('profile-delete');
        const approveButton = document.getElementById('profile-approve');
        button.disabled = true;
        button.textContent = 'Deleting…';
        approveButton.disabled = true;
        try {
            const result = await api('review-submission.php', {
                method: 'POST',
                body: JSON.stringify({
                    submission_id: config.submissionId,
                    decision: 'delete'
                })
            });
            showMessage(result.message, false);
            window.location.assign(`${config.basePath}/admin`);
        } catch (error) {
            showMessage(error.message, true);
            button.disabled = false;
            button.textContent = 'Delete submission';
            approveButton.disabled = false;
        }
    }

    document.getElementById('profile-approve').addEventListener('click', approveSubmission);
    const deleteButton = document.getElementById('profile-delete');
    if (deleteButton) deleteButton.addEventListener('click', openDeleteConfirmation);
    document.getElementById('cancel-delete').addEventListener('click', () => {
        document.getElementById('delete-confirmation').close();
    });
    document.getElementById('confirm-delete').addEventListener('click', () => {
        document.getElementById('delete-confirmation').close();
        deleteSubmission();
    });

    Promise.all([loadProfile(), loadSubmission()]).catch((error) => {
        showMessage(error.message, true);
        const profileSections = document.getElementById('profile-sections');
        if (profileSections) {
            profileSections.replaceChildren(element('p', 'empty-state', error.message));
        }
    });
})();
