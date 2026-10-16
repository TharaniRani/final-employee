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
            const response = await fetch(url, mergedOptions);

            if (response.status === 419) {
                // CSRF token expired, try to refresh
                await refreshCsrfToken();
                mergedOptions.headers['X-CSRF-Token'] = csrfToken;
                return fetch(url, mergedOptions);
            }

            if (response.status === 401) {
                // Session expired
                window.location.reload();
                return;
            }

            const contentType = response.headers.get('content-type');
            let data;

            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (data && typeof data === 'object' && data.success === false) {
                throw new Error(data.message || 'The request failed');
            }

            if (!response.ok) {
                throw new Error(data.message || `HTTP error! status: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    async function refreshCsrfToken() {
        try {
            const response = await fetch(config.basePath + '/backend-integration/api/csrf-token.php', {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.csrf_token) {
                csrfToken = data.csrf_token;
            }
        } catch (error) {
            console.error('Failed to refresh CSRF token:', error);
        }
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