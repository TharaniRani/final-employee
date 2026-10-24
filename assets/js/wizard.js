/**
 * Wizard Navigation Module
 * Handles step navigation, progress tracking, and wizard state
 */
window.HRMS_Wizard = (function() {
    'use strict';

    const config = window.HRMS_CONFIG;
    const state = window.HRMS_FormState;
    const validation = window.HRMS_Validation;
    const conditionalFields = window.HRMS_ConditionalFields;
    const api = window.HRMS_API;

    const stepKeys = config.stepKeys;
    const steps = config.steps;
    let currentStepIndex = stepKeys.indexOf(config.currentStep);
    let currentStep = config.currentStep;
    let isInitialized = false;

    // DOM elements
    const wizard = document.getElementById('form-wizard');
    const stepperItems = document.querySelectorAll('.stepper-item[data-step]');
    const prevButtons = document.querySelectorAll('[id$="-prev"]');
    const nextButtons = document.querySelectorAll('[id$="-next"]');
    const saveDraftBtn = document.getElementById('save-draft-btn');
    const draftStorageKey = config.verifiedMobile
        ? `hrms_onboarding_draft_${String(config.verifiedMobile).replace(/\D/g, '')}`
        : null;
    let isRestoringDraft = false;
    let employeeDataReady = false;
    let localDraftSaveFailed = false;
    let employeeEducationRows = [];
    let approvedEmployeeDocumentIds = new Set();

    function init() {
        if (isInitialized || !wizard) return;
        isInitialized = true;

        // Initialize conditional fields
        conditionalFields.init();

        // Setup navigation buttons
        setupNavigationButtons();

        // Setup stepper click navigation
        setupStepperNavigation();

        state.subscribe(() => {
            if (!isRestoringDraft && !['submitted', 'pending', 'approved'].includes(state.get('submission.status'))) {
                persistLocalDraft();
            }
        });

        isRestoringDraft = true;
        if (config.verifiedMobile) {
            state.initFromEmployee({ mobile: config.verifiedMobile });
            state.markClean();
            const mobileInput = document.getElementById('mobile-number-display');
            if (mobileInput) {
                mobileInput.value = config.verifiedMobile;
            }
        }

        isRestoringDraft = false;

        // Show current step
        showStep(currentStepIndex);

        // Evaluate conditional fields for current step
        conditionalFields.evaluateForStep(currentStep, state.getState());

        loadEmployeeData();

        console.log('Wizard initialized at step:', currentStep);
    }

    async function loadEmployeeData() {
        if (!config.verifiedMobile) return;

        let restored = false;
        let employeeDataLoaded = false;
        try {
            const result = await api.getEmployee();
            if (!result.success || !result.employee) {
                throw new Error(result.message || 'Employee data was not returned');
            }

            const employee = result.employee;
            employee.documents = Array.isArray(result.documents) ? result.documents : [];
            approvedEmployeeDocumentIds = new Set(
                employee.documents.map(document => String(document.id))
            );
            employeeEducationRows = Array.isArray(employee.education) ? employee.education : [];
            employee.legacy_documents = Array.isArray(result.legacy_documents) ? result.legacy_documents : [];
            employee.existing_documents = result.existing_documents || {};
            window.HRMS_FileUpload.setDocumentMetadata(employee.documents);
            isRestoringDraft = true;
            state.initFromEmployee(employee);
            state.loadDraft(state.getState());
            populateEmployeeFields(employee);
            syncPermanentLocationFromCurrent();
            displayExistingIdentityDocuments(employee.existing_documents);
            displayLegacyAdditionalDetails(employee);
            employeeDataLoaded = true;
            isRestoringDraft = false;
        } catch (error) {
            isRestoringDraft = false;
            console.error('Unable to load employee data:', error);
            showToast(
                'error',
                'Employee Details Not Loaded',
                'We could not retrieve your current HRMS details. Refresh the page or contact HR before submitting.'
            );
        } finally {
            isRestoringDraft = true;
            restored = loadDraft(false);
            if (restored) restoreExistingEducationDocuments();
            isRestoringDraft = false;
            if (!restored) {
                syncNextOfKinControls();
                conditionalFields.evaluateForStep(currentStep, state.getState());
            }
            employeeDataReady = employeeDataLoaded;
            if (employeeDataReady) persistLocalDraft();
            state.markClean();
            window.HRMS_Review?.updateReview();
        }
    }

    function populateEmployeeFields(employee) {
        const fieldMap = {
            full_name: ['full-name', 'aadhaar-name'],
            email: ['email'],
            mobile: ['mobile-number-display'],
            current_address: ['curr-address-line1'],
            current_address_line2: ['curr-address-line2'],
            current_address_line3: ['curr-address-line3'],
            current_city: ['curr_city'],
            current_pincode: ['curr_pincode'],
            current_state: ['curr_state'],
            current_country: ['curr_country'],
            permanent_address: ['perm-address-line1'],
            permanent_address_line2: ['perm-address-line2'],
            permanent_address_line3: ['perm-address-line3'],
            permanent_city: ['perm_city'],
            permanent_pincode: ['perm_pincode'],
            permanent_state: ['perm_state'],
            permanent_country: ['perm_country'],
            same_as_permanent: ['same_as_permanent'],
            date_of_birth: ['date-of-birth'],
            gender: ['gender'],
            marital_status: ['marital-status'],
            spouse_name: ['spouse-name'],
            father_name: ['father-name'],
            mother_name: ['mother-name'],
            blood_group: ['blood-group'],
            aadhaar_number: ['aadhaar-number'],
            aadhaar_name: ['aadhaar-name'],
            pan_number: ['pan-number'],
            aadhaar_pan_linked: ['aadhaar_pan_linked'],
            is_alumni: ['is_alumni'],
            alumni_school: ['alumni-school'],
            nok_name_source: ['nok_name_source'],
            nok_selected_name: ['nok_select'],
            nok_manual_name: ['nok_manual_name'],
            nok_contact: ['nok_contact'],
            nok_is_emergency: ['nok_is_emergency'],
            nok_emergency_contact: ['nok_emergency_contact'],
            nok_address: ['nok_address_line1'],
            nok_address_line2: ['nok_address_line2'],
            nok_address_line3: ['nok_address_line3'],
            nok_city: ['nok_city'],
            nok_pincode: ['nok_pincode'],
            nok_state: ['nok_state'],
            nok_country: ['nok_country'],
            referred_by: ['referred_by_name'],
            // referral_source: ['referral_source'],
            family_working: ['family_working'],
            family_studying: ['family_studying'],
            is_teacher: ['is_teacher'],
            bed_status: ['bed-status'],
            bed_institution: ['bed-institution'],
            bed_proposed_year: ['bed-proposed-year'],
            bed_graduation_year: ['bed-graduation-year'],
            employment_unit: ['employment_unit'],
            employment_campus: ['employment_campus'],
            employment_designation: ['employment_designation'],
            employment_employee_type: ['employment_employee_type'],
            employment_job_type: ['employment_job_type'],
            employment_reporting_to: ['employment_reporting_to'],
            employment_date_of_joining: ['employment_date_of_joining']
        };

        Object.entries(fieldMap).forEach(([employeeField, controlNames]) => {
            const value = employee[employeeField];
            if (value === null || value === undefined || value === '') return;

            controlNames.forEach(controlName => {
                const controls = document.querySelectorAll(
                    `[name="${controlName}"], #${controlName}`
                );

                controls.forEach(control => {
                    if (control.type === 'radio' || control.type === 'checkbox') {
                        control.checked = control.value === String(value);
                    } else if (control.type !== 'file') {
                        if (control instanceof HTMLSelectElement && value !== '' &&
                            !Array.from(control.options).some(option => option.value === String(value))) {
                            control.add(new Option(String(value), String(value)));
                        }
                        control.value = String(value);
                    }
                });
            });
        });

        populateNextOfKinOptions(employee);
    }

    function displayExistingIdentityDocuments(existingDocuments) {
        ['aadhaar', 'pan', 'photo', 'resume', 'signature'].forEach(type => {
            const document = existingDocuments?.[type];
            if (!document?.exists || !document.filename) return;
            const url = `${config.basePath}/backend-integration/api/view-existing-document.php?type=${encodeURIComponent(type)}`;
            const fieldId = type === 'resume' ? 'resume-upload' : `${type}-upload`;
            window.HRMS_FileUpload.setExistingDocument(fieldId, document.filename, url);
        });
    }

    function displayLegacyAdditionalDetails(employee) {
        const details = [
            ['Postgraduate degree status', employee.legacy_pg_degree_status],
            ['Postgraduate institution', employee.legacy_pg_degree_institution]
        ].filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '');

        const container = document.getElementById('existing-legacy-details');
        if (!container || !details.length) return;

        const heading = document.createElement('strong');
        heading.textContent = 'Additional legacy details (read-only; no matching editable field in this form)';
        const list = document.createElement('dl');
        details.forEach(([label, value]) => {
            const term = document.createElement('dt');
            term.textContent = label;
            const description = document.createElement('dd');
            description.textContent = String(value);
            list.append(term, description);
        });
        container.replaceChildren(heading, list);
        container.hidden = false;
    }

    function populateNextOfKinOptions(employee) {
        const select = document.getElementById('nok-select');
        if (!select) return;

        const options = [
            ['spouse_name', 'Spouse'],
            ['father_name', 'Father'],
            ['mother_name', 'Mother'],
            ['nok_name', 'Existing Next of Kin']
        ];
        const existingNames = new Set();
        select.replaceChildren(new Option('Select from family members', ''));

        options.forEach(([field, relation]) => {
            const name = String(employee[field] || '').trim();
            const normalizedName = name.toLocaleLowerCase();
            if (!name || existingNames.has(normalizedName)) return;

            existingNames.add(normalizedName);
            const option = new Option(`${name} (${relation})`, name);
            select.add(option);
        });
    }

    function syncNextOfKinControls() {
        const nextOfKin = state.get('next_of_kin');
        if (!nextOfKin) return;

        const source = nextOfKin.name_source?.original_value || '';
        document.querySelectorAll('input[name="nok_name_source"]').forEach((radio) => {
            radio.checked = radio.value === source;
        });

        const emergencyStatus = nextOfKin.is_emergency?.original_value || '';
        document.querySelectorAll('input[name="nok_is_emergency"]').forEach((radio) => {
            radio.checked = radio.value === emergencyStatus;
        });

        const select = document.getElementById('nok-select');
        if (select) {
            select.value = nextOfKin.selected_name?.original_value || '';
        }

        const manualName = document.getElementById('nok-manual-name');
        if (manualName) {
            manualName.value = nextOfKin.manual_name?.original_value || '';
        }

        const emergencyContact = document.getElementById('emergency-contact');
        if (emergencyContact) {
            emergencyContact.value = nextOfKin.emergency_contact?.original_value || '';
        }
    }

    function setupNavigationButtons() {
        // Previous buttons
        prevButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                goToStep(currentStepIndex - 1);
            });
        });

        // Next/Continue buttons
        nextButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                validateAndProceed();
            });
        });

        // Save draft button (if exists)
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', saveDraft);
        }
    }

    function setupStepperNavigation() {
        stepperItems.forEach(item => {
            item.addEventListener('click', () => {
                const targetStep = item.dataset.step;
                const targetIndex = stepKeys.indexOf(targetStep);

                // Only allow navigation to completed steps or current step
                if (targetIndex <= currentStepIndex || isStepCompleted(targetStep)) {
                    goToStep(targetIndex);
                }
            });

            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    item.click();
                }
            });
        });
    }

    function loadDraft(showNotification = true) {
        if (!draftStorageKey) return false;

        try {
            const saved = localStorage.getItem(draftStorageKey);
            if (!saved) return false;

            const draft = JSON.parse(saved);
            const draftStepIndex = stepKeys.indexOf(draft.current_step);
            if (!draft.form_data || draftStepIndex < 0) {
                console.warn('Ignoring locally saved draft with invalid form data or step.');
                return false;
            }

            if (String(draft.form_data.verified_mobile || '').replace(/\D/g, '') !==
                String(config.verifiedMobile).replace(/\D/g, '')) {
                console.warn('Ignoring locally saved draft for a different verified mobile number.');
                return false;
            }

            clearUnapprovedDocumentReferences(draft.form_data);
            const savedExperience = draft.form_data.work_experience;
            const employeeExperience = state.get('work_experience.entries') || [];
            if (draft.version !== 2 &&
                savedExperience?.not_applicable &&
                (!Array.isArray(savedExperience.entries) || savedExperience.entries.length === 0) &&
                employeeExperience.length === 0) {
                savedExperience.not_applicable = false;
            }

            currentStepIndex = draftStepIndex;
            currentStep = stepKeys[currentStepIndex];
            state.loadDraft(mergeDraftWithCurrent(draft.form_data, state.toJSON()));
            syncPermanentLocationFromCurrent();
            populateFormFromState();
            syncNextOfKinControls();
            showStep(currentStepIndex);
            conditionalFields.evaluateForStep(currentStep, state.getState());
            if (showNotification) {
                showToast('info', 'Draft Restored', 'Your saved progress has been restored.');
            }
            return true;
        } catch (error) {
            console.error('Unable to restore locally saved draft:', error);
            showToast('error', 'Draft Restore Failed', 'Saved progress could not be restored in this browser.');
            return false;
        }
    }

    function clearUnapprovedDocumentReferences(value) {
        if (!value || typeof value !== 'object') return;

        Object.entries(value).forEach(([key, child]) => {
            if (/(?:^|_)file_id$/.test(key) || key === 'file_id') {
                if (typeof child === 'number' ||
                    typeof child === 'string' && /^\d+$/.test(child)) {
                    if (!approvedEmployeeDocumentIds.has(String(child))) {
                        value[key] = null;
                    }
                }
                return;
            }

            if (child && typeof child === 'object') {
                clearUnapprovedDocumentReferences(child);
            }
        });
    }

    function restoreExistingEducationDocuments() {
        const currentEducation = state.get('education') || [];
        if (!currentEducation.length) return;

        const mergedEducation = currentEducation.map(entry => {
            const rowId = Number(entry.existing_row_id) ||
                Number(String(entry.id || '').match(/^db-education-(\d+)$/)?.[1]);
            const existing = employeeEducationRows.find(row => Number(row.id) === rowId);
            if (!existing) {
                return {
                    ...entry,
                    existing_certificate_available: false,
                    legacy_certificate_name: ''
                };
            }

            return {
                ...entry,
                existing_row_id: Number(existing.id),
                existing_certificate_available: !!existing.has_certificate,
                legacy_certificate_name: existing.has_certificate ? existing.certificate_name || '' : ''
            };
        });

        state.set('education', mergedEducation);
    }

    function syncPermanentLocationFromCurrent() {
        if (state.get('address.same_as_permanent.original_value') !== 'Yes') return;

        ['address_line1', 'address_line2', 'address_line3', 'city', 'pincode', 'state', 'country'].forEach(field => {
            const currentValue = state.get(`address.current.${field}`);
            const currentText = currentValue?.verification === 'wrong' &&
                currentValue.corrected_value !== null &&
                currentValue.corrected_value !== undefined &&
                currentValue.corrected_value !== ''
                ? currentValue.corrected_value
                : currentValue?.original_value;
            const existingPermanentValue = state.get(`address.permanent.${field}`);
            const value = currentText !== null && currentText !== undefined && String(currentText).trim() !== ''
                ? currentValue
                : existingPermanentValue;
            state.set(`address.permanent.${field}`, value);

            const control = document.querySelector(`[name="perm_${field}"]`);
            if (control) {
                control.value = value?.original_value || '';
            }

            const correctedControl = document.querySelector(`[name="perm_${field}_corrected"]`);
            if (correctedControl) {
                correctedControl.value = value?.corrected_value || '';
            }

            document.querySelectorAll(`[name="perm_${field}_verification"]`).forEach(radio => {
                radio.checked = radio.value === value?.verification;
            });
        });
    }

    function mergeDraftWithCurrent(draftValue, currentValue) {
        if (draftValue === undefined) return currentValue;
        if (draftValue === null || draftValue === '') {
            return currentValue !== undefined && currentValue !== null && currentValue !== ''
                ? currentValue
                : draftValue;
        }
        if (Array.isArray(draftValue)) {
            return draftValue.length === 0 && Array.isArray(currentValue) && currentValue.length > 0
                ? currentValue
                : draftValue;
        }
        if (typeof draftValue === 'object') {
            const merged = { ...(currentValue && typeof currentValue === 'object' && !Array.isArray(currentValue) ? currentValue : {}) };
            Object.entries(draftValue).forEach(([key, value]) => {
                if (key === 'existing_document_available' ||
                    key === 'existing_document_name' ||
                    key === 'existing_certificate_available' ||
                    key === 'legacy_certificate_name') {
                    return;
                }
                merged[key] = mergeDraftWithCurrent(value, merged[key]);
            });
            return merged;
        }
        return draftValue;
    }

    function populateFormFromState() {
        document.querySelectorAll('input[name], select[name], textarea[name]').forEach(control => {
            if (control.type === 'file' || control.name.endsWith('[]')) return;

            const verificationMatch = control.name.match(/^(.+)_verification$/);
            const correctedMatch = control.name.match(/^(.+)_corrected$/);
            const mappedName = verificationMatch?.[1] || correctedMatch?.[1] || control.name;
            const path = state.getFieldStatePath(mappedName);
            if (!path) return;

            let value = state.get(path);
            if (verificationMatch) {
                value = state.get(`${path}.verification`);
            } else if (correctedMatch) {
                value = state.get(`${path}.corrected_value`);
            } else if (value && typeof value === 'object' && !Array.isArray(value)) {
                value = value.original_value;
            }

            if (control instanceof HTMLInputElement && control.type === 'radio') {
                control.checked = String(value ?? '') === control.value;
            } else if (control instanceof HTMLInputElement && control.type === 'checkbox') {
                control.checked = Boolean(value);
            } else {
                control.value = value === null || value === undefined ? '' : String(value);
            }
        });
    }

    function persistLocalDraft() {
        if (!draftStorageKey || !state.get('mobile_verified') || !employeeDataReady) return false;

        try {
            localStorage.setItem(draftStorageKey, JSON.stringify({
                version: 2,
                current_step: currentStep,
                form_data: state.toJSON(),
                updated_at: new Date().toISOString()
            }));
            localDraftSaveFailed = false;
            return true;
        } catch (error) {
            console.error('Unable to save draft in browser storage:', error);
            if (!localDraftSaveFailed) {
                showToast('error', 'Progress Not Saved', 'This browser could not save your progress locally.');
            }
            localDraftSaveFailed = true;
            return false;
        }
    }

    function clearDraft() {
        if (!draftStorageKey) return;
        try {
            localStorage.removeItem(draftStorageKey);
        } catch (error) {
            console.error('Unable to clear locally saved draft:', error);
        }
    }

    function showStep(index) {
        if (index < 0 || index >= stepKeys.length) return;

        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(step => {
            step.hidden = true;
            step.classList.remove('active');
        });

        // Show target step
        const stepId = `step-${stepKeys[index]}`;
        const targetStep = document.getElementById(stepId);
        if (targetStep) {
            targetStep.hidden = false;
            targetStep.classList.add('active');
        }

        // Update stepper
        updateStepper(index);

        // Update navigation buttons
        updateNavigationButtons(index);

        // Update current step tracking
        currentStepIndex = index;
        currentStep = stepKeys[index];

        // Evaluate conditional fields for this step
        conditionalFields.evaluateForStep(currentStep, state.getState());

        // Scroll to top of form
        const wizardEl = document.getElementById('form-wizard');
        if (wizardEl) {
            wizardEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Announce step change for accessibility
        announceStepChange(currentStep);
        persistLocalDraft();
    }

    function updateStepper(activeIndex) {
        stepperItems.forEach((item, index) => {
            item.classList.remove('active', 'completed', 'error');
            const stepNumber = item.querySelector('.stepper-step');
            if (stepNumber) {
                stepNumber.dataset.progress = `${index + 1} of ${stepKeys.length}`;
                stepNumber.style.setProperty(
                    '--step-progress',
                    `${((index + 1) / stepKeys.length) * 100}%`
                );
            }

            if (index === activeIndex) {
                item.classList.add('active');
                item.setAttribute('aria-current', 'step');
            } else if (index < activeIndex) {
                item.classList.add('completed');
                item.setAttribute('aria-current', 'false');
            } else {
                item.setAttribute('aria-current', 'false');
            }
        });
    }

    function updateNavigationButtons(index) {
        const isFirst = index === 0;
        const isLast = index === stepKeys.length - 1;

        prevButtons.forEach(btn => {
            btn.disabled = isFirst;
            btn.style.display = isFirst ? 'none' : 'inline-flex';
        });

        nextButtons.forEach(btn => {
            if (isLast) {
                btn.style.display = 'none';
            } else {
                btn.style.display = 'inline-flex';
            }
        });
    }

    function validateCurrentStep() {
        const currentStepElement = document.getElementById(`step-${currentStep}`);
        validation.clearAllErrors(currentStepElement);

        // Validate current step
        const errors = validation.validateStep(currentStep, state.getState());

        if (errors.length > 0) {
            validation.showErrors(errors);
            showValidationSummary(currentStepElement, errors);

            // Show toast
            showToast('error', 'Validation Error', `Please fix ${errors.length} error(s) before continuing.`);

            // Mark stepper item as error
            const currentStepperItem = document.querySelector(`.stepper-item[data-step="${currentStep}"]`);
            if (currentStepperItem) {
                currentStepperItem.classList.add('error');
            }

            return false;
        }

        // Clear errors on success
        showValidationSummary(currentStepElement, []);

        // Remove error from stepper
        const currentStepperItem = document.querySelector(`.stepper-item[data-step="${currentStep}"]`);
        if (currentStepperItem) {
            currentStepperItem.classList.remove('error');
        }

        return true;
    }

    function showValidationSummary(stepElement, errors) {
        if (!stepElement) return;

        const form = stepElement.querySelector('form');
        if (!form) return;

        let summary = form.querySelector('.validation-summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'alert alert-error validation-summary';
            summary.tabIndex = -1;
            summary.setAttribute('role', 'alert');
            summary.setAttribute('aria-live', 'assertive');

            const content = document.createElement('div');
            content.className = 'alert-content';
            const title = document.createElement('p');
            title.className = 'alert-title';
            title.textContent = 'Please correct these items before continuing:';
            const list = document.createElement('ul');
            list.className = 'alert-message validation-summary-list';
            content.append(title, list);
            summary.appendChild(content);
            form.insertBefore(summary, form.firstChild);
        }

        const list = summary.querySelector('.validation-summary-list');
        list.replaceChildren(...errors.map(error => {
            const item = document.createElement('li');
            item.textContent = error.message;
            return item;
        }));
        summary.hidden = errors.length === 0;

        if (errors.length > 0) {
            summary.focus();
            summary.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function validateAndProceed() {
        if (!validateCurrentStep()) return false;
        showStep(currentStepIndex + 1);
        return true;
    }

    function goToStep(target) {
        // Accept either step name (string) or index (number)
        let targetIndex = typeof target === 'string' ? stepKeys.indexOf(target) : target;

        if (targetIndex < 0 || targetIndex >= stepKeys.length) return;

        // Validate current step before leaving (except when going back)
        if (targetIndex > currentStepIndex) {
            if (!validateCurrentStep()) return;
        }

        showStep(targetIndex);
    }

    function isStepCompleted(stepKey) {
        // A step is completed if all its required fields have values
        const errors = validation.validateStep(stepKey, state.getState());
        return errors.length === 0;
    }

    function getStepCompletionStatus() {
        const status = {};
        stepKeys.forEach((step, index) => {
            if (index <= currentStepIndex) {
                status[step] = isStepCompleted(step);
            }
        });
        return status;
    }

    async function saveDraft() {
        if (persistLocalDraft()) {
            state.markClean();
            showToast('success', 'Draft Saved', 'Your progress has been saved in this browser.');
        } else {
            showToast('error', 'Save Failed', 'Could not save progress in this browser.');
        }
    }

    async function submitForm() {
        // This is handled in review.js
        return review.submit();
    }

    function showLoading(message = 'Processing...') {
        const overlay = document.getElementById('global-loading');
        const text = document.getElementById('loading-text');
        if (overlay && text) {
            text.textContent = message;
            overlay.hidden = false;
            overlay.classList.add('active');
        }
    }

    function hideLoading() {
        const overlay = document.getElementById('global-loading');
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => overlay.hidden = true, 300);
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

    function announceStepChange(stepKey) {
        const stepName = steps[stepKey];
        const announcement = document.createElement('div');
        announcement.setAttribute('role', 'status');
        announcement.setAttribute('aria-live', 'polite');
        announcement.className = 'sr-only';
        announcement.textContent = `Step ${stepKeys.indexOf(stepKey) + 1} of ${stepKeys.length}: ${stepName}`;
        document.body.appendChild(announcement);
        setTimeout(() => announcement.remove(), 1000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public API
    return {
        init,
        goToStep,
        getCurrentStep: () => currentStep,
        getCurrentStepIndex: () => currentStepIndex,
        getStepKeys: () => stepKeys,
        validateAndProceed,
        saveDraft,
        isStepCompleted,
        getStepCompletionStatus,
        clearDraft,
        isEmployeeDataReady: () => employeeDataReady,
        showLoading,
        hideLoading,
        showToast
    };
})();