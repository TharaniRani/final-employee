/**
 * Referral Module
 * Handles family members working and studying repeaters
 */
window.HRMS_Referral = (function() {
    'use strict';

    const state = window.HRMS_FormState;
    const validation = window.HRMS_Validation;
    const fileUpload = window.HRMS_FileUpload;

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

        // Populate unit dropdowns
        populateUnitDropdowns();
    }

    function populateFamilyWorkingFields(item, entry, index) {
        const nameInput = item.querySelector('input[name="family_working_name[]"]');
        if (nameInput) {
            nameInput.value = entry.name?.original_value || '';
            nameInput.dataset.index = index;
        }

        const unitSelect = item.querySelector('select[name="family_working_unit[]"]');
        if (unitSelect) {
            const value = String(entry.unit?.original_value || '');
            if (value && !Array.from(unitSelect.options).some(option => option.value === value)) {
                unitSelect.add(new Option(`${value} (stored value)`, value));
            }
            unitSelect.value = value;
            unitSelect.dataset.index = index;
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
            { selector: 'input[name="family_working_name[]"]', path: 'name' },
            { selector: 'select[name="family_working_unit[]"]', path: 'unit' },
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
                schoolSelect.add(new Option(`${value} (stored value)`, value));
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

    function populateUnitDropdowns() {
        const selects = document.querySelectorAll('select[name="family_working_unit[]"]');
        const units = getUnits();

        selects.forEach(select => {
            const currentValue = select.value;
            if (select.options.length <= 1) {
                units.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit;
                    option.textContent = unit;
                    select.appendChild(option);
                });
            }
            select.value = currentValue;
        });
    }

    function populateSchoolDropdowns() {
        const selects = document.querySelectorAll('select[name="family_studying_school[]"]');
        const schools = window.HRMS_CONFIG.schools || [];

        selects.forEach(select => {
            const currentValue = select.value;
            if (select.options.length <= 1) {
                schools.forEach(school => {
                    const option = document.createElement('option');
                    option.value = school;
                    option.textContent = school;
                    select.appendChild(option);
                });
            }
            select.value = currentValue;
        });
    }

    function getUnits() {
        // This would ideally come from backend
        return [
            'Lady Andal House of Children, Chetpet',
            'Lady Andal Open School - NIOS, T. Nagar',
            'Lady Andal Open School - OS, T. Nagar',
            'Lady Andal School - IB, Chetpet',
            'Prem Vihar, Chetpet',
            'School of Sound and Music, Chetpet',
            'Shanthi Sadan, Chetpet',
            'Sir & Lady M. Venkatasubba Rao Hr., Sec., School, Tambaram',
            'Sir Mutha School - CBSE, Chetpet',
            'Sir Mutha Venkatasubba Rao Concert Hall, Chetpet',
            'The Madras Seva Sadan - Head Office, Chetpet',
            'The Madras Seva Sadan Hr., Sec., School, Chetpet',
            'The Madras Seva Sadan Hr., Sec., School, Tambaram',
            'The Madras Seva Sadan Nursery and Primary School, Tambaram',
            'Central Office',
            'Administration',
            'Finance',
            'Human Resources',
            'Academic Affairs',
            'Student Affairs',
            'Facilities',
            'IT Department'
        ];
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
        init
    };
})();