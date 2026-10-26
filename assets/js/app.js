/**
 * Main Application Entry Point
 * Initializes all modules and handles global events
 */
(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeApp);
    } else {
        initializeApp();
    }

    function initializeApp() {
        console.log('HRMS Employee Form - Initializing...');

        // Initialize all modules in order
        try {
            populateStaticOptions();

            // 1. Form State (must be first)
            if (window.HRMS_FormState) {
                window.HRMS_FormState.subscribe(() => {});
                window.HRMS_FormState.bindFormInputs();
                console.log('✓ Form State initialized');
            }

            // 2. Validation
            if (window.HRMS_Validation) {
                console.log('✓ Validation initialized');
            }

            // 3. Conditional Fields
            if (window.HRMS_ConditionalFields) {
                window.HRMS_ConditionalFields.init();
                console.log('✓ Conditional Fields initialized');
            }

            // 4. File Upload
            if (window.HRMS_FileUpload) {
                window.HRMS_FileUpload.initAll();
                console.log('✓ File Upload initialized');
            }

            // 5. Education
            if (window.HRMS_Education) {
                window.HRMS_Education.init();
                console.log('✓ Education module initialized');
            }

            // 6. Work Experience
            if (window.HRMS_WorkExperience) {
                window.HRMS_WorkExperience.init();
                console.log('✓ Work Experience module initialized');
            }

            // 7. Referral
            if (window.HRMS_Referral) {
                window.HRMS_Referral.init();
                console.log('✓ Referral module initialized');
            }

            // 8. Other Documents
            if (window.HRMS_OtherDocuments) {
                window.HRMS_OtherDocuments.init();
                console.log('✓ Other Documents module initialized');
            }

            if (window.HRMS_EmploymentDetails) {
                window.HRMS_EmploymentDetails.init();
                console.log('Employment Details initialized');
            }

            // 9. Review
            if (window.HRMS_Review) {
                window.HRMS_Review.init();
                console.log('✓ Review module initialized');
            }

            // 10. Wizard (must be last as it depends on others)
            if (window.HRMS_Wizard) {
                window.HRMS_Wizard.init();
                console.log('✓ Wizard initialized');
            }

            // Setup global event listeners
            setupGlobalListeners();

            // Handle mobile verification form if present
            handleMobileVerification();

            console.log('HRMS Employee Form - Initialization complete');

        } catch (error) {
            console.error('Initialization error:', error);
        }
    }

    function populateStaticOptions() {
        document.querySelectorAll('select').forEach(select => {
            const values = select.name.endsWith('_state') || select.name.endsWith('_state_corrected')
                ? window.HRMS_CONFIG.states
                : select.name.endsWith('_country')
                    ? window.HRMS_CONFIG.countries
                : select.name === 'alumni_school'
                    ? window.HRMS_CONFIG.schools
                    : null;
            if (!values) return;

            const selectedValue = select.value;
            const existingValues = new Set(Array.from(select.options, option => option.value));
            values.forEach(value => {
                if (existingValues.has(value)) return;
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                select.appendChild(option);
            });
            select.value = selectedValue || (select.name.endsWith('_country') ? 'India' : '');
        });
    }

    // Export for pincode autocomplete to use
    window.HRMS_App = {
        populateStaticOptions
    };

    function setupGlobalListeners() {
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Alt + S: Save draft
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                if (window.HRMS_Wizard) {
                    window.HRMS_Wizard.saveDraft();
                }
            }

            // Alt + Right: Next step
            if (e.altKey && e.key === 'ArrowRight') {
                e.preventDefault();
                if (window.HRMS_Wizard) {
                    window.HRMS_Wizard.validateAndProceed();
                }
            }

            // Alt + Left: Previous step
            if (e.altKey && e.key === 'ArrowLeft') {
                e.preventDefault();
                if (window.HRMS_Wizard) {
                    window.HRMS_Wizard.goToStep(window.HRMS_Wizard.getCurrentStepIndex() - 1);
                }
            }
        });

        const refreshFieldValidation = (target) => {
            if (!(target instanceof HTMLElement) || !target.closest('.wizard-step')) return;
            const validationResult = window.HRMS_Validation?.validateControl(target);
            if (validationResult !== null && validationResult !== undefined) {
                window.HRMS_Wizard?.refreshValidationFeedback();
            }
        };

        // Revalidate previously invalid fields as their values change.
        document.addEventListener('input', (e) => {
            const target = e.target;
            if (target instanceof HTMLElement &&
                target.matches('.form-input, .form-select, .form-textarea') &&
                (target.classList.contains('error') || target.getAttribute('aria-invalid') === 'true')) {
                Promise.resolve().then(() => refreshFieldValidation(target));
            }
        }, true);

        // Validate every supported field when it loses focus.
        document.addEventListener('blur', (e) => {
            refreshFieldValidation(e.target);
        }, true);

        document.addEventListener('change', (e) => {
            refreshFieldValidation(e.target);
        }, true);

        // Handle modal backdrop clicks
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                const modal = e.target.closest('.modal-overlay');
                if (modal) {
                    modal.hidden = true;
                    modal.classList.remove('active');
                }
            }
        });

        // Handle escape key for modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.hidden = true;
                    modal.classList.remove('active');
                });
            }
        });

    }

    function handleMobileVerification() {
        // This is handled in the mobile-verification.php component inline script
        // But we can add any global handlers here if needed
    }

    // Utility function to get form data for debugging
    window.HRMS_Debug = {
        getState: () => window.HRMS_FormState?.getState(),
        getCurrentStep: () => window.HRMS_Wizard?.getCurrentStep(),
        validateAll: () => window.HRMS_Validation?.validateAll(window.HRMS_FormState?.getState()),
        saveDraft: () => window.HRMS_Wizard?.saveDraft(),
        submitForm: () => window.HRMS_Wizard?.submitForm()
    };

    // Expose for debugging
    console.log('HRMS Debug tools available at window.HRMS_Debug');
})();