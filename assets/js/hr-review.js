(function() {
    'use strict';

    const config = window.HR_REVIEW_CONFIG;
    const message = document.getElementById('page-message');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    let csrfToken = csrfMeta ? csrfMeta.content : '';

    const approvedFields = [
        'employees.date_of_birth', 'employees.gender', 'employees.marital_status',
        'employees.spouse_name', 'employees.father_name', 'employees.mother_name',
        'employees.blood_group', 'employees.alumni_detail', 'employees.alumni_unit',
        'employee_contact.personal_email', 'employee_contact.local_address',
        'employee_contact.local_address1', 'employee_contact.local_address2',
        'employee_contact.pincode', 'employee_contact.city', 'employee_contact.state',
        'employee_contact.country', 'employee_contact.permanent_address',
        'employee_contact.permanent_address1', 'employee_contact.permanent_address2',
        'employee_contact.perm_pincode', 'employee_contact.perm_city',
        'employee_contact.perm_state', 'employee_contact.perm_country',
        'employee_contact.nok_name', 'employee_contact.nok_contact_no',
        'employee_contact.nok_address', 'employee_contact.nok_emergency_no',
        'employee_identification.aadhar_no', 'employee_identification.aadhar_copy',
        'employee_identification.aadharlinked', 'employee_identification.pan_no',
        'employee_identification.pan_copy', 'employee_identification.photo',
        'employee_identification.resume', 'employee_identification.staff_sign',
        'employee_identification.driving', 'employee_employment.unit',
        'employee_employment.branch', 'employee_employment.designation',
        'employee_employment.employee_type', 'employee_employment.job_type',
        'employee_employment.reporting_to', 'employee_employment.date_of_joining',
        'employee_referrals.all',
        'educational_qualifications.all', 'employee_previous_experience.all'
    ];

    const fieldLabels = {
        'employees.date_of_birth': 'Date of birth',
        'employees.marital_status': 'Marital status',
        'employees.alumni_detail': 'Alumni status',
        'employees.alumni_unit': 'Alumni school',
        'employee_contact.personal_email': 'Personal email',
        'employee_contact.local_address': 'Current address line 1',
        'employee_contact.local_address1': 'Current address line 2',
        'employee_contact.local_address2': 'Current address line 3',
        'employee_contact.permanent_address': 'Permanent address line 1',
        'employee_contact.permanent_address1': 'Permanent address line 2',
        'employee_contact.permanent_address2': 'Permanent address line 3',
        'employee_contact.nok_name': 'Emergency contact name',
        'employee_contact.nok_contact_no': 'Emergency contact number',
        'employee_contact.nok_address': 'Emergency contact address',
        'employee_contact.nok_emergency_no': 'Emergency contact alternate number',
        'employee_identification.aadhar_no': 'Aadhaar number',
        'employee_identification.aadhar_copy': 'Aadhaar document',
        'employee_identification.aadharlinked': 'Aadhaar and PAN linked',
        'employee_identification.pan_no': 'PAN number',
        'employee_identification.pan_copy': 'PAN document',
        'employee_identification.staff_sign': 'Signature',
        'employee_identification.driving': 'Driving license',
        'employee_employment.branch': 'Branch / campus',
        'employee_employment.employee_type': 'Employee type',
        'employee_employment.job_type': 'Job type',
        'employee_employment.reporting_to': 'Reporting to',
        'employee_referrals.all': 'Referral details',
        'educational_qualifications.all': 'Educational qualifications',
        'employee_previous_experience.all': 'Previous work experience'
    };
    const documentFields = new Set([
        'employee_identification.aadhar_copy',
        'employee_identification.pan_copy',
        'employee_identification.photo',
        'employee_identification.resume',
        'employee_identification.staff_sign',
        'employee_identification.driving'
    ]);
    const collectionDocumentColumns = new Set([
        'certificate_path', 'pay_slip', 'experience_letter_path'
    ]);
    const collectionFields = new Set([
        'employee_referrals.all',
        'educational_qualifications.all',
        'employee_previous_experience.all'
    ]);
    const collectionColumnLabels = {
        // referral_source: 'Referral source',
        referred_by: 'Referred by',
        family_members_working: 'Family members working',
        working_name: 'Working family member',
        working_unit: 'Working unit',
        working_designation: 'Working designation',
        family_members_studying: 'Family members studying',
        student_name: 'Student name',
        student_unit: 'Student unit',
        student_class: 'Student class',
        qualification_type: 'Qualification type',
        qualification_details: 'Qualification',
        institution: 'Institution',
        completion_year: 'Completion year',
        certificate_path: 'Certificate',
        company_name: 'Company',
        role: 'Role',
        start_date: 'Start date',
        end_date: 'End date',
        experience_details: 'Details',
        pay_slip: 'Payslip',
        experience_letter_path: 'Experience letter',
        mobile_no: 'Mobile'
    };
    let activeSubmission = null;
    let reviewSteps = [];
    let activeStepIndex = 0;
    let queueRequestId = 0;

    function showMessage(text, isError) {
        if (!message) return;
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

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function formatChangeValue(value) {
        if (value === null || value === undefined || value === '') return '—';
        if (typeof value === 'object') return JSON.stringify(value);
        return String(value);
    }

    function buildDocumentPreviewLink(submission, field, side, rowIndex, column) {
        const link = element('a', 'document-preview-link');
        const params = new URLSearchParams({
            submission_id: String(submission.id),
            field,
            side
        });
        if (rowIndex !== undefined) params.set('row', String(rowIndex));
        if (column) params.set('column', column);
        link.href = `${config.basePath}/backend-integration/api/preview-submission-document.php?${params.toString()}`;
        link.target = '_blank';
        link.rel = 'noopener';
        link.title = 'Preview document in a new browser tab';
        link.setAttribute('aria-label', 'Preview document in a new browser tab');

        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('aria-hidden', 'true');
        icon.setAttribute('focusable', 'false');
        const eye = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        eye.setAttribute('d', 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Zm10 4a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0-2a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z');
        eye.setAttribute('fill', 'currentColor');
        icon.append(eye);
        link.append(icon, element('span', 'visually-hidden', 'Preview document'));
        return link;
    }

    function appendValue(cell, value, submission, field, side, rowIndex, column) {
        if (collectionDocumentColumns.has(column || '') && typeof value === 'string' && value !== '') {
            cell.append(buildDocumentPreviewLink(submission, field, side, rowIndex, column));
            return;
        }
        if (documentFields.has(field) && typeof value === 'string' && value !== '') {
            cell.append(buildDocumentPreviewLink(submission, field, side));
            return;
        }
        if (collectionFields.has(field)) {
            if (!Array.isArray(value) || value.length === 0) {
                cell.textContent = '—';
                return;
            }
            value.forEach((record, index) => {
                const item = element('div', 'collection-value');
                if (value.length > 1) item.append(element('strong', 'collection-index', `Entry ${index + 1}`));
                Object.entries(record || {}).forEach(([key, itemValue]) => {
                    const row = element('div', 'collection-detail');
                    row.append(element('span', 'collection-label', collectionColumnLabels[key] || key.replace(/_/g, ' ')));
                    const itemCell = element('span', 'collection-item-value');
                    appendValue(itemCell, itemValue, submission, field, side, index, key);
                    row.append(itemCell);
                    item.append(row);
                });
                cell.append(item);
            });
            return;
        }
        cell.textContent = formatChangeValue(value);
    }

    function buildSubmissionCard(submission) {
        const card = element('article', 'submission-card');
        const heading = element('div', 'submission-card-heading');
        const title = element('h3', '', submission.employee_name || `Employee ${submission.employee_id}`);
        const badge = element('span', `status-badge status-${submission.status}`, submission.status);
        heading.append(title, badge);
        const meta = element('div', 'submission-meta');
        meta.append(
            element('span', '', `Employee ID: ${submission.employee_id}${submission.employee_number ? ` (${submission.employee_number})` : ''}${submission.unit ? ` · ${submission.unit}` : ''}`),
            element('span', '', `Mobile: ${submission.mobile || '—'}`),
            element('span', '', `${submission.reference_number || `Submission ${submission.id}`} · ${submission.submitted_at}`)
        );
        const review = element('button', 'button review-button', 'Review Request');
        review.type = 'button';
        review.addEventListener('click', () => openReview(submission));
        card.append(heading, meta, review);
        return card;
    }

    function categoryForField(field) {
        const table = field.split('.')[0];
        const categories = {
            employees: ['Personal Details', 'Review employee profile corrections.'],
            employee_contact: ['Contact Details', 'Review addresses and contact information.'],
            employee_employment: ['Employment Details', 'Review role, unit, and employment information.'],
            employee_identification: ['Documents & Identification', 'Review identity details and document changes.'],
            employee_referrals: ['Family & Referrals', 'Review family and referral information.'],
            educational_qualifications: ['Education', 'Review education and qualification changes.'],
            employee_previous_experience: ['Work Experience', 'Review previous employment details.']
        };
        return categories[table] || ['Other Changes', 'Review the remaining requested corrections.'];
    }

    function updateSelectionCount() {
        const body = document.getElementById('changed-fields-body');
        if (!body) return;
        const selected = body.querySelectorAll('input[type="checkbox"]:checked').length;
        const counter = document.getElementById('selected-count');
        const approve = document.getElementById('approve-selected');
        const reject = document.getElementById('reject-selected');
        if (counter) counter.textContent = `${selected} selected`;
        if (approve) approve.disabled = selected === 0;
        if (reject) reject.disabled = selected === 0;
        const selectAll = document.getElementById('select-step-fields');
        if (selectAll) {
            const pending = body.querySelectorAll('input[type="checkbox"]');
            selectAll.checked = pending.length > 0 && selected === pending.length;
            selectAll.disabled = pending.length === 0;
        }
    }

    function renderReview() {
        const dialog = document.getElementById('review-dialog');
        const body = document.getElementById('changed-fields-body');
        const actions = document.getElementById('review-actions');
        const completedNotice = document.getElementById('completed-notice');
        const empty = document.getElementById('review-empty');
        if (!activeSubmission || !dialog || !body || !actions || !completedNotice || !empty) return;
        const submission = activeSubmission;
        const currentStep = reviewSteps[activeStepIndex];
        const fieldDecisions = submission.field_decisions || {};

        const steps = document.getElementById('review-steps');
        steps.replaceChildren();
        reviewSteps.forEach((step, index) => {
            const item = document.createElement('li');
            const button = element('button', 'review-step-button');
            button.type = 'button';
            button.classList.toggle('is-active', index === activeStepIndex);
            button.classList.toggle('is-complete', step.changes.every((change) => fieldDecisions[change.field]));
            button.setAttribute('aria-current', index === activeStepIndex ? 'step' : 'false');
            button.append(
                element('span', 'step-number', String(index + 1)),
                element('span', 'step-label', step.title),
                element('span', 'step-count', `${step.changes.length} ${step.changes.length === 1 ? 'change' : 'changes'}`)
            );
            button.addEventListener('click', () => {
                activeStepIndex = index;
                renderReview();
            });
            item.append(button);
            steps.append(item);
        });

        const changed = submission.changed_field_details || [];
        const resolvedCount = changed.filter((change) => fieldDecisions[change.field]).length;
        document.getElementById('review-total-progress').textContent = `${resolvedCount} / ${changed.length} reviewed`;
        document.getElementById('review-progress-bar').style.width = changed.length
            ? `${Math.round(resolvedCount / changed.length * 100)}%`
            : '0%';
        document.getElementById('review-step-overline').textContent = `STEP ${activeStepIndex + 1}`;
        document.getElementById('review-step-title').textContent = currentStep ? currentStep.title : 'Changed fields';
        document.getElementById('review-step-description').textContent = currentStep
            ? currentStep.description
            : 'No changed fields were recorded for this request.';
        document.getElementById('step-change-count').textContent = currentStep
            ? `${currentStep.changes.length} ${currentStep.changes.length === 1 ? 'change' : 'changes'}`
            : '0 changes';
        document.getElementById('step-indicator').textContent = `Step ${activeStepIndex + 1} of ${reviewSteps.length || 1}`;
        const previous = document.getElementById('previous-step');
        const next = document.getElementById('next-step');
        previous.disabled = activeStepIndex === 0;
        next.disabled = activeStepIndex >= reviewSteps.length - 1;
        const dots = document.getElementById('step-dots');
        dots.replaceChildren(...reviewSteps.map((_, index) => {
            const dot = element('span', 'step-dot');
            dot.classList.toggle('is-active', index === activeStepIndex);
            return dot;
        }));

        const statusBadge = document.getElementById('review-status');
        statusBadge.textContent = submission.status;
        statusBadge.className = `status-badge status-${submission.status} review-status`;
        body.replaceChildren();
        const changes = currentStep ? currentStep.changes : [];
        changes.forEach((change) => {
            const row = document.createElement('tr');
            const fieldCell = document.createElement('th');
            fieldCell.scope = 'row';
            const fieldLabel = element('label', 'review-field');
            const decision = fieldDecisions[change.field]?.decision || change.decision || (
                submission.status === 'approved'
                    ? ((submission.approved_fields || []).includes(change.field) ? 'approved' : 'not-approved')
                    : submission.status === 'rejected' ? 'rejected' : null
            );
            if (submission.status === 'pending' && !decision) {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = change.field;
                checkbox.addEventListener('change', updateSelectionCount);
                fieldLabel.append(checkbox);
            }
            fieldLabel.append(element('span', '', fieldLabels[change.field] || change.field.split('.').pop().replace(/_/g, ' ')));
            fieldCell.append(fieldLabel);

            const originalValue = change.original_available === false
                ? 'Unavailable'
                : formatChangeValue(change.original);
            const originalCell = element('td', change.original_available === false ? 'value-unavailable' : '');
            if (change.original_available === false) {
                originalCell.textContent = originalValue;
                originalCell.title = 'This older request did not retain the original value.';
            } else {
                appendValue(originalCell, change.original, submission, change.field, 'original');
            }
            const requestedCell = element('td', 'requested-value');
            appendValue(requestedCell, change.requested, submission, change.field, 'requested');
            const decisionCell = document.createElement('td');
            if (decision) {
                const badge = element('span', `field-decision field-${decision}`, decision);
                if (fieldDecisions[change.field]?.reason) badge.title = fieldDecisions[change.field].reason;
                decisionCell.append(badge);
            } else {
                const label = decision === 'not-approved' ? 'Not approved' : 'Pending';
                decisionCell.append(element('span', `field-decision field-${decision || 'pending'}`, label));
            }
            const actionCell = element('td', 'field-action-cell');
            if (submission.status === 'pending' && !decision) {
                const approveOne = element('button', 'row-action row-approve', 'Approve');
                approveOne.type = 'button';
                approveOne.addEventListener('click', () => reviewSubmission('approve_fields', [change.field]));
                const rejectOne = element('button', 'row-action row-reject', 'Reject');
                rejectOne.type = 'button';
                rejectOne.addEventListener('click', () => reviewSubmission('reject_fields', [change.field]));
                actionCell.append(approveOne, rejectOne);
            } else if (decision) {
                actionCell.textContent = 'Reviewed';
            } else {
                actionCell.textContent = '—';
            }
            row.append(fieldCell, originalCell, requestedCell, decisionCell, actionCell);
            body.append(row);
        });

        empty.hidden = changes.length > 0;
        const tableWrap = document.querySelector('.review-table-wrap');
        if (tableWrap) tableWrap.hidden = changes.length === 0;
        actions.hidden = submission.status !== 'pending';
        document.getElementById('reject-request').hidden = (submission.approved_fields || []).length > 0;
        completedNotice.hidden = submission.status === 'pending';
        completedNotice.textContent = submission.status === 'approved'
            ? 'All field decisions are complete. Approved values were applied; rejected values did not change the employee record.'
            : 'This request was rejected. Original employee data was not changed.';
        const selectAll = document.getElementById('select-step-fields');
        selectAll.checked = false;
        updateSelectionCount();
    }

    function openReview(submission) {
        activeSubmission = submission;
        activeStepIndex = 0;
        const dialog = document.getElementById('review-dialog');
        const subtitle = document.getElementById('review-subtitle');
        if (!dialog || !subtitle) return;
        subtitle.textContent = `${submission.reference_number || `Submission ${submission.id}`} · Employee ID: ${submission.employee_id}`;
        const grouped = new Map();
        (Array.isArray(submission.changed_field_details) ? submission.changed_field_details : [])
            .filter((change) => approvedFields.includes(change.field))
            .forEach((change) => {
                const [title, description] = categoryForField(change.field);
                if (!grouped.has(title)) grouped.set(title, { title, description, changes: [] });
                grouped.get(title).changes.push(change);
            });
        reviewSteps = Array.from(grouped.values());
        renderReview();
        dialog.showModal();
    }

    async function reviewSubmission(decision, fields) {
        if (!activeSubmission) return;
        const body = {
            submission_id: activeSubmission.id,
            decision
        };
        if (decision === 'approve_fields') {
            body.approved_fields = fields;
        } else if (decision === 'reject_fields' || decision === 'reject') {
            const reason = window.prompt('Enter the reason for rejecting this submission:');
            if (reason === null) return;
            if (!reason.trim()) {
                showMessage('A rejection reason is required.', true);
                return;
            }
            if (decision === 'reject_fields') body.rejected_fields = fields;
            body.rejection_reason = reason.trim();
        }

        document.querySelectorAll('#review-actions button, #changed-fields-body button').forEach((button) => {
            button.disabled = true;
        });
        try {
            const result = await api('review-submission.php', {
                method: 'POST',
                body: JSON.stringify(body)
            });
            if (decision === 'approve_fields' || decision === 'reject_fields') {
                activeSubmission.field_decisions = result.field_decisions || {};
                activeSubmission.approved_fields = result.approved_fields || [];
                activeSubmission.status = result.status;
                renderReview();
                if (result.status !== 'pending') document.getElementById('review-dialog').close();
                showMessage(result.message, false);
            } else {
                document.getElementById('review-dialog').close();
                showMessage(decision === 'approve'
                    ? 'Approved changes were saved to the original employee records.'
                    : 'Submission rejected. Original employee records were not changed.', false);
            }
            await loadQueue();
        } catch (error) {
            showMessage(error.message, true);
            updateSelectionCount();
            document.querySelectorAll('#review-actions button, #changed-fields-body button').forEach((button) => {
                button.disabled = false;
            });
        }
    }

    async function loadQueue() {
        const list = document.getElementById('submission-list');
        if (!list) return;
        const requestId = ++queueRequestId;
        list.replaceChildren(element('p', 'empty-state', 'Loading employee requests…'));
        try {
            const search = document.getElementById('submission-search').value.trim();
            const status = document.getElementById('status-filter').value;
            const unit = document.getElementById('unit-filter').value;
            const query = new URLSearchParams({ search, status, unit });
            const response = await api(`review-submission.php?${query.toString()}`, { method: 'GET' });
            if (requestId !== queueRequestId) return;
            const unitFilter = document.getElementById('unit-filter');
            const selectedUnit = unitFilter.value;
            const unitOptions = [element('option', '', 'All units')];
            unitOptions[0].value = '';
            (response.units || []).forEach((value) => {
                const option = element('option', '', value);
                option.value = value;
                unitOptions.push(option);
            });
            unitFilter.replaceChildren(...unitOptions);
            unitFilter.value = (response.units || []).includes(selectedUnit) ? selectedUnit : '';

            ['pending', 'approved', 'rejected'].forEach((key) => {
                const count = document.getElementById(`${key}-count`);
                if (count) count.textContent = String(response.status_counts?.[key] ?? 0);
            });
            list.replaceChildren();
            if (!response.submissions.length) {
                list.append(element('p', 'empty-state', 'No employee requests match these filters.'));
                return;
            }
            response.submissions.forEach((submission) => list.append(buildSubmissionCard(submission)));
        } catch (error) {
            if (requestId !== queueRequestId) return;
            list.replaceChildren(element('p', 'empty-state', error.message));
        }
    }

    const searchInput = document.getElementById('submission-search');
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(loadQueue, 250);
        });
    }
    ['status-filter', 'unit-filter'].forEach((id) => {
        const filter = document.getElementById(id);
        if (filter) filter.addEventListener('change', loadQueue);
    });
    const approveSelected = document.getElementById('approve-selected');
    if (approveSelected) approveSelected.addEventListener('click', () => {
        const fields = Array.from(document.querySelectorAll('#changed-fields-body input:checked'))
            .map((input) => input.value);
        if (fields.length) reviewSubmission('approve_fields', fields);
    });
    const rejectSelected = document.getElementById('reject-selected');
    if (rejectSelected) rejectSelected.addEventListener('click', () => {
        const fields = Array.from(document.querySelectorAll('#changed-fields-body input:checked'))
            .map((input) => input.value);
        if (fields.length) reviewSubmission('reject_fields', fields);
    });
    const rejectRequest = document.getElementById('reject-request');
    if (rejectRequest) rejectRequest.addEventListener('click', () => reviewSubmission('reject', []));
    const selectStepFields = document.getElementById('select-step-fields');
    if (selectStepFields) selectStepFields.addEventListener('change', () => {
        document.querySelectorAll('#changed-fields-body input[type="checkbox"]').forEach((checkbox) => {
            checkbox.checked = selectStepFields.checked;
        });
        updateSelectionCount();
    });
    const previousStep = document.getElementById('previous-step');
    if (previousStep) previousStep.addEventListener('click', () => {
        if (activeStepIndex > 0) {
            activeStepIndex -= 1;
            renderReview();
        }
    });
    const nextStep = document.getElementById('next-step');
    if (nextStep) nextStep.addEventListener('click', () => {
        if (activeStepIndex < reviewSteps.length - 1) {
            activeStepIndex += 1;
            renderReview();
        }
    });
    const reviewDialog = document.getElementById('review-dialog');
    const closeReview = document.getElementById('close-review');
    if (closeReview && reviewDialog) closeReview.addEventListener('click', () => reviewDialog.close());

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(loginForm);
            const submit = loginForm.querySelector('button[type="submit"]');
            submit.disabled = true;
            try {
                await api('hr-login.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'login',
                        username: formData.get('username'),
                        password: formData.get('password')
                    })
                });
                window.location.reload();
            } catch (error) {
                showMessage(error.message, true);
                submit.disabled = false;
            }
        });
    }

    const refreshButton = document.getElementById('refresh-button');
    if (refreshButton) refreshButton.addEventListener('click', loadQueue);

    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', async () => {
            try {
                await api('hr-login.php', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'logout' })
                });
                window.location.reload();
            } catch (error) {
                showMessage(error.message, true);
            }
        });
    }

    if (config.signedIn) loadQueue();
})();
