/**
 * File Upload Module
 * Stages files in browser storage and uploads them on final submission
 */
window.HRMS_FileUpload = (function() {
    'use strict';

    const config = window.HRMS_CONFIG;
    const api = window.HRMS_API;
    const state = window.HRMS_FormState;
    const maxFileSize = config.validation.maxFileSize;
    const allowedTypes = config.validation.allowedFileTypes;

    const staticFileStatePaths = {
        'resume-upload': 'personal_basic.resume_file_id',
        'aadhaar-upload': 'aadhaar_pan.aadhaar.file_id',
        'pan-upload': 'aadhaar_pan.pan.file_id',
        'photo-upload': 'personal_details.photo_file_id',
        'signature-upload': 'personal_details.signature_file_id',
        'bed-upload': 'teacher_documents.bed_file_id',
        'driving-license-upload': 'teacher_documents.driving_license_file_id',
        'police-verification-upload': 'teacher_documents.police_verification_file_id'
    };

    let uploadListeners = new Set();
    const initializedZones = new WeakMap();
    const documentNames = new Map();
    let persistedFileSyncRegistered = false;
    let pendingFilesDatabase;
    const pendingFilesStore = 'pendingFiles';
    const localFilePrefix = 'local-file:';
    const pendingFileOwner = String(config.verifiedMobile || '').replace(/\D/g, '');

    function openPendingFilesDatabase() {
        if (pendingFilesDatabase) return pendingFilesDatabase;

        pendingFilesDatabase = new Promise((resolve, reject) => {
            const request = indexedDB.open('hrms-onboarding-files', 1);
            request.onupgradeneeded = () => {
                const database = request.result;
                if (!database.objectStoreNames.contains(pendingFilesStore)) {
                    const store = database.createObjectStore(pendingFilesStore, { keyPath: 'id' });
                    store.createIndex('owner', 'owner', { unique: false });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error || new Error('Could not open browser file storage'));
            request.onblocked = () => reject(new Error('Browser file storage is blocked by another tab'));
        });

        return pendingFilesDatabase;
    }

    async function savePendingFile(record) {
        const database = await openPendingFilesDatabase();
        await new Promise((resolve, reject) => {
            const transaction = database.transaction(pendingFilesStore, 'readwrite');
            transaction.objectStore(pendingFilesStore).put(record);
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error || new Error('Could not save the selected file'));
            transaction.onabort = () => reject(transaction.error || new Error('Saving the selected file was cancelled'));
        });
    }

    async function getPendingFile(id) {
        const database = await openPendingFilesDatabase();
        return new Promise((resolve, reject) => {
            const request = database.transaction(pendingFilesStore, 'readonly')
                .objectStore(pendingFilesStore)
                .get(id);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error || new Error('Could not restore the selected file'));
        });
    }

    async function removePendingFile(id) {
        if (!String(id).startsWith(localFilePrefix)) return;

        const database = await openPendingFilesDatabase();
        await new Promise((resolve, reject) => {
            const transaction = database.transaction(pendingFilesStore, 'readwrite');
            transaction.objectStore(pendingFilesStore).delete(id);
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error || new Error('Could not remove the saved file'));
            transaction.onabort = () => reject(transaction.error || new Error('Removing the saved file was cancelled'));
        });
    }

    function createLocalFileId() {
        const uniqueId = window.crypto?.randomUUID
            ? window.crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        return `${localFilePrefix}${uniqueId}`;
    }

    function collectLocalFileReferences(value, references = []) {
        if (!value || typeof value !== 'object') return references;

        Object.entries(value).forEach(([key, child]) => {
            if (typeof child === 'string' && child.startsWith(localFilePrefix) &&
                (key === 'file_id' || key.endsWith('_file_id'))) {
                references.push({ parent: value, key, id: child });
            } else if (child && typeof child === 'object') {
                collectLocalFileReferences(child, references);
            }
        });
        return references;
    }

    async function uploadPendingFiles(formData) {
        const references = collectLocalFileReferences(formData);
        const uploadedIds = new Map();

        for (const reference of references) {
            if (uploadedIds.has(reference.id)) {
                reference.parent[reference.key] = uploadedIds.get(reference.id);
                continue;
            }

            const record = await getPendingFile(reference.id);
            if (!record || record.owner !== pendingFileOwner) {
                throw new Error(`The saved file "${reference.id}" is unavailable. Select it again before submitting.`);
            }

            let serverFileId = record.serverFileId;
            if (!serverFileId) {
                const result = await api.uploadDocument(record.file, record.documentType, record.metadata);
                serverFileId = result.file_id || result.document_id || result.id;
                if (!serverFileId) {
                    throw new Error(`The upload response did not include an ID for "${record.originalName}".`);
                }
                documentNames.set(String(serverFileId), record.originalName);
                await savePendingFile({ ...record, serverFileId });
            }

            const normalizedFileId = String(serverFileId);
            uploadedIds.set(reference.id, normalizedFileId);
            reference.parent[reference.key] = normalizedFileId;
        }

        return uploadedIds.size;
    }

    async function clearPendingFiles() {
        if (!pendingFileOwner || !('indexedDB' in window)) return true;

        try {
            const database = await openPendingFilesDatabase();
            const records = await new Promise((resolve, reject) => {
                const request = database.transaction(pendingFilesStore, 'readonly')
                    .objectStore(pendingFilesStore)
                    .getAll();
                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => reject(request.error || new Error('Could not list saved files'));
            });
            const ownerRecords = records.filter(record => record.owner === pendingFileOwner);
            for (const record of ownerRecords) {
                await removePendingFile(record.id);
                documentNames.delete(record.id);
            }
            return true;
        } catch (error) {
            console.error('Unable to clear locally saved files:', error);
            return false;
        }
    }

    function notifyUploadListeners(event) {
        uploadListeners.forEach(listener => {
            try {
                listener(event);
            } catch (error) {
                console.error('Upload listener error:', error);
            }
        });
    }

    function validateFile(file) {
        const errors = [];

        if (file.size > maxFileSize) {
            errors.push(`File size exceeds ${formatFileSize(maxFileSize)} limit`);
        }

        if (!allowedTypes.includes(file.type)) {
            errors.push('File type not allowed. Allowed: PDF, JPG, PNG');
        }

        return {
            valid: errors.length === 0,
            errors
        };
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function getFileIcon(file) {
        if (file.type === 'application/pdf') {
            return 'pdf';
        }
        if (file.type.startsWith('image/')) {
            return 'image';
        }
        return 'file';
    }

    function createFileUploadZone(container, options = {}) {
        if (initializedZones.has(container)) {
            return initializedZones.get(container);
        }

        const {
            documentType,
            fieldName,
            required = true,
            accept = '.pdf,.jpg,.jpeg,.png',
            multiple = false,
            onUploadSuccess,
            onUploadError,
            onFileSelect,
            onFileRemove
        } = options;

        const zone = container.querySelector('.file-upload-zone');
        const input = container.querySelector('.file-upload-input');
        const info = container.querySelector('.file-upload-info');
        const nameEl = container.querySelector('.file-upload-name-text');
        const removeBtn = container.querySelector('.file-upload-remove');
        const replaceBtn = container.querySelector('.file-upload-replace');
        const actions = container.querySelector('.file-upload-actions');
        let viewBtn = container.querySelector('.file-upload-view');

        if (actions && !viewBtn) {
            viewBtn = document.createElement('button');
            viewBtn.type = 'button';
            viewBtn.className = 'btn btn-sm file-upload-view';
            viewBtn.setAttribute('aria-label', 'View uploaded document');
            viewBtn.title = 'View uploaded document';
            viewBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> View';
            actions.appendChild(viewBtn);
        }

        let currentFile = null;
        let fileId = null;

        function updateUI(file) {
            currentFile = file;
            if (file) {
                zone.classList.add('has-file');
                zone.querySelector('.file-upload-text').textContent = file.name;
                zone.querySelector('.file-upload-hint').textContent = file.pendingUpload
                    ? `${formatFileSize(file.size)} • Saved in this browser until submission`
                    : file.persisted
                        ? 'Previously uploaded'
                        : `${formatFileSize(file.size)} • ${file.type}`;
                if (info) info.style.display = 'flex';
                if (nameEl) nameEl.textContent = file.name;
                if (viewBtn) viewBtn.disabled = false;
            } else {
                zone.classList.remove('has-file');
                zone.querySelector('.file-upload-text').textContent = 'Upload Document';
                zone.querySelector('.file-upload-hint').textContent = 'PDF, JPG, PNG up to 5MB';
                if (info) info.style.display = 'none';
                if (viewBtn) viewBtn.disabled = true;
            }
        }

        async function handleFileSelect(file) {
            const validation = validateFile(file);
            if (!validation.valid) {
                showError(container, validation.errors.join(', '));
                if (onUploadError) onUploadError(validation.errors);
                return false;
            }

            clearError(container);

            const previousFile = currentFile;
            const previousFileId = fileId;
            zone.classList.add('uploading');
            const uploadText = zone.querySelector('.file-upload-text');
            const originalText = uploadText.textContent;
            uploadText.textContent = 'Saving in this browser...';

            try {
                const localId = createLocalFileId();
                await savePendingFile({
                    id: localId,
                    owner: pendingFileOwner,
                    file,
                    documentType,
                    metadata: {},
                    originalName: file.name,
                    mimeType: file.type,
                    size: file.size,
                    serverFileId: null
                });

                fileId = localId;
                documentNames.set(localId, file.name);
                updateUI({
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    pendingUpload: true
                });
                zone.classList.remove('uploading');

                if (previousFileId && previousFileId !== localId) {
                    documentNames.delete(String(previousFileId));
                    removePendingFile(previousFileId).catch(error => {
                        console.error('Unable to remove the replaced local file:', error);
                    });
                }
                const result = {
                    success: true,
                    file_id: localId,
                    document_id: localId,
                    original_name: file.name,
                    pending: true
                };

                if (onUploadSuccess) onUploadSuccess(result, file);
                if (onFileSelect) onFileSelect(file, result);

                notifyUploadListeners({ type: 'file-saved-locally', file, result, documentType, container });
                return true;
            } catch (error) {
                zone.classList.remove('uploading');
                uploadText.textContent = originalText;
                currentFile = previousFile;
                fileId = previousFileId;
                updateUI(previousFile);

                showError(container, error.message || 'Could not save file in this browser');
                if (onUploadError) onUploadError(error);
                notifyUploadListeners({ type: 'file-save-error', file, error, documentType, container });
                return false;
            }
        }

        function handleRemove() {
            currentFile = null;
            if (fileId !== null) {
                documentNames.delete(String(fileId));
                removePendingFile(fileId).catch(error => {
                    console.error('Unable to remove the locally saved file:', error);
                });
            }
            fileId = null;
            input.value = '';
            updateUI(null);
            clearError(container);
            if (onFileRemove) onFileRemove();
            notifyUploadListeners({ type: 'file-removed', documentType, container });
        }

        function handleReplace() {
            input.click();
        }

        // Event listeners
        input.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) handleFileSelect(file);
            input.value = ''; // Allow selecting same file again
        });

        zone.addEventListener('click', (e) => {
            if (e.target === zone || e.target.closest('.file-upload-icon') || e.target.closest('.file-upload-text') || e.target.closest('.file-upload-hint')) {
                if (!currentFile) input.click();
            }
        });

        zone.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && !currentFile) {
                e.preventDefault();
                input.click();
            }
        });

        // Drag and drop
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');

            const file = e.dataTransfer.files[0];
            if (file) handleFileSelect(file);
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleRemove();
            });
        }
        if (replaceBtn) {
            replaceBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleReplace();
            });
        }
        if (viewBtn) {
            viewBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (!fileId || !currentFile) return;

                if (window.HRMS_Review?.previewFile) {
                    window.HRMS_Review.previewFile(fileId, currentFile.name);
                } else {
                    showError(container, 'Document preview is unavailable. Please refresh the page and try again.');
                }
            });
        }

        // Public API for this upload zone
        const uploadZone = {
            getFile() {
                return currentFile;
            },
            getFileId() {
                return fileId;
            },
            setFile(file, id) {
                currentFile = file;
                fileId = id;
                updateUI(file);
            },
            setUploaded(id) {
                fileId = id || null;
                if (!id) {
                    currentFile = null;
                    updateUI(null);
                    return;
                }
                if (!String(id).startsWith(localFilePrefix)) {
                    currentFile = {
                        name: documentNames.get(String(id)) || 'Previously uploaded document',
                        persisted: true
                    };
                    updateUI(currentFile);
                    return;
                }

                currentFile = null;
                updateUI(null);
                getPendingFile(id).then(record => {
                    if (fileId !== id) return;
                    if (!record || record.owner !== pendingFileOwner) {
                        showError(container, 'This locally saved file is unavailable. Please select it again.');
                        return;
                    }
                    documentNames.set(id, record.originalName);
                    currentFile = {
                        name: record.originalName,
                        size: record.size,
                        type: record.mimeType,
                        pendingUpload: true
                    };
                    updateUI(currentFile);
                }).catch(error => {
                    console.error('Unable to restore locally saved upload:', error);
                    showError(container, 'Could not restore this file from browser storage. Please select it again.');
                });
            },
            clear() {
                handleRemove();
            },
            isValid() {
                return !!currentFile;
            }
        };

        initializedZones.set(container, uploadZone);
        return uploadZone;
    }

    function showError(container, message) {
        let errorEl = container.querySelector('.form-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'form-error';
            container.appendChild(errorEl);
        }
        errorEl.textContent = message;
        errorEl.style.display = 'flex';
        container.classList.add('has-error');
    }

    function clearError(container) {
        const errorEl = container.querySelector('.form-error');
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.style.display = 'none';
        }
        container.classList.remove('has-error');
    }

    // Initialize all file upload zones on page
    function initAll() {
        document.querySelectorAll('.file-upload').forEach(container => {
            const statePath = staticFileStatePaths[container.id];
            if (!statePath) return;

            const documentType = container.dataset.documentType || container.id?.replace('-upload', '') || 'document';
            const zone = createFileUploadZone(container, {
                documentType,
                onUploadSuccess: (result) => {
                    const fileId = result.file_id || result.document_id;
                    if (!fileId) {
                        throw new Error('The upload response did not include a document ID');
                    }
                    state.set(statePath, fileId);
                },
                onFileRemove: () => state.set(statePath, null)
            });
            zone.setUploaded(state.get(statePath));
        });

        if (!persistedFileSyncRegistered) {
            persistedFileSyncRegistered = true;
            state.subscribe((path) => {
                if (path !== '*') return;

                Object.entries(staticFileStatePaths).forEach(([containerId, statePath]) => {
                    const container = document.getElementById(containerId);
                    const zone = container && initializedZones.get(container);
                    if (zone) zone.setUploaded(state.get(statePath));
                });
            });
        }
    }

    return {
        createFileUploadZone,
        validateFile,
        formatFileSize,
        getFileIcon,
        setDocumentMetadata(documents) {
            if (!Array.isArray(documents)) return;

            documents.forEach((document) => {
                if (document.id && document.original_name) {
                    documentNames.set(String(document.id), document.original_name);
                }
            });
        },
        getFileName(fileId) {
            return documentNames.get(String(fileId)) || '';
        },
        uploadPendingFiles,
        clearPendingFiles,
        initAll,
        getPendingFile,
        onUpload(listener) {
            uploadListeners.add(listener);
            return () => uploadListeners.delete(listener);
        }
    };
})();