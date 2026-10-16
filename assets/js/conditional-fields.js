/**
 * Conditional Fields Engine
 * Reusable conditional field logic for the wizard form
 */
window.HRMS_ConditionalFields = (function() {
    'use strict';

    const state = window.HRMS_FormState;
    const validation = window.HRMS_Validation;

    // Conditional rules definition
    const conditionalRules = [
        // Personal Basic
        {
            id: 'alumni-school',
            condition: (s) => s.personal_basic?.is_alumni === 'Yes' ||
                s.personal_basic?.is_alumni?.original_value === 'Yes',
            target: '#alumni-school-field',
            fields: ['is_alumni', 'alumni_school'],
            clearOnHide: true
        },

        // Address
        {
            id: 'permanent-address',
            condition: (s) => s.address?.same_as_permanent?.original_value === 'No',
            target: '#permanent-address-section',
            fields: [
                'same_as_permanent',
                'perm_address_line1', 'perm_address_line2', 'perm_address_line3',
                'perm_city', 'perm_pincode', 'perm_state', 'perm_country'
            ],
            clearOnHide: false
        },

        // Personal Details
        {
            id: 'spouse-name',
            condition: (s) => s.personal_details?.marital_status?.original_value === 'Married',
            target: '#spouse-field',
            fields: ['marital_status', 'spouse_name'],
            clearOnHide: true
        },

        // Next of Kin
        {
            id: 'nok-select-field',
            condition: (s) => s.next_of_kin?.name_source?.original_value === 'select',
            target: '#nok-select-field',
            fields: ['name_source', 'nok_select'],
            clearOnHide: true
        },
        {
            id: 'nok-manual-field',
            condition: (s) => s.next_of_kin?.name_source?.original_value === 'manual',
            target: '#nok-manual-field',
            fields: ['name_source', 'nok_manual_name'],
            clearOnHide: true
        },
        {
            id: 'emergency-contact-field',
            condition: (s) => s.next_of_kin?.is_emergency?.original_value === 'No',
            target: '#emergency-contact-field',
            fields: ['next_of_kin.is_emergency', 'next_of_kin.emergency_contact'],
            clearOnHide: false
        },

        // Referral - Family Working
        {
            id: 'family-working-details',
            condition: (s) => getReferralStatus(s, 'family_working') === 'Yes',
            target: '#family-working-details',
            fields: ['family_working', 'family_working_details'],
            clearOnHide: false // Keep data when hidden
        },

        // Referral - Family Studying
        {
            id: 'family-studying-details',
            condition: (s) => getReferralStatus(s, 'family_studying') === 'Yes',
            target: '#family-studying-details',
            fields: ['family_studying', 'family_studying_details'],
            clearOnHide: false
        },

        // Education - Others fields
        {
            id: 'education-others',
            // This is handled per education item in education.js
            condition: (s) => false,
            target: ''
        },

        // Teacher Documents
        {
            id: 'bed-year-field',
            condition: (s) => {
                const status = teacherStatus(s, 'bed_status');
                return teacherStatus(s, 'is_teacher') === 'Yes' &&
                    (status === 'Joined' || status === 'Completed');
            },
            target: '#bed-year-field',
            fields: ['is_teacher', 'bed_status'],
            clearOnHide: false
        },
        {
            id: 'teacher-details',
            condition: (s) => teacherStatus(s, 'is_teacher') === 'Yes',
            target: '#teacher-details-section',
            fields: [
                'is_teacher',
                'bed_status', 'bed_institution', 'bed_file_id',
                'bed_proposed_year', 'bed_graduation_year'
            ],
            clearOnHide: true
        },
        {
            id: 'bed-proposed-year',
            condition: (s) => {
                return teacherStatus(s, 'is_teacher') === 'Yes' &&
                    teacherStatus(s, 'bed_status') === 'Joined';
            },
            target: '#bed-proposed-year-group',
            fields: ['bed_proposed_year'],
            clearOnHide: true
        },
        {
            id: 'bed-graduation-year',
            condition: (s) => {
                return teacherStatus(s, 'is_teacher') === 'Yes' &&
                    teacherStatus(s, 'bed_status') === 'Completed';
            },
            target: '#bed-graduation-year-group',
            fields: ['bed_graduation_year'],
            clearOnHide: true
        },

        // Work Experience
        {
            id: 'experience-details',
            condition: (s) => !s.work_experience?.not_applicable,
            target: '#experience-details-section',
            fields: ['work_experience.entries'],
            clearOnHide: false
        }
    ];

    function getReferralStatus(state, field) {
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

    let initialized = false;
    let currentStep = null;

    function init() {
        if (initialized) return;
        initialized = true;

        // Subscribe to state changes
        state.subscribe(handleStateChange);
    }

    function handleStateChange(path, value, fullState) {
        // Check if any rules are affected by this path change
        conditionalRules.forEach(rule => {
            if (isPathRelevant(path, rule)) {
                evaluateRule(rule, fullState);
            }
        });
    }

    function isPathRelevant(path, rule) {
        return Array.isArray(rule.fields) &&
            rule.fields.some(field => path.startsWith(field) || path.includes(field));
    }

    function evaluateRule(rule, fullState) {
        const shouldShow = rule.condition(fullState);
        const element = document.querySelector(rule.target);

        if (!element) return;

        if (shouldShow) {
            showField(element, rule);
        } else {
            hideField(element, rule);
        }
    }

    function showField(element, rule) {
        if (element.classList.contains('hidden') || element.style.display === 'none') {
            element.style.display = '';
            element.classList.remove('hidden');
            // Trigger reflow for animation
            element.offsetHeight;
            element.classList.remove('conditional-field-hidden');
            element.classList.add('conditional-field-visible');

            if (rule.onShow) {
                rule.onShow(window.HRMS_FormState.getState());
            }
        }
    }

    function hideField(element, rule) {
        if (!element.classList.contains('hidden') && element.style.display !== 'none') {
            element.classList.add('conditional-field-hidden');
            element.classList.remove('conditional-field-visible');

            setTimeout(() => {
                if (element.classList.contains('conditional-field-hidden')) {
                    element.style.display = 'none';
                    element.classList.add('hidden');
                }
            }, 300);

            if (rule.clearOnHide && rule.onHide) {
                rule.onHide(window.HRMS_FormState.getState());
            }
        }
    }

    function evaluateAll(fullState) {
        conditionalRules.forEach(rule => {
            evaluateRule(rule, fullState);
        });
    }

    function evaluateForStep(stepName, fullState) {
        // Only evaluate rules relevant to current step
        const stepRules = conditionalRules.filter(rule => {
            if (!rule.target) return false;

            const target = document.querySelector(rule.target);
            if (!target) return false;

            // Check if target is in current step
            const stepElement = target.closest('.wizard-step');
            return !stepElement || stepElement.id === `step-${stepName}`;
        });

        stepRules.forEach(rule => evaluateRule(rule, fullState));
    }

    // Public API
    return {
        init,
        evaluateAll,
        evaluateForStep,
        evaluateRule,
        showField,
        hideField,
        rules: conditionalRules
    };
})();