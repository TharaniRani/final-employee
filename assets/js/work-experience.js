/**
 * Work Experience Module
 * Handles dynamic work experience entries
 */
window.HRMS_WorkExperience = (function() {
    'use strict';

    const state = window.HRMS_FormState;
    const validation = window.HRMS_Validation;
    const fileUpload = window.HRMS_FileUpload;

    let container = null;
    let addBtn = null;
    let template = null;
    let naCheckbox = null;

    let initialized = false;

    function getElements() {
        container = document.getElementById('experience-items');
        addBtn = document.getElementById('add-experience');
        template = document.getElementById('experience-template');
        naCheckbox = document.getElementById('experience-na');
        return container && addBtn && template;
    }

    function init() {
        if (initialized) return;
        initialized = true;

        if (!getElements()) {
            // Elements not ready yet, retry on next tick
            setTimeout(init, 100);
            return;
        }

        console.log('[WorkExperience] Elements found, initializing...');
        addBtn.addEventListener('click', (e) => {
            console.log('[WorkExperience] Add button clicked');
            addExperienceEntry();
        });

        // Not Applicable checkbox
        if (naCheckbox) {
            naCheckbox.addEventListener('change', (e) => {
                const isNA = e.target.checked;
                state.set('work_experience.not_applicable', isNA);
                toggleExperienceSection(isNA);
            });
        }

        // Subscribe to state changes
        state.subscribe((path) => {
            if (path === '*' || path === 'work_experience.entries' || path === 'work_experience.not_applicable') {
                renderExperienceEntries();
            } else if (path.startsWith('work_experience.entries[')) {
                updateExperienceTitle(path);
            }
        });

        // Initial render
        renderExperienceEntries();
    }

    function toggleExperienceSection(isNA) {
        isNA = Boolean(isNA);
        if (naCheckbox) {
            naCheckbox.checked = isNA;
        }

        const section = document.getElementById('experience-details-section');
        if (section) {
            section.style.display = isNA ? 'none' : 'block';
        }
        if (addBtn) {
            addBtn.style.display = isNA ? 'none' : 'inline-flex';
        }
    }

    function addExperienceEntry() {
        console.log('[WorkExperience] addExperienceEntry called');
        const entry = state.addExperience();
        console.log('[WorkExperience] Entry created:', entry.id);
        renderExperienceEntries();

        setTimeout(() => {
            if (!container) return;
            const newItem = container.querySelector(`[data-id="${entry.id}"]`);
            if (newItem) {
                const firstInput = newItem.querySelector('.form-input, .form-select');
                if (firstInput) firstInput.focus();
            }
        }, 50);
    }

    function renderExperienceEntries() {
        const entries = state.get('work_experience.entries') || [];
        const isNA = state.get('work_experience.not_applicable');

        if (!getElements()) {
            console.warn('[WorkExperience] Elements not available for rendering');
            return;
        }

        toggleExperienceSection(isNA);

        container.innerHTML = '';

        entries.forEach((entry, index) => {
            const clone = template.content.cloneNode(true);
            const item = clone.querySelector('.experience-item');
            if (!item) {
                console.error('[WorkExperience] Template missing .experience-item');
                return;
            }
            item.dataset.id = entry.id;

            // Update item number
            const numberEl = item.querySelector('.item-number');
            if (numberEl) numberEl.textContent = index + 1;

            // Title
            const titleEl = item.querySelector('.experience-card-title');
            if (titleEl) {
                const company = entry.company?.original_value || `Experience ${index + 1}`;
                titleEl.innerHTML = `Work Experience ${index + 1}: <span class="exp-company">${escapeHtml(company)}</span>`;
            }

            // Populate fields
            populateExperienceFields(item, entry, index);

            // Attach event listeners
            attachExperienceListeners(item, entry);

            container.appendChild(clone);
        });

        // Initialize file uploads
        fileUpload.initAll();
    }

    function updateExperienceTitle(path) {
        const index = Number(path.match(/^work_experience\.entries\[(\d+)\]/)?.[1]);
        const entry = state.get('work_experience.entries')[index];
        if (!entry) return;

        const title = container.querySelector(`[data-id="${entry.id}"] .experience-card-title`);
        if (title) {
            title.innerHTML = `Work Experience ${index + 1}: <span class="exp-company">${escapeHtml(entry.company?.original_value || `Experience ${index + 1}`)}</span>`;
        }
    }

    function populateExperienceFields(item, entry, index) {
        const fieldMappings = [
            { selector: 'input[name="experience_company[]"]', path: 'company', id: `experience-${index}-company` },
            { selector: 'input[name="experience_designation[]"]', path: 'designation', id: `experience-${index}-designation` },
            { selector: 'input[name="experience_start_month[]"]', path: 'start_month', id: `experience-${index}-start_month` },
            { selector: 'input[name="experience_end_month[]"]', path: 'end_month', id: `experience-${index}-end_month` },
            { selector: 'textarea[name="experience_details[]"]', path: 'details', id: `experience-${index}-details` }
        ];

        fieldMappings.forEach(({ selector, path, id }) => {
            const input = item.querySelector(selector);
            if (input) {
                input.value = entry[path]?.original_value || '';
                input.dataset.index = index;
                input.id = id;
                // Also set ID on the error div if it exists
                const errorDiv = input.parentElement.querySelector('.form-error');
                if (errorDiv) {
                    errorDiv.id = `${id}-error`;
                }
            }
        });

        // Experience Letter Upload
        const letterUpload = item.querySelector('.experience-letter-upload');
        if (letterUpload) {
            letterUpload.dataset.documentType = 'experience_letter';
            const fileInput = letterUpload.querySelector('.file-upload-input');
            if (fileInput) fileInput.name = 'experience_letter[]';
        }

        // Pay Slip Upload
        const payslipUpload = item.querySelector('.experience-payslip-upload');
        if (payslipUpload) {
            payslipUpload.dataset.documentType = 'experience_payslip';
            const fileInput = payslipUpload.querySelector('.file-upload-input');
            if (fileInput) fileInput.name = 'experience_payslip[]';
        }

        // Initialize month restrictions after populating fields
        updateMonthRestrictions(item, entry);
    }

    function attachExperienceListeners(item, entry) {
        const index = Array.from(container.children).findIndex(el => el.dataset.id === entry.id);

        const fieldMappings = [
            { selector: 'input[name="experience_company[]"]', path: 'company' },
            { selector: 'input[name="experience_designation[]"]', path: 'designation' },
            { selector: 'input[name="experience_start_month[]"]', path: 'start_month' },
            { selector: 'input[name="experience_end_month[]"]', path: 'end_month' },
            { selector: 'textarea[name="experience_details[]"]', path: 'details' }
        ];

        fieldMappings.forEach(({ selector, path }) => {
            const input = item.querySelector(selector);
            if (input) {
                input.addEventListener('input', (e) => {
                    const value = e.target.value;
                    state.updateExperience(entry.id, {
                        [path]: { original_value: value, verification: 'pending', corrected_value: null }
                    });
                    
                    // Update min/max on date inputs for cross-field restriction
                    if (path === 'start_month' || path === 'end_month') {
                        updateMonthRestrictions(item, entry);
                    }
                });

                input.addEventListener('blur', () => {
                    validateExperienceField(input, path, entry);
                });
            }
        });

        // Experience Letter upload
        const letterUpload = item.querySelector('.experience-letter-upload');
        if (letterUpload) {
            const uploadZone = fileUpload.createFileUploadZone(letterUpload, {
                documentType: 'experience_letter',
                onUploadSuccess: (result) => {
                    state.updateExperience(entry.id, {
                        experience_letter_file_id: result.file_id || result.id
                    });
                },
                onFileRemove: () => {
                    state.updateExperience(entry.id, {
                        experience_letter_file_id: null
                    });
                }
            });
            uploadZone.setUploaded(entry.experience_letter_file_id);
            if (entry.legacy_experience_letter_name) {
                const note = document.createElement('p');
                note.className = 'existing-document-note';
                note.textContent = `Previously recorded: ${entry.legacy_experience_letter_name}`;
                letterUpload.appendChild(note);
            }
        }

        // Pay Slip upload
        const payslipUpload = item.querySelector('.experience-payslip-upload');
        if (payslipUpload) {
            const uploadZone = fileUpload.createFileUploadZone(payslipUpload, {
                documentType: 'experience_payslip',
                onUploadSuccess: (result) => {
                    state.updateExperience(entry.id, {
                        pay_slip_file_id: result.file_id || result.id
                    });
                },
                onFileRemove: () => {
                    state.updateExperience(entry.id, {
                        pay_slip_file_id: null
                    });
                }
            });
            uploadZone.setUploaded(entry.pay_slip_file_id);
            if (entry.legacy_pay_slip_name) {
                const note = document.createElement('p');
                note.className = 'existing-document-note';
                note.textContent = `Previously recorded: ${entry.legacy_pay_slip_name}`;
                payslipUpload.appendChild(note);
            }
        }

        // Remove button
        const removeBtn = item.querySelector('.repeater-item-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                confirmRemove(`Remove Work Experience ${index + 1}?`, () => {
                    state.removeExperience(entry.id);
                });
            });
        }

        // Initialize month restrictions on load
        updateMonthRestrictions(item, entry);
    }

    function updateMonthRestrictions(item, entry) {
        const startInput = item.querySelector('input[name="experience_start_month[]"]');
        const endInput = item.querySelector('input[name="experience_end_month[]"]');
        
        if (startInput && endInput) {
            const startValue = startInput.value;
            const endValue = endInput.value;
            
            // End month cannot be before start month
            if (startValue) {
                endInput.min = startValue;
                if (endValue && endValue < startValue) {
                    endInput.value = '';
                    // Clear state for end_month
                    state.updateExperience(entry.id, {
                        end_month: { original_value: '', verification: 'pending', corrected_value: null }
                    });
                }
            } else {
                endInput.min = '';
            }
            
            // Start month cannot be after end month
            if (endValue) {
                startInput.max = endValue;
                if (startValue && startValue > endValue) {
                    startInput.value = '';
                    // Clear state for start_month
                    state.updateExperience(entry.id, {
                        start_month: { original_value: '', verification: 'pending', corrected_value: null }
                    });
                }
            } else {
                startInput.max = '';
            }
        }
    }

    function validateExperienceField(input, path, entry) {
        const value = input.value;
        const fieldPath = `work_experience.entries[${Array.from(container.children).findIndex(el => el.dataset.id === entry.id)}].${path}`;
        
        // Get the full entry object from state to pass as context for cross-field validation
        const fullState = state.getState();
        const entryContext = fullState.work_experience?.entries?.find(e => e.id === entry.id);
        
        // Pass the entry object with original_value structure
        const valueObj = { original_value: value };
        const result = validation.validateField(fieldPath, valueObj, state.getState(), null, entryContext);

        if (!result.valid) {
            validation.showError(input.id || `${path}-${entry.id}`, result.message);
        } else {
            validation.clearError(input.id || `${path}-${entry.id}`);
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
        addExperienceEntry
    };
})();