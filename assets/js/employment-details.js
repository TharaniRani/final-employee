/**
 * Employment Details Module
 * Loads organizational choices and synchronizes employment fields with form state.
 */
window.HRMS_EmploymentDetails = (function() {
    'use strict';

    const api = window.HRMS_API;
    const state = window.HRMS_FormState;
    const fields = [
        { key: 'unit', label: 'Unit', type: 'select' },
        { key: 'campus', label: 'Campus', type: 'select' },
        { key: 'designation', label: 'Designation', type: 'text' },
        { key: 'employee_type', label: 'Employee Type (Grade)', type: 'select' },
        { key: 'job_type', label: 'Job Type', type: 'select' },
        { key: 'reporting_to', label: 'Reporting To', type: 'select' },
        { key: 'date_of_joining', label: 'Date of Joining', type: 'date' }
    ];
    let options = {};
    let initialized = false;

    function init() {
        if (initialized || !document.getElementById('step-employment-details')) return;
        initialized = true;
        state.subscribe((path) => {
            if (path === '*' || path.startsWith('employment_details.')) {
                populateSelects();
            }
        });
        loadOptions();
    }

    async function loadOptions() {
        const errorContainer = document.getElementById('employment-options-error');
        try {
            const [employmentResult, unitResult] = await Promise.all([
                api.getEmploymentOptions(),
                api.getUnits()
            ]);
            const employmentOptions = employmentResult?.options;
            const requiredFields = ['campus', 'employee_type', 'job_type', 'reporting_to'];
            if (!unitResult?.success || !Array.isArray(unitResult.units) || unitResult.units.length === 0 ||
                !employmentResult?.success || !employmentOptions ||
                !requiredFields.every(field => Array.isArray(employmentOptions[field]) && employmentOptions[field].length > 0)) {
                throw new Error('Employment dropdown options were not returned by the server.');
            }
            options = {
                ...employmentOptions,
                unit: unitResult.units
            };
            populateSelects();
            if (errorContainer) errorContainer.hidden = true;
        } catch (error) {
            console.error('Unable to load employment dropdown options:', error);
            if (errorContainer) {
                errorContainer.textContent = error.message || 'Unable to load employment options. Please refresh and try again.';
                errorContainer.hidden = false;
            }
        }
    }

    function populateSelects() {
        fields.forEach(field => {
            const fieldId = `employment-${field.key.replace(/_/g, '-')}`;
            const fieldState = state.get(`employment_details.${field.key}`) || {};
            const currentValue = fieldState.original_value || '';
            const change = fieldState.change || '';
            const currentInput = document.getElementById(fieldId);
            const choiceGroup = document.getElementById(`${fieldId}-change`);
            const replacement = document.getElementById(`${fieldId}-replacement`);
            const updatedInput = document.getElementById(`${fieldId}-updated`);

            if (currentInput) currentInput.value = currentValue;
            if (choiceGroup) {
                choiceGroup.querySelectorAll('input[type="radio"]').forEach(input => {
                    input.checked = input.value === change;
                });
            }
            if (replacement) replacement.hidden = change !== 'Yes';
            if (!updatedInput) return;

            if (field.type === 'select') {
                const values = Array.isArray(options[field.key]) ? [...options[field.key]] : [];
                if (currentValue && !values.includes(currentValue)) values.push(currentValue);
                const placeholder = new Option(`Select ${field.label.toLowerCase()}`, '');
                updatedInput.replaceChildren(placeholder);
                values.forEach(value => updatedInput.add(new Option(value, value)));
            }
            updatedInput.value = fieldState.updated_value || '';
            updatedInput.required = change === 'Yes';
        });
    }

    return { init };
})();
