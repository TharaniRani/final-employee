(function() {
    'use strict';

    const config = window.HR_REVIEW_CONFIG;
    const message = document.getElementById('page-message');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    let csrfToken = csrfMeta ? csrfMeta.content : '';
    let queueRequestId = 0;
    let currentPage = 1;

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
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function formatSubmissionDate(value) {
        if (!value) return '—';
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return match ? `${match[3]}-${match[2]}-${match[1]}` : String(value);
    }

    function buildSubmissionRow(submission, index, pageSize) {
        const row = document.createElement('tr');
        const values = [
            String((currentPage - 1) * pageSize + index + 1),
            submission.employee_number || String(submission.employee_id),
            submission.employee_name || `Employee ${submission.employee_id}`,
            submission.email || '—',
            submission.mobile || '—',
            submission.unit || '—',
            submission.designation || '—',
            formatSubmissionDate(submission.submitted_at)
        ];
        values.forEach((value, cellIndex) => {
            row.append(element(
                'td',
                cellIndex === 1 ? 'employee-id-cell' : (cellIndex === 2 ? 'employee-name-cell' : ''),
                value
            ));
        });

        const statusCell = document.createElement('td');
        statusCell.append(element('span', `submission-status status-${submission.status}`, submission.status));
        row.append(statusCell);

        const actionCell = document.createElement('td');
        const viewButton = element('button', 'submission-view', 'View');
        viewButton.type = 'button';
        viewButton.setAttribute(
            'aria-label',
            `View submission for ${submission.employee_name || `employee ${submission.employee_id}`}`
        );
        viewButton.addEventListener('click', () => {
            window.location.href = `${config.basePath}/admin/employees/view/${submission.id}`;
        });
        actionCell.append(viewButton);
        row.append(actionCell);
        return row;
    }

    function renderPageNumbers(current, total) {
        const container = document.getElementById('page-numbers');
        if (!container) return;

        const pages = new Set([1, total, current - 1, current, current + 1]);
        const visiblePages = Array.from(pages)
            .filter((page) => page >= 1 && page <= total)
            .sort((left, right) => left - right);
        const controls = [];
        let previous = 0;

        visiblePages.forEach((page) => {
            if (page - previous > 1) {
                controls.push(element('span', 'page-ellipsis', '…'));
            }
            const button = element('button', 'page-button page-number', String(page));
            button.type = 'button';
            button.setAttribute('aria-label', `Page ${page}`);
            if (page === current) {
                button.classList.add('is-current');
                button.setAttribute('aria-current', 'page');
            } else {
                button.addEventListener('click', () => {
                    currentPage = page;
                    loadQueue(false);
                });
            }
            controls.push(button);
            previous = page;
        });

        container.replaceChildren(...controls);
    }

    async function loadQueue(resetPage) {
        const tableBody = document.getElementById('submission-table-body');
        if (!tableBody) return;
        if (resetPage) currentPage = 1;
        const requestId = ++queueRequestId;
        tableBody.replaceChildren();
        const empty = document.getElementById('submission-empty');
        empty.hidden = true;
        document.getElementById('result-count').textContent = 'Loading submissions…';

        try {
            const search = document.getElementById('submission-search').value.trim();
            const status = document.getElementById('status-filter').value;
            const unitFilter = document.getElementById('unit-filter');
            const unit = unitFilter ? unitFilter.value : '';
            const query = new URLSearchParams({ search, status, unit, page: String(currentPage) });
            const response = await api(`review-submission.php?${query.toString()}`, { method: 'GET' });
            if (requestId !== queueRequestId) return;

            if (unitFilter && unitFilter.tagName === 'SELECT') {
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
            }

            ['pending', 'approved'].forEach((key) => {
                const count = document.getElementById(`${key}-count`);
                if (count) count.textContent = String(response.status_counts?.[key] ?? 0);
            });
            document.getElementById('total-count').textContent = String(response.total_count ?? 0);
            if (!response.submissions.length) {
                empty.hidden = false;
            } else {
                response.submissions.forEach((submission, index) => {
                    tableBody.append(buildSubmissionRow(submission, index, response.pagination.page_size));
                });
            }

            const pagination = response.pagination;
            const firstResult = pagination.total_results === 0
                ? 0
                : ((pagination.page - 1) * pagination.page_size) + 1;
            const lastResult = Math.min(pagination.page * pagination.page_size, pagination.total_results);
            const resultSummary = `Showing ${firstResult}–${lastResult} of ${pagination.total_results} submissions`;
            document.getElementById('result-count').textContent = resultSummary;
            document.getElementById('pagination-label').textContent = resultSummary;
            renderPageNumbers(pagination.page, pagination.total_pages);
            document.getElementById('previous-page').disabled = pagination.page <= 1;
            document.getElementById('next-page').disabled = pagination.page >= pagination.total_pages;
        } catch (error) {
            if (requestId !== queueRequestId) return;
            empty.hidden = false;
            empty.textContent = error.message;
            document.getElementById('result-count').textContent = 'Unable to load submissions';
            document.getElementById('pagination-label').textContent = 'Unable to load submissions';
        }
    }

    const searchInput = document.getElementById('submission-search');
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => loadQueue(true), 250);
        });
    }

    ['status-filter', 'unit-filter'].forEach((id) => {
        const filter = document.getElementById(id);
        if (filter) filter.addEventListener('change', () => loadQueue(true));
    });

    document.querySelectorAll('.summary-card').forEach((card) => {
        card.addEventListener('click', () => {
            document.getElementById('status-filter').value = card.dataset.status;
            loadQueue(true);
        });
    });

    const clearFilters = document.getElementById('clear-filters');
    if (clearFilters) clearFilters.addEventListener('click', () => {
        document.getElementById('submission-search').value = '';
        document.getElementById('status-filter').value = 'all';
        const unitFilter = document.getElementById('unit-filter');
        if (unitFilter && unitFilter.tagName === 'SELECT') unitFilter.value = '';
        loadQueue(true);
    });

    const previousPage = document.getElementById('previous-page');
    if (previousPage) previousPage.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage -= 1;
            loadQueue(false);
        }
    });

    const nextPage = document.getElementById('next-page');
    if (nextPage) nextPage.addEventListener('click', () => {
        currentPage += 1;
        loadQueue(false);
    });

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(loginForm);
            const submit = loginForm.querySelector('button[type="submit"]');
            submit.disabled = true;
            submit.classList.add('is-loading');
            submit.querySelector('.login-button-label').textContent = 'Signing in…';
            try {
                await api('hr-login.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'login',
                        email: formData.get('email'),
                        password: formData.get('password')
                    })
                });
                window.location.reload();
            } catch (error) {
                showMessage(error.message, true);
                submit.disabled = false;
                submit.classList.remove('is-loading');
                submit.querySelector('.login-button-label').textContent = 'Sign in';
            }
        });
    }

    const refreshButton = document.getElementById('refresh-button');
    if (refreshButton) refreshButton.addEventListener('click', () => loadQueue(false));

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

    const passwordToggle = document.getElementById('toggle-password');
    if (passwordToggle) passwordToggle.addEventListener('click', () => {
        const passwordInput = document.getElementById('password');
        const showPassword = passwordInput.type === 'password';
        passwordInput.type = showPassword ? 'text' : 'password';
        passwordToggle.textContent = showPassword ? 'Hide' : 'Show';
        passwordToggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
    });

    if (config.signedIn) loadQueue(false);
})();
