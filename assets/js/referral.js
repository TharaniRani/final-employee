/**
 * Referral Module
 * Handles family members working and studying repeaters
 */
window.HRMS_Referral = (function() {
    'use strict';

    const state = window.HRMS_FormState;
    const validation = window.HRMS_Validation;
    const fileUpload = window.HRMS_FileUpload;
    const api = window.HRMS_API;

    // Family Working
    const fwContainer = document.getElementById('family-working-items');
    const fwAddBtn = document.getElementById('add-family-working');
    const fwTemplate = document.getElementById('family-working-template');

    // Family Studying
    const fsContainer = document.getElementById('family-studying-items');
    const fsAddBtn = document.getElementById('add-family-studying');
    const fsTemplate = document.getElementById('family-studying-template');

    let initialized = false;

    function init() {
        if (initialized) return;
        initialized = true;

        // Family Working
        if (fwAddBtn && fwTemplate && fwContainer) {
            fwAddBtn.addEventListener('click', () => addFamilyMember('working'));
        }

        // Family Studying
        if (fsAddBtn && fsTemplate && fsContainer) {
            fsAddBtn.addEventListener('click', () => addFamilyMember('studying'));
        }

        // Radio buttons for conditional display
        setupRadioListeners();

        // Subscribe to state changes
        state.subscribe((path) => {
            if (path === '*') {
                ensureInitialEntry('working');
                ensureInitialEntry('studying');
            }
            if (path === '*' || path === 'referral.family_working_details') {
                renderFamilyWorking();
            } else if (path.startsWith('referral.family_working_details[')) {
                updateFamilyTitle('working', path);
            }
            if (path === '*' || path === 'referral.family_studying_details') {
                renderFamilyStudying();
            } else if (path.startsWith('referral.family_studying_details[')) {
                updateFamilyTitle('studying', path);
            }
        });

        // Initial render
        renderFamilyWorking();
        renderFamilyStudying();
        ensureInitialEntry('working');
        ensureInitialEntry('studying');
    }

    function setupRadioListeners() {
        // Family Working radios
        const fwYes = document.getElementById('family-working-yes');
        const fwNo = document.getElementById('family-working-no');
        if (fwYes && fwNo) {
            [fwYes, fwNo].forEach(radio => {
                radio.addEventListener('change', (e) => {
                    state.set('referral.family_working', e.target.value);
                    if (e.target.value === 'Yes') {
                        ensureInitialEntry('working', true);
                    }
                });
            });
        }

        // Family Studying radios
        const fsYes = document.getElementById('family-studying-yes');
        const fsNo = document.getElementById('family-studying-no');
        if (fsYes && fsNo) {
            [fsYes, fsNo].forEach(radio => {
                radio.addEventListener('change', (e) => {
                    state.set('referral.family_studying', e.target.value);
                    if (e.target.value === 'Yes') {
                        ensureInitialEntry('studying', true);
                    }
                });
            });
        }
    }

    function ensureInitialEntry(type, focus = false) {
        const referral = state.getState().referral;
        const field = type === 'working' ? 'family_working' : 'family_studying';
        const status = referral?.[field];
        const statusValue = typeof status === 'object' && status !== null
            ? status.original_value
            : status;
        const details = referral?.[`${field}_details`];

        if (statusValue !== 'Yes' || !Array.isArray(details) || details.length > 0) {
            return;
        }

        const entry = type === 'working'
            ? state.addFamilyWorking()
            : state.addFamilyStudying();
        if (focus) {
            focusNewEntry(type === 'working' ? fwContainer : fsContainer, entry.id);
        }
    }

    function addFamilyMember(type) {
        if (type === 'working') {
            const entry = state.addFamilyWorking();
            renderFamilyWorking();
            focusNewEntry(fwContainer, entry.id);
        } else {
            const entry = state.addFamilyStudying();
            renderFamilyStudying();
            focusNewEntry(fsContainer, entry.id);
        }
    }

    function focusNewEntry(container, id) {
        if (!container) return;
        setTimeout(() => {
            const newItem = container.querySelector(`[data-id="${id}"]`);
            if (newItem) {
                const firstInput = newItem.querySelector('.form-input, .form-select');
                if (firstInput) firstInput.focus();
            }
        }, 50);
    }

    function renderFamilyWorking() {
        if (!fwContainer || !fwTemplate) return;

        const entries = state.get('referral.family_working_details') || [];
        fwContainer.innerHTML = '';

        entries.forEach((entry, index) => {
            const clone = fwTemplate.content.cloneNode(true);
            const item = clone.querySelector('.family-working-item');
            item.dataset.id = entry.id;

            // Update item number
            const numberEl = item.querySelector('.item-number');
            if (numberEl) numberEl.textContent = index + 1;

            // Title
            const titleEl = item.querySelector('.repeater-item-title');
            if (titleEl) {
                const name = entry.name?.original_value || `Family Member ${index + 1}`;
                titleEl.innerHTML = `Family Member ${index + 1}: <span class="fw-name">${escapeHtml(name)}</span>`;
            }

            // Populate fields
            populateFamilyWorkingFields(item, entry, index);

            // Attach listeners
            attachFamilyWorkingListeners(item, entry);

            fwContainer.appendChild(clone);
        });

    }

    function populateFamilyWorkingFields(item, entry, index) {
        const tmssIdInput = item.querySelector('input[name="family_working_tmss_id[]"]');
        if (tmssIdInput) {
            tmssIdInput.value = entry.tmss_id?.original_value || '';
            tmssIdInput.dataset.index = index;
        }

        const nameInput = item.querySelector('input[name="family_working_name[]"]');
        if (nameInput) {
            nameInput.value = entry.name?.original_value || '';
            nameInput.dataset.index = index;
        }

        const unitInput = item.querySelector('input[name="family_working_unit[]"]');
        if (unitInput) {
            const value = String(entry.unit?.original_value || '');
            unitInput.value = value;
            unitInput.dataset.index = index;
        }

        const designationInput = item.querySelector('input[name="family_working_designation[]"]');
        if (designationInput) {
            designationInput.value = entry.designation?.original_value || '';
            designationInput.dataset.index = index;
        }
    }

    function attachFamilyWorkingListeners(item, entry) {
        const index = Array.from(fwContainer.children).findIndex(el => el.dataset.id === entry.id);

        const fieldMappings = [
            { selector: 'input[name="family_working_tmss_id[]"]', path: 'tmss_id' },
            { selector: 'input[name="family_working_name[]"]', path: 'name' },
            { selector: 'input[name="family_working_unit[]"]', path: 'unit' },
            { selector: 'input[name="family_working_designation[]"]', path: 'designation' }
        ];

        fieldMappings.forEach(({ selector, path }) => {
            const input = item.querySelector(selector);
            if (input) {
                const eventType = input.tagName === 'SELECT' ? 'change' : 'input';
                input.addEventListener(eventType, (e) => {
                    const value = e.target.value;
                    const update = {};
                    update[path] = { original_value: value, verification: 'pending', corrected_value: null };
                    state.updateFamilyWorking(entry.id, update);
                });

                input.addEventListener('blur', () => {
                    validateField(input, `referral.family_working_details[${index}].${path}`);
                });
            }
        });

        const mssIdInput = item.querySelector('input[name="family_working_tmss_id[]"]');
        const lookupError = mssIdInput?.closest('.form-group')?.querySelector('.form-error');
        const nameInput = item.querySelector('input[name="family_working_name[]"]');
        const unitInput = item.querySelector('input[name="family_working_unit[]"]');
        const designationInput = item.querySelector('input[name="family_working_designation[]"]');
        let lookupTimer = null;
        let lookupSequence = 0;
        let lastLookedUpId = '';
        let previousMssEmployeeId = mssIdInput?.value.trim() || '';

        const setLookupMessage = (message) => {
            if (!lookupError || !mssIdInput) return;
            lookupError.textContent = message;
            lookupError.style.display = message ? 'flex' : 'none';
            mssIdInput.classList.toggle('error', !!message);
            if (message) {
                mssIdInput.setAttribute('aria-invalid', 'true');
            } else {
                mssIdInput.removeAttribute('aria-invalid');
            }
        };

        const updateEmploymentField = (path, control, value) => {
            if (!control) return;
            const normalizedValue = String(value || '').trim();
            control.value = normalizedValue;
            state.updateFamilyWorking(entry.id, {
                [path]: {
                    original_value: normalizedValue,
                    verification: 'pending',
                    corrected_value: null
                }
            });
        };

        const clearEmploymentFields = () => {
            updateEmploymentField('name', nameInput, '');
            updateEmploymentField('unit', unitInput, '');
            updateEmploymentField('designation', designationInput, '');
        };

        const lookupEmployment = async () => {
            if (!mssIdInput) return;
            const mssEmployeeId = mssIdInput.value.trim();
            if (!mssEmployeeId) {
                setLookupMessage('');
                return;
            }

            const requestSequence = ++lookupSequence;
            try {
                const result = await api.getFamilyMemberEmployment(mssEmployeeId);
                if (requestSequence !== lookupSequence || mssIdInput.value.trim() !== mssEmployeeId) return;
                updateEmploymentField('name', nameInput, result.employee?.full_name);
                updateEmploymentField('unit', unitInput, result.employee?.unit);
                updateEmploymentField('designation', designationInput, result.employee?.designation);
                lastLookedUpId = mssEmployeeId;
                previousMssEmployeeId = mssEmployeeId;
                setLookupMessage('');
            } catch (error) {
                if (requestSequence !== lookupSequence || mssIdInput.value.trim() !== mssEmployeeId) return;
                clearEmploymentFields();
                lastLookedUpId = '';
                setLookupMessage(error.message || 'No employee was found for that MSS Employee ID.');
            }
        };

        if (mssIdInput) {
            mssIdInput.addEventListener('input', () => {
                const mssEmployeeId = mssIdInput.value.trim();
                lookupSequence++;
                clearTimeout(lookupTimer);
                setLookupMessage('');
                if (mssEmployeeId !== previousMssEmployeeId) {
                    clearEmploymentFields();
                    lastLookedUpId = '';
                }
                previousMssEmployeeId = mssEmployeeId;
                if (mssEmployeeId) {
                    lookupTimer = setTimeout(lookupEmployment, 600);
                }
            });
            mssIdInput.addEventListener('blur', () => {
                clearTimeout(lookupTimer);
                lookupEmployment();
            });
        }

        // Remove button
        const removeBtn = item.querySelector('.repeater-item-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                confirmRemove(`Remove Family Member ${index + 1}?`, () => {
                    state.removeFamilyWorking(entry.id);
                });
            });
        }
    }

    function renderFamilyStudying() {
        if (!fsContainer || !fsTemplate) return;

        const entries = state.get('referral.family_studying_details') || [];
        fsContainer.innerHTML = '';

        entries.forEach((entry, index) => {
            const clone = fsTemplate.content.cloneNode(true);
            const item = clone.querySelector('.family-studying-item');
            item.dataset.id = entry.id;

            // Update item number
            const numberEl = item.querySelector('.item-number');
            if (numberEl) numberEl.textContent = index + 1;

            // Title
            const titleEl = item.querySelector('.repeater-item-title');
            if (titleEl) {
                const name = entry.name?.original_value || `Family Member ${index + 1}`;
                titleEl.innerHTML = `Family Member ${index + 1}: <span class="fs-name">${escapeHtml(name)}</span>`;
            }

            // Populate fields
            populateFamilyStudyingFields(item, entry, index);

            // Attach listeners
            attachFamilyStudyingListeners(item, entry);

            fsContainer.appendChild(clone);
        });

        // Populate school dropdowns
        populateSchoolDropdowns();
    }

    function updateFamilyTitle(type, path) {
        const index = Number(path.match(/details\[(\d+)\]/)?.[1]);
        const entries = state.get(type === 'working'
            ? 'referral.family_working_details'
            : 'referral.family_studying_details');
        const entry = entries[index];
        if (!entry) return;

        const container = type === 'working' ? fwContainer : fsContainer;
        const title = container.querySelector(`[data-id="${entry.id}"] .${type === 'working' ? 'fw-name' : 'fs-name'}`);
        if (title) {
            title.textContent = entry.name?.original_value || `Family Member ${index + 1}`;
        }
    }

    function populateFamilyStudyingFields(item, entry, index) {
        const nameInput = item.querySelector('input[name="family_studying_name[]"]');
        if (nameInput) {
            nameInput.value = entry.name?.original_value || '';
            nameInput.dataset.index = index;
        }

        const schoolSelect = item.querySelector('select[name="family_studying_school[]"]');
        if (schoolSelect) {
            const value = String(entry.school?.original_value || '');
            if (value && !Array.from(schoolSelect.options).some(option => option.value === value)) {
                schoolSelect.add(new Option(value, value));
            }
            schoolSelect.value = value;
            schoolSelect.dataset.index = index;
        }

        const classInput = item.querySelector('input[name="family_studying_class[]"]');
        if (classInput) {
            classInput.value = entry.class?.original_value || '';
            classInput.dataset.index = index;
        }
    }

    function attachFamilyStudyingListeners(item, entry) {
        const index = Array.from(fsContainer.children).findIndex(el => el.dataset.id === entry.id);

        const fieldMappings = [
            { selector: 'input[name="family_studying_name[]"]', path: 'name' },
            { selector: 'select[name="family_studying_school[]"]', path: 'school' },
            { selector: 'input[name="family_studying_class[]"]', path: 'class' }
        ];

        fieldMappings.forEach(({ selector, path }) => {
            const input = item.querySelector(selector);
            if (input) {
                const eventType = input.tagName === 'SELECT' ? 'change' : 'input';
                input.addEventListener(eventType, (e) => {
                    const value = e.target.value;
                    const update = {};
                    update[path] = { original_value: value, verification: 'pending', corrected_value: null };
                    state.updateFamilyStudying(entry.id, update);
                });

                input.addEventListener('blur', () => {
                    validateField(input, `referral.family_studying_details[${index}].${path}`);
                });
            }
        });

        // Remove button
        const removeBtn = item.querySelector('.repeater-item-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                confirmRemove(`Remove Family Member ${index + 1}?`, () => {
                    state.removeFamilyStudying(entry.id);
                });
            });
        }
    }

    function populateSchoolDropdowns() {
        const selects = document.querySelectorAll('select[name="family_studying_school[]"]');
        const schools = window.HRMS_CONFIG.schools || [];

        selects.forEach(select => {
            const currentValue = select.value;
            const availableSchools = new Set(
                Array.from(select.options, option => option.value)
            );
            schools.forEach(school => {
                if (availableSchools.has(school)) return;
                select.add(new Option(school, school));
                availableSchools.add(school);
            });
            select.value = currentValue;
        });
    }

    function syncFamilyStudyingFields(submissionData = null) {
        if (!fsContainer) return;

        fsContainer.querySelectorAll('.family-studying-item').forEach(item => {
            const id = item.dataset.id;
            if (!id) return;

            const entries = state.get('referral.family_studying_details') || [];
            const entryIndex = entries.findIndex(student => student.id === id);
            const entry = entries[entryIndex];
            const submissionEntry = submissionData?.referral?.family_studying_details?.find(
                student => student.id === id
            );
            if (!entry) return;

            const updates = {};
            [
                ['name', 'input[name="family_studying_name[]"]'],
                ['school', 'select[name="family_studying_school[]"]'],
                ['class', 'input[name="family_studying_class[]"]']
            ].forEach(([field, selector]) => {
                const control = item.querySelector(selector);
                if (!control) return;

                const currentValue = control.value;
                if (currentValue !== (entry[field]?.original_value || '')) {
                    updates[field] = {
                        original_value: currentValue,
                        verification: 'pending',
                        corrected_value: null
                    };
                }
                if (submissionEntry) {
                    submissionEntry[field] = {
                        ...(submissionEntry[field] || {}),
                        original_value: currentValue,
                        verification: 'pending',
                        corrected_value: null
                    };
                }
            });

            if (Object.keys(updates).length > 0) {
                state.updateFamilyStudying(id, updates);
            }
        });
    }

    function validateField(input, fieldPath) {
        const value = input.value;
        const result = validation.validateField(fieldPath, value, state.getState());

        if (!result.valid) {
            validation.showError(input.id || fieldPath.replace(/\./g, '-'), result.message);
        } else {
            validation.clearError(input.id || fieldPath.replace(/\./g, '-'));
        }
    }

    function confirmRemove(message, onConfirm) {
        const modal = document.getElementById('confirm-modal');
        const messageEl = document.getElementById('confirm-modal-message');
        const confirmBtn = document.getElementById('confirm-modal-confirm');
        const cancelBtn = document.getElementById('confirm-modal-cancel');
        const closeBtn = document.getElementById('confirm-modal-close');

        messageEl.textContent = message;
        modal.hidden = false;
        modal.classList.add('active');

        const cleanup = () => {
            modal.hidden = true;
            modal.classList.remove('active');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            closeBtn.removeEventListener('click', handleCancel);
        };

        const handleConfirm = () => {
            cleanup();
            onConfirm();
        };

        const handleCancel = () => {
            cleanup();
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        closeBtn.addEventListener('click', handleCancel);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init,
        syncFamilyStudyingFields
    };
})();