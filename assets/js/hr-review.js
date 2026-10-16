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
        'employee_employment.probation_period', 'employee_employment.probation_start_date',
        'employee_employment.probation_end_date', 'employee_referrals.all',
        'educational_qualifications.all', 'employee_previous_experience.all'
    ];

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

    function buildSubmissionCard(submission) {
        const card = element('article', 'submission-card');
        const title = element('h3', '', `Submission ${submission.reference_number || submission.id}`);
        const meta = element('div', 'submission-meta');
        meta.append(
            element('span', '', `Employee ID: ${submission.employee_id}`),
            element('span', '', `Mobile: ${submission.mobile}`),
            element('span', '', `Submitted: ${submission.submitted_at}`)
        );

        const details = element('details');
        const summary = element('summary', '', 'Inspect submitted values');
        const json = element('pre', 'submission-json', JSON.stringify(submission.form_data, null, 2));
        details.append(summary, json);

        const fieldTitle = element('h4', '', 'Choose fields to approve');
        const fieldList = element('div', 'approval-fields');
        approvedFields.forEach((field) => {
            const label = element('label');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = field;
            label.append(checkbox, document.createTextNode(field));
            fieldList.append(label);
        });

        const actions = element('div', 'card-actions');
        const approve = element('button', 'button', 'Approve selected changes');
        approve.type = 'button';
        const reject = element('button', 'button reject-button', 'Reject submission');
        reject.type = 'button';
        approve.addEventListener('click', async () => {
            const fields = Array.from(fieldList.querySelectorAll('input:checked')).map((input) => input.value);
            if (!fields.length) {
                showMessage('Select at least one field or section to approve.', true);
                return;
            }
            approve.disabled = true;
            try {
                await api('review-submission.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        submission_id: submission.id,
                        decision: 'approve',
                        approved_fields: fields
                    })
                });
                showMessage('Approved changes were saved to the original employee records.', false);
                await loadQueue();
            } catch (error) {
                showMessage(error.message, true);
                approve.disabled = false;
            }
        });
        reject.addEventListener('click', async () => {
            const reason = window.prompt('Enter the reason for rejecting this submission:');
            if (reason === null) return;
            if (!reason.trim()) {
                showMessage('A rejection reason is required.', true);
                return;
            }
            reject.disabled = true;
            try {
                await api('review-submission.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        submission_id: submission.id,
                        decision: 'reject',
                        rejection_reason: reason.trim()
                    })
                });
                showMessage('Submission rejected. Original employee records were not changed.', false);
                await loadQueue();
            } catch (error) {
                showMessage(error.message, true);
                reject.disabled = false;
            }
        });
        actions.append(approve, reject);
        card.append(title, meta, details, fieldTitle, fieldList, actions);
        return card;
    }

    async function loadQueue() {
        const list = document.getElementById('submission-list');
        if (!list) return;
        list.replaceChildren(element('p', 'empty-state', 'Loading pending submissions…'));
        try {
            const response = await api('review-submission.php?limit=100', { method: 'GET' });
            list.replaceChildren();
            if (!response.submissions.length) {
                list.append(element('p', 'empty-state', 'There are no pending submissions.'));
                return;
            }
            response.submissions.forEach((submission) => list.append(buildSubmissionCard(submission)));
        } catch (error) {
            list.replaceChildren(element('p', 'empty-state', error.message));
        }
    }

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
