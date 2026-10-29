<?php
/**
 * Review and Submit
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 9;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-review" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-review-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-review-title">Review & Submit</h2>
        <p class="step-description">Review all your information before submitting. Click Edit on any section to make changes.</p>
    </div>

    <div class="step-content">
        <form id="review-form" novalidate>
            <input type="hidden" name="step" value="review">

            <div id="review-summary">
                <!-- Review sections populated by JavaScript -->
            </div>

            <div class="form-group mt-5">
                <fieldset class="verification-group" id="declaration-group">
                    <legend class="form-label">Declaration</legend>
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="declaration" value="Yes" id="declaration-checkbox" required>
                            <span>I hereby declare that all the information provided in this form is true, complete, and accurate to the best of my knowledge. I understand that any false information may lead to disciplinary action.</span>
                        </label>
                    </div>
                    <div class="form-error" id="declaration-checkbox-error" role="alert"></div>
                </fieldset>
            </div>

            <div class="form-group mt-4">
                <fieldset class="verification-group" id="terms-group">
                    <legend class="form-label">Terms and Conditions</legend>
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="terms_accepted" value="Yes" id="terms-checkbox" required>
                            <span>I have read and agree to the <button type="button" class="btn btn-ghost btn-sm p-0 terms-link" id="terms-link" aria-label="View Terms and Conditions">Terms and Conditions</button>.</span>
                        </label>
                    </div>
                    <div class="form-error" id="terms-checkbox-error" role="alert"></div>
                </fieldset>
            </div>

            <div id="submission-error" class="alert alert-error" style="display: none;" role="alert" aria-live="assertive">
                <svg class="alert-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div class="alert-content">
                    <div class="alert-title" id="submission-error-title">Submission could not be completed</div>
                    <p class="alert-message" id="submission-error-message"></p>
                    <ul id="submission-error-list" class="alert-message" style="margin: 0.5rem 0 0; padding-left: 1.5rem;" hidden></ul>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step <?php echo $stepIndex + 1; ?> of <?php echo count($wizardStepKeys); ?></span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="review-prev">Previous</button>
            <button type="button" class="btn btn-primary btn-lg" id="review-submit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Submit Form
            </button>
        </div>
    </div>
</div>

<div class="wizard-step" id="step-success" data-step-index="10" role="tabpanel" aria-labelledby="step-success-title" hidden>
    <div class="step-header">
        <h2 class="step-title" id="step-success-title">Submission Successful</h2>
    </div>

    <div class="step-content">
        <div class="success-screen" id="success-screen">
            <div class="success-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2 class="success-title">Form Submitted Successfully!</h2>
            <p class="success-message">Your employee information has been submitted and is waiting for HR approval. Your original employee records will not change until HR approves the requested updates.</p>

            <div class="success-details" id="success-details">
                <div class="success-detail">
                    <svg class="success-detail-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span class="success-detail-label">Reference Number</span>
                    <span class="success-detail-value" id="success-ref">-</span>
                </div>
                <div class="success-detail">
                    <svg class="success-detail-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span class="success-detail-label">Submission Date</span>
                    <span class="success-detail-value" id="success-date">-</span>
                </div>
                <div class="success-detail">
                    <svg class="success-detail-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span class="success-detail-label">Status</span>
                    <span class="success-detail-value" id="success-status">Waiting for HR approval</span>
                </div>
            </div>

            <div class="d-grid gap-2 mt-5" style="max-width: 300px; margin-left: auto; margin-right: auto;">
                <button type="button" class="btn btn-primary btn-lg" id="download-acknowledgement">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    Download Acknowledgement
                </button>
                <button type="button" class="btn btn-secondary" id="start-new-form">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Start New Form
                </button>
            </div>
        </div>
    </div>

    <div class="step-footer">
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="success-close">Close</button>
        </div>
    </div>
</div>

<!-- File Preview Modal -->
<div class="modal-overlay" id="file-preview-modal" role="dialog" aria-modal="true" aria-labelledby="file-preview-title" hidden>
    <div class="hrms-modal modal-lg">
        <div class="modal-header">
            <h2 class="modal-title" id="file-preview-title">Document Preview</h2>
            <button class="modal-close" id="file-preview-close" aria-label="Close preview">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="file-preview-body">
            <div class="text-center py-4">
                <div class="spinner mx-auto"></div>
                <p class="mt-2 text-secondary">Loading document...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="file-preview-close-btn">Close</button>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal-overlay" id="terms-modal" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title" hidden>
    <div class="hrms-modal">
        <div class="modal-header">
            <h2 class="modal-title" id="terms-modal-title">Terms and Conditions</h2>
            <button class="modal-close" id="terms-modal-close" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="terms-modal-body">
            <div class="terms-content">
                <h4 class="mb-3">Terms and Conditions</h4>
                <div class="terms-sections">
                    <div class="terms-section mb-4">
                        <h5 class="mb-2">1. Information Accuracy</h5>
                        <p class="text-secondary">I confirm that the information and documents submitted through this employee onboarding form are accurate and complete to the best of my knowledge.</p>
                    </div>
                    <div class="terms-section mb-4">
                        <h5 class="mb-2">2. Document Verification</h5>
                        <p class="text-secondary">I understand that the organization may verify the information and documents provided during the employee onboarding process.</p>
                    </div>
                    <div class="terms-section mb-4">
                        <h5 class="mb-2">3. Data Processing</h5>
                        <p class="text-secondary">I understand that my submitted information may be processed and maintained by the organization for legitimate employment, administrative, and compliance purposes, subject to applicable laws and policies.</p>
                    </div>
                    <div class="terms-section mb-4">
                        <h5 class="mb-2">4. Responsibility</h5>
                        <p class="text-secondary">I agree to provide accurate information and notify the organization if any submitted information requires correction.</p>
                    </div>
                    <div class="terms-section mb-4">
                        <h5 class="mb-2">5. Acceptance</h5>
                        <p class="text-secondary">By accepting these Terms and Conditions, I acknowledge that I have read and understood the above statements.</p>
                    </div>
                </div>
                <div class="alert alert-info mt-4" role="alert">
                    <svg class="alert-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div class="alert-content">
                        <div class="alert-title">Note</div>
                        <div class="alert-message">These are placeholder terms for development purposes and must be reviewed and approved by the organization before production use.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="terms-modal-cancel">Close</button>
            <button type="button" class="btn btn-primary" id="terms-modal-agree">I Agree</button>
        </div>
    </div>
</div>