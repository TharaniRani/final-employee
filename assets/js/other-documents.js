/**
 * Other Documents Module
 * Handles the repeatable document title and upload fields.
 */
window.HRMS_OtherDocuments = (function() {
    'use strict';

    const state = window.HRMS_FormState;
    const fileUpload = window.HRMS_FileUpload;
    const container = document.getElementById('other-documents-items');
    const addButton = document.getElementById('add-other-document');
    const template = document.getElementById('other-document-template');

    let initialized = false;

    function init() {
        if (initialized) return;
        initialized = true;

        if (!container || !addButton || !template) return;

        addButton.addEventListener('click', addDocument);
        state.subscribe((path) => {
            if (path === '*' || path === 'teacher_documents.other_documents') {
                renderDocuments();
            }
        });

        renderDocuments();
    }

    function addDocument() {
        const entry = state.addOtherDocument();
        focusTitle(entry.id);
    }

    function renderDocuments() {
        if (!container || !template) return;

        const entries = state.get('teacher_documents.other_documents') || [];
        container.innerHTML = '';

        entries.forEach((entry, index) => {
            const clone = template.content.cloneNode(true);
            const item = clone.querySelector('.other-document-item');
            if (!item) return;

            item.dataset.id = entry.id;
            const number = item.querySelector('.item-number');
            if (number) number.textContent = String(index + 1);

            const titleInput = item.querySelector('input[name="other_doc_title[]"]');
            if (titleInput) {
                titleInput.id = `other-doc-title-${entry.id}`;
                titleInput.value = entry.title?.original_value || '';
                titleInput.addEventListener('input', () => {
                    state.updateOtherDocument(entry.id, {
                        title: {
                            original_value: titleInput.value,
                            verification: 'pending',
                            corrected_value: null
                        }
                    });
                });
            }

            const upload = item.querySelector('.other-doc-upload');
            if (upload) {
                upload.dataset.documentType = 'other_document';
                const fileInput = upload.querySelector('.file-upload-input');
                if (fileInput) fileInput.name = `other_doc_file_${entry.id}`;

                const zone = fileUpload.createFileUploadZone(upload, {
                    documentType: 'other_document',
                    onUploadSuccess: (result) => {
                        const fileId = result.file_id || result.document_id || result.id;
                        if (!fileId) {
                            throw new Error('The upload response did not include a document ID');
                        }
                        state.updateOtherDocument(entry.id, { file_id: fileId });
                    },
                    onFileRemove: () => {
                        state.updateOtherDocument(entry.id, { file_id: null });
                    }
                });
                zone.setUploaded(entry.file_id);
            }

            const removeButton = item.querySelector('.repeater-item-remove');
            if (removeButton) {
                removeButton.addEventListener('click', () => {
                    state.removeOtherDocument(entry.id);
                });
            }

            container.appendChild(clone);
        });
    }

    function focusTitle(id) {
        const titleInput = container.querySelector(`[data-id="${id}"] input[name="other_doc_title[]"]`);
        if (titleInput) titleInput.focus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { init };
})();
