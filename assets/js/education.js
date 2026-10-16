/**
 * Education Module
 * Handles dynamic education qualification entries
 */
window.HRMS_Education = (function() {
    'use strict';

    const state = window.HRMS_FormState;
    const validation = window.HRMS_Validation;
    const conditionalFields = window.HRMS_ConditionalFields;
    const fileUpload = window.HRMS_FileUpload;

    const container = document.getElementById('education-items');
    const addBtn = document.getElementById('add-education');
    const template = document.getElementById('education-template');

    let initialized = false;
    let itemCounter = 0;

    function init() {
        if (initialized) return;
        initialized = true;

        if (!container || !addBtn || !template) {
            return;
        }

        addBtn.addEventListener('click', addEducationEntry);

        // Subscribe to state changes for education array
        state.subscribe((path) => {
            if (path === '*' || path === 'education') {
                renderEducationEntries();
            } else if (path.startsWith('education[')) {
                updateEducationTitle(path);
            }
        });

        // Initial render
        renderEducationEntries();
    }

    function addEducationEntry() {
        const entry = state.addEducation();
        renderEducationEntries();
        // Focus first input of new entry
        setTimeout(() => {
            const newItem = container.querySelector(`[data-id="${entry.id}"]`);
            if (newItem) {
                const firstInput = newItem.querySelector('.form-input, .form-select');
                if (firstInput) firstInput.focus();
            }
        }, 50);
    }

    function renderEducationEntries() {
        const education = state.get('education') || [];
        container.innerHTML = '';

        education.forEach((entry, index) => {
            const clone = template.content.cloneNode(true);
            const item = clone.querySelector('.education-item');
            item.dataset.id = entry.id;

            // Update item number
            const numberEl = item.querySelector('.item-number');
            if (numberEl) numberEl.textContent = index + 1;

            // Title
            const titleEl = item.querySelector('.education-card-title');
            if (titleEl) {
                const qualName = entry.qualification_name?.original_value || `Qualification ${index + 1}`;
                titleEl.innerHTML = `Qualification ${index + 1}: <span class="qual-name">${escapeHtml(qualName)}</span>`;
            }

            // Populate fields
            populateEducationFields(item, entry, index);

            // Attach event listeners
            attachEducationListeners(item, entry);

            container.appendChild(clone);
        });

        // Initialize file uploads for new entries
        fileUpload.initAll();
    }

    function updateEducationTitle(path) {
        const index = Number(path.match(/^education\[(\d+)\]/)?.[1]);
        const entry = state.get('education')[index];
        if (!entry) return;

        const title = container.querySelector(`[data-id="${entry.id}"] .education-card-title`);
        if (title) {
            title.innerHTML = `Qualification ${index + 1}: <span class="qual-name">${escapeHtml(entry.qualification_name?.original_value || `Qualification ${index + 1}`)}</span>`;
        }
    }

    function populateEducationFields(item, entry, index) {
        // Qualification Type
        const typeSelect = item.querySelector('.education-qualification-type');
        if (typeSelect) {
            typeSelect.dataset.storedType = entry.qualification_type?.original_value || '';
            populateQualificationTypes(typeSelect);
            typeSelect.value = entry.qualification_type?.original_value || '';
            typeSelect.name = `education_qualification_type[]`;
            typeSelect.dataset.index = index;
        }

        // Qualification Name
        const nameInput = item.querySelector('input[name="education_qualification_name[]"]');
        if (nameInput) {
            nameInput.value = entry.qualification_name?.original_value || '';
            nameInput.dataset.index = index;
        }

        // Skilled In
        const skilledInput = item.querySelector('input[name="education_skilled_in[]"]');
        if (skilledInput) {
            skilledInput.value = entry.skilled_in?.original_value || '';
            skilledInput.dataset.index = index;
        }

        // Special Talents
        const talentsInput = item.querySelector('input[name="education_special_talents[]"]');
        if (talentsInput) {
            talentsInput.value = entry.special_talents?.original_value || '';
            talentsInput.dataset.index = index;
        }

        // Institution
        const instInput = item.querySelector('input[name="education_institution[]"]');
        if (instInput) {
            instInput.value = entry.institution?.original_value || '';
            instInput.dataset.index = index;
        }

        // Year
        const yearInput = item.querySelector('input[name="education_year[]"]');
        if (yearInput) {
            yearInput.value = entry.year_of_completion?.original_value || '';
            yearInput.dataset.index = index;
        }

        // Certificate Upload
        const certUpload = item.querySelector('.education-certificate-upload');
        if (certUpload) {
            certUpload.dataset.documentType = 'education_certificate';
            const fileInput = certUpload.querySelector('.file-upload-input');
            if (fileInput) fileInput.name = 'education_certificate[]';
            const uploadZone = fileUpload.createFileUploadZone(certUpload, {
                documentType: 'education_certificate',
                onUploadSuccess: (result) => {
                    state.updateEducation(entry.id, {
                        certificate_file_id: result.file_id || result.id
                    });
                },
                onFileRemove: () => {
                    state.updateEducation(entry.id, {
                        certificate_file_id: null
                    });
                }
            });
            uploadZone.setUploaded(entry.certificate_file_id);
            if (entry.legacy_certificate_name) {
                const note = document.createElement('p');
                note.className = 'existing-document-note';
                note.textContent = `Previously recorded: ${entry.legacy_certificate_name}`;
                certUpload.appendChild(note);
            }
        }

        // Show/hide others fields based on qualification type
        const othersFields = item.querySelector('.education-others-fields');
        if (othersFields) {
            const isOthers = entry.qualification_type?.original_value === 'Others';
            othersFields.style.display = isOthers ? 'block' : 'none';
        }
    }

    function populateQualificationTypes(select) {
        const types = window.HRMS_CONFIG.qualificationTypes || [];
        types.forEach(type => {
            if (Array.from(select.options).some(option => option.value === type.value)) return;
            const option = document.createElement('option');
            option.value = type.value;
            option.textContent = type.label;
            select.appendChild(option);
        });
        const storedType = select.dataset.storedType;
        if (storedType && !Array.from(select.options).some(option => option.value === storedType)) {
            select.add(new Option(`${storedType} (stored value)`, storedType));
        }
    }

    function attachEducationListeners(item, entry) {
        const index = Array.from(container.children).findIndex(el => el.dataset.id === entry.id);

        // Qualification Type change
        const typeSelect = item.querySelector('.education-qualification-type');
        if (typeSelect) {
            typeSelect.addEventListener('change', (e) => {
                const value = e.target.value;
                state.updateEducation(entry.id, {
                    qualification_type: { original_value: value, verification: 'pending', corrected_value: null }
                });

                // Show/hide others fields
                const othersFields = item.querySelector('.education-others-fields');
                if (othersFields) {
                    othersFields.style.display = value === 'Others' ? 'block' : 'none';
                }
            });
        }

        // Other fields
        const fieldMappings = [
            { selector: 'input[name="education_qualification_name[]"]', path: 'qualification_name' },
            { selector: 'input[name="education_skilled_in[]"]', path: 'skilled_in' },
            { selector: 'input[name="education_special_talents[]"]', path: 'special_talents' },
            { selector: 'input[name="education_institution[]"]', path: 'institution' },
            { selector: 'input[name="education_year[]"]', path: 'year_of_completion' }
        ];

        fieldMappings.forEach(({ selector, path }) => {
            const input = item.querySelector(selector);
            if (input) {
                input.addEventListener('input', (e) => {
                    const value = e.target.value;
                    state.updateEducation(entry.id, {
                        [path]: { original_value: value, verification: 'pending', corrected_value: null }
                    });
                });

                input.addEventListener('blur', () => {
                    validateEducationField(input, path, entry);
                });
            }
        });

        // Certificate upload
        // Remove button
        const removeBtn = item.querySelector('.repeater-item-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                confirmRemove(`Remove Qualification ${index + 1}?`, () => {
                    state.removeEducation(entry.id);
                });
            });
        }
    }

    function validateEducationField(input, path, entry) {
        const value = input.value;
        const fieldPath = `education[${Array.from(container.children).findIndex(el => el.dataset.id === entry.id)}].${path}`;
        const result = validation.validateField(fieldPath, value, state.getState());

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
        addEducationEntry
    };
})();