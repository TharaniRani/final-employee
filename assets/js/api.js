/**
 * API Communication Layer
 * Handles all backend API calls for the HRMS Employee Form
 */
window.HRMS_API = (function() {
    'use strict';

    const config = window.HRMS_CONFIG;
    let csrfToken = config.csrfToken;

    function getHeaders(includeContentType = true) {
        const headers = {
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (includeContentType) {
            headers['Content-Type'] = 'application/json';
        }
        return headers;
    }

    async function request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') || endpoint.startsWith('/')
            ? endpoint
            : `${config.basePath}/${endpoint}`;
        const isFormData = options.body instanceof FormData;
        const defaultOptions = {
            headers: getHeaders(!isFormData),
            credentials: 'same-origin'
        };

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };

        if (options.body && !(options.body instanceof FormData)) {
            mergedOptions.body = JSON.stringify(options.body);
        }

        try {
            let response = await fetch(url, mergedOptions);

            if (response.status === 419) {
                await refreshCsrfToken();
                mergedOptions.headers['X-CSRF-Token'] = csrfToken;
                response = await fetch(url, mergedOptions);
            }

            const contentType = response.headers.get('content-type');
            let data;

            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (response.status === 401) {
                throw createApiError(
                    response,
                    data,
                    'Your secure session has expired. Sign in again; your saved draft should remain available in this browser.'
                );
            }

            if (!response.ok || data && typeof data === 'object' && data.success === false) {
                throw createApiError(response, data, 'The request could not be completed.');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            if (error && error.status === undefined && error.name === 'TypeError') {
                throw new Error('We could not connect to the HRMS server. Check your connection and try submitting again.');
            }
            throw error;
        }
    }

    function createApiError(response, data, fallbackMessage) {
        const serverMessage = data && typeof data === 'object' && typeof data.message === 'string'
            ? data.message
            : typeof data === 'string' && data.trim() && !data.trimStart().startsWith('<') &&
                data.trim().length <= 500
                ? data.trim()
                : '';
        const message = response.status === 401
            ? 'Your secure session has expired. Reload the page to verify again; your saved draft should remain available in this browser.'
            : response.status === 419
                ? 'Your secure form session could not be refreshed. Reload the page and try again.'
                : serverMessage || `${fallbackMessage} (HTTP ${response.status})`;
        const error = new Error(message);
        error.status = response.status;
        error.errors = data && typeof data === 'object' && Array.isArray(data.errors)
            ? data.errors
            : [];
        error.supportReference = data && typeof data === 'object'
            ? data.support_reference || ''
            : '';
        return error;
    }

    async function refreshCsrfToken() {
        const response = await fetch(config.basePath + '/backend-integration/api/csrf-token.php', {
            credentials: 'same-origin'
        });
        const contentType = response.headers.get('content-type') || '';
        let data = null;
        if (response.ok && contentType.includes('application/json')) {
            try {
                data = await response.json();
            } catch {
                throw new Error('Your secure form session could not be refreshed. Reload the page and try again.');
            }
        }
        if (!response.ok || !data || !data.csrf_token) {
            throw new Error('Your secure form session could not be refreshed. Reload the page and try again.');
        }
        csrfToken = data.csrf_token;
    }

    return {
        // Mobile Verification
        async verifyMobile(mobile) {
            return request(config.apiEndpoints.verifyMobile, {
                method: 'POST',
                body: { mobile }
            });
        },

        async verifyOtp(mobile, otp) {
            return request(config.apiEndpoints.verifyOtp, {
                method: 'POST',
                body: { mobile, otp }
            });
        },

        // Employee Data
        async getEmployee(employeeId = null) {
            const endpoint = employeeId
                ? `${config.apiEndpoints.getEmployee}?employee_id=${encodeURIComponent(employeeId)}`
                : config.apiEndpoints.getEmployee;
            return request(endpoint);
        },

        async getFamilyMemberEmployment(mssEmployeeId) {
            return request(config.apiEndpoints.getFamilyMemberEmployment, {
                method: 'POST',
                body: { mss_employee_id: mssEmployeeId }
            });
        },

        // Document Upload
        async uploadDocument(file, documentType, metadata = {}) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('document_type', documentType);
            formData.append('metadata', JSON.stringify(metadata));

            return request(config.apiEndpoints.uploadDocument, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                    // Don't set Content-Type for FormData - browser sets it with boundary
                },
                body: formData
            });
        },

        async downloadDocument(fileId) {
            const url = `${config.basePath}/backend-integration/api/download-document.php?file_id=${encodeURIComponent(fileId)}`;
            const fetchDocument = () => fetch(url, {
                headers: getHeaders(false),
                credentials: 'same-origin'
            });

            let response = await fetchDocument();
            if (response.status === 419) {
                await refreshCsrfToken();
                response = await fetchDocument();
            }

            if (response.status === 401) {
                window.location.reload();
                throw new Error('Your session has expired. Please sign in again.');
            }

            if (!response.ok) {
                const contentType = response.headers.get('content-type') || '';
                const error = contentType.includes('application/json')
                    ? await response.json()
                    : null;
                throw new Error(error?.message || `Unable to load document (HTTP ${response.status}).`);
            }

            return response.blob();
        },

        // Draft Management
        async saveDraft(formData, step = null) {
            return request(config.apiEndpoints.saveDraft, {
                method: 'POST',
                body: { form_data: formData, current_step: step }
            });
        },

        async getDraft() {
            return request(config.apiEndpoints.getDraft);
        },

        // Form Submission
        async submitForm(formData) {
            return request(config.apiEndpoints.submitForm, {
                method: 'POST',
                body: { form_data: formData }
            });
        },

        // Reference Data
        async getSchools() {
            return request(config.apiEndpoints.getSchools);
        },

        async getUnits() {
            return request(config.apiEndpoints.getUnits);
        },

        async getEmploymentOptions() {
            return request(config.apiEndpoints.getEmploymentOptions);
        },

        // Utility
        setCsrfToken(token) {
            csrfToken = token;
        },

        getCsrfToken() {
            return csrfToken;
        }
    };
})();