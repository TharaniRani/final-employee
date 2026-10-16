<?php
/**
 * Step 8: Teacher Details and Other Documents
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 7;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-teacher-documents" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-teacher-documents-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-teacher-documents-title">Teacher Details & Other Documents</h2>
        <p class="step-description">If you are a teacher, enter your B.Ed details. Upload other applicable documents.</p>
    </div>

    <div class="step-content">
        <form id="teacher-documents-form" novalidate>
            <input type="hidden" name="step" value="teacher-documents">

            <div class="existing-data-notice" id="teacher-existing-notice" style="display: none;" role="status" aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Some fields are pre-filled from your employee record. Please verify each field.</div>
                </div>
            </div>

            <h3 class="h5 mb-4">Teacher Details</h3>

            <div class="form-group">
                <fieldset class="verification-group" id="is-teacher-group">
                    <legend class="form-label">Are you a teacher in one of the Madras Seva Sadan schools? <span class="required-indicator" aria-hidden="true">*</span></legend>
                    <div class="radio-group radio-inline" role="radiogroup" aria-label="Teacher status">
                        <label class="radio-item">
                            <input type="radio" name="is_teacher" value="Yes" id="teacher-yes" required>
                            <span>Yes</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="is_teacher" value="No" id="teacher-no" required>
                            <span>No</span>
                        </label>
                    </div>
                    <div class="form-error" id="is-teacher-error" role="alert"></div>
                </fieldset>
            </div>

            <div class="conditional-field" id="teacher-details-section" style="display: none;">
                <h4 class="h6 mb-3">B.Ed Details</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label for="bed-status" class="form-label">B.Ed Status <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="verification-group" id="bed-status-group">
                            <span class="verification-value" id="bed-status-display"></span>
                            <select class="form-select" id="bed-status" name="bed_status" required aria-describedby="bed-status-help">
                                <option value="">Select B.Ed status</option>
                                <option value="Joined">Joined</option>
                                <option value="Completed">Completed</option>
                            </select>
                            <div class="form-help" id="bed-status-help">Select whether you have joined or completed B.Ed</div>
                            <div class="form-error" id="bed-status-error" role="alert"></div>
                            <div class="verification-options" id="bed-status-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="bed_status_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="bed_status_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bed-institution" class="form-label">Institution <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="verification-group" id="bed-institution-group">
                            <span class="verification-value" id="bed-institution-display"></span>
                            <input type="text" class="form-input" id="bed-institution" name="bed_institution" placeholder="Enter institution name" autocomplete="organization" required aria-describedby="bed-inst-help">
                            <div class="form-help" id="bed-inst-help">Name of the institution where you pursued/completed B.Ed</div>
                            <div class="form-error" id="bed-institution-error" role="alert"></div>
                            <div class="verification-options" id="bed-institution-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="bed_institution_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="bed_institution_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">

                    <div class="conditional-field" id="bed-year-field" style="display: none;">
                        <div class="form-group" id="bed-proposed-year-group">
                            <label for="bed-proposed-year" class="form-label">Year of Proposed Graduation <span class="required-indicator" aria-hidden="true">*</span></label>
                            <div class="verification-group" id="bed-proposed-year-group-inner">
                                <span class="verification-value" id="bed-proposed-year-display"></span>
                                <input type="number" class="form-input" id="bed-proposed-year" name="bed_proposed_year" placeholder="YYYY" min="<?php echo date('Y'); ?>" max="<?php echo date('Y') + 10; ?>" step="1" required aria-describedby="bed-proposed-year-help">
                                <div class="form-help" id="bed-proposed-year-help">Expected year of graduation (if B.Ed status is Joined)</div>
                                <div class="form-error" id="bed-proposed-year-error" role="alert"></div>
                            </div>
                        </div>

                        <div class="form-group" id="bed-graduation-year-group">
                            <label for="bed-graduation-year" class="form-label">Year of Graduation <span class="required-indicator" aria-hidden="true">*</span></label>
                            <div class="verification-group" id="bed-graduation-year-group-inner">
                                <span class="verification-value" id="bed-graduation-year-display"></span>
                                <input type="number" class="form-input" id="bed-graduation-year" name="bed_graduation_year" placeholder="YYYY" min="1950" max="<?php echo date('Y'); ?>" step="1" required aria-describedby="bed-graduation-year-help">
                                <div class="form-help" id="bed-graduation-year-help">Year of graduation (if B.Ed status is Completed)</div>
                                <div class="form-error" id="bed-graduation-year-error" role="alert"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">B.Ed Document Upload <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="file-upload" id="bed-upload">
                            <input type="file" class="file-upload-input" id="bed-file" name="bed_file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="bed-upload-help">
                            <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload B.Ed document">
                                <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <p class="file-upload-text">Upload B.Ed Document</p>
                                <p class="file-upload-hint">PDF, JPG, PNG up to 5MB</p>
                            </div>
                            <div class="file-upload-info" style="display: none;">
                                <div class="file-upload-name">
                                    <svg class="file-upload-name-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    <span class="file-upload-name-text"></span>
                                </div>
                                <div class="file-upload-actions">
                                    <button type="button" class="btn btn-sm file-upload-remove">Remove</button>
                                    <button type="button" class="btn btn-sm file-upload-replace">Replace</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-help" id="bed-upload-help">Upload joined confirmation (fees paid, application submitted, enrollment) or completion certificate</div>
                        <div class="form-error" id="bed-upload-error" role="alert"></div>
                    </div>
                </div>
            </div>

            <hr class="divider my-5">

            <h3 class="h5 mb-4">Other Documents</h3>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Driving License Upload</label>
                    <div class="file-upload" id="driving-license-upload">
                        <input type="file" class="file-upload-input" id="driving-license-file" name="driving_license_file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="dl-upload-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload driving license">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                                <path d="M8 21h8M12 17v4"></path>
                            </svg>
                            <p class="file-upload-text">Upload Driving License</p>
                            <p class="file-upload-hint">PDF, JPG, PNG up to 5MB</p>
                        </div>
                        <div class="file-upload-info" style="display: none;">
                            <div class="file-upload-name">
                                <svg class="file-upload-name-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                                    <path d="M8 21h8M12 17v4"></path>
                                </svg>
                                <span class="file-upload-name-text"></span>
                            </div>
                            <div class="file-upload-actions">
                                <button type="button" class="btn btn-sm file-upload-remove">Remove</button>
                                <button type="button" class="btn btn-sm file-upload-replace">Replace</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-help" id="dl-upload-help">Upload copy of your driving license (optional)</div>
                    <div class="form-error" id="driving-license-error" role="alert"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Police Verification Upload</label>
                    <div class="file-upload" id="police-verification-upload">
                        <input type="file" class="file-upload-input" id="police-verification-file" name="police_verification_file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="police-verification-upload-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload Police Verification">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <p class="file-upload-text">Upload Police Verification</p>
                            <p class="file-upload-hint">PDF, JPG, PNG up to 5MB</p>
                        </div>
                        <div class="file-upload-info" style="display: none;">
                            <div class="file-upload-name">
                                <svg class="file-upload-name-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <span class="file-upload-name-text"></span>
                            </div>
                            <div class="file-upload-actions">
                                <button type="button" class="btn btn-sm file-upload-remove">Remove</button>
                                <button type="button" class="btn btn-sm file-upload-replace">Replace</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-help" id="police-verification-upload-help">Upload copy of Police Verification (optional)</div>
                    <div class="form-error" id="police-verification-error" role="alert"></div>
                </div>
            </div>

            <div class="repeater-section" id="other-documents-repeater">
                <div class="repeater-header">
                    <h4 class="repeater-title">Other Documents</h4>
                    <button type="button" class="btn btn-sm repeater-add-btn" id="add-other-document" data-repeater="other-documents">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Document
                    </button>
                </div>
                <div class="repeater-items" id="other-documents-items">
                    <!-- Other document items added dynamically -->
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step 8 of 11</span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="teacher-documents-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="teacher-documents-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>

<template id="other-document-template">
    <div class="repeater-item other-document-card other-document-item" data-id="">
        <div class="other-document-card-header">
            <h5 class="other-document-card-title">Document <span class="item-number"></span></h5>
            <div class="repeater-item-actions">
                <button type="button" class="btn btn-sm repeater-item-remove" aria-label="Remove document">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Remove
                </button>
            </div>
        </div>
        <div class="other-document-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Document Title <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="other_doc_title[]" placeholder="Enter document title" autocomplete="off" required>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Document Upload <span class="required-indicator" aria-hidden="true">*</span></label>
                <div class="file-upload other-doc-upload">
                    <input type="file" class="file-upload-input" name="other_doc_file[]" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="other-doc-help">
                    <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload document">
                        <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        <p class="file-upload-text">Upload Document</p>
                        <p class="file-upload-hint">PDF, JPG, PNG up to 5MB</p>
                    </div>
                    <div class="file-upload-info" style="display: none;">
                        <div class="file-upload-name">
                            <svg class="file-upload-name-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <span class="file-upload-name-text"></span>
                        </div>
                        <div class="file-upload-actions">
                            <button type="button" class="btn btn-sm file-upload-remove">Remove</button>
                            <button type="button" class="btn btn-sm file-upload-replace">Replace</button>
                        </div>
                    </div>
                </div>
                <div class="form-help" id="other-doc-help">Upload the document (PDF, JPG, PNG - max 5MB)</div>
                <div class="form-error" role="alert"></div>
            </div>
        </div>
    </div>
</template>