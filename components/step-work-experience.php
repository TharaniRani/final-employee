<?php
/**
 * Step 9: Work Experience
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 8;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-work-experience" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-work-experience-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-work-experience-title">Work Experience</h2>
        <p class="step-description">Enter your past work experience details. Add multiple entries as needed.</p>
    </div>

    <div class="step-content">
        <form id="work-experience-form" novalidate>
            <input type="hidden" name="step" value="work-experience">

            <div class="existing-data-notice" id="experience-existing-notice" style="display: none;" role="status" aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Your work experience from the employee record is displayed below. Please verify and add any missing experience.</div>
                </div>
            </div>

            <div class="form-group">
                <fieldset class="verification-group" id="not-applicable-group">
                    <legend class="form-label">Not Applicable</legend>
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="experience_not_applicable" value="Yes" id="experience-na">
                            <span>Not Applicable (Select if you've started your career and continued in any of The Madras Seva Sadan institutions)</span>
                        </label>
                    </div>
                    <div class="form-error" id="experience-na-error" role="alert"></div>
                </fieldset>
            </div>

            <div class="conditional-field" id="experience-details-section" style="display: block;">
                <div class="repeater-section" id="experience-repeater">
                    <div class="repeater-header">
                        <h3 class="repeater-title">Work Experience Entries</h3>
                        <button type="button" class="btn btn-sm repeater-add-btn" id="add-experience" data-repeater="experience">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add Work Experience
                        </button>
                    </div>
                    <div class="repeater-items" id="experience-items">
                        <!-- Experience cards added dynamically -->
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4" role="alert">
                <svg class="alert-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="alert-content">
                    <div class="alert-title">Work Experience Guidelines</div>
                    <div class="alert-message">
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>Add each previous employment as a separate entry</li>
                            <li>Experience letter upload is optional but recommended</li>
                            <li>Pay slip upload is optional</li>
                            <li>Select "Not Applicable" only if your entire career has been within Madras Seva Sadan institutions</li>
                            <li>Month format: MM/YYYY (e.g., 01/2020 for January 2020)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step <?php echo $stepIndex + 1; ?> of <?php echo count($wizardStepKeys); ?></span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="work-experience-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="work-experience-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>

<template id="experience-template">
    <div class="repeater-item experience-card experience-item" data-id="">
        <div class="experience-card-header">
            <h5 class="experience-card-title">Work Experience <span class="item-number"></span></h5>
            <div class="repeater-item-actions">
                <button type="button" class="btn btn-sm repeater-item-remove" aria-label="Remove experience">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Remove
                </button>
            </div>
        </div>
        <div class="experience-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Company/Institution Name <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="experience_company[]" placeholder="Enter company/institution name" autocomplete="organization" required>
                    <div class="form-error" role="alert"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Role/Designation <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="experience_designation[]" placeholder="Enter your role/designation" autocomplete="off" required>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Starting Month <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="month" class="form-input" name="experience_start_month[]" required aria-describedby="start-month-help">
                    <div class="form-help" id="start-month-help">Month and year you started (MM/YYYY)</div>
                    <div class="form-error" role="alert"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Leaving Month <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="month" class="form-input" name="experience_end_month[]" required aria-describedby="end-month-help">
                    <div class="form-help" id="end-month-help">Month and year you left (MM/YYYY)</div>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Experience Details <span class="required-indicator" aria-hidden="true">*</span></label>
                <textarea class="form-textarea" name="experience_details[]" placeholder="Describe your role, responsibilities, achievements..." rows="3" required></textarea>
                <div class="form-help">Provide details of your role, responsibilities, and achievements</div>
                <div class="form-error" role="alert"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Experience Letter (Optional)</label>
                    <div class="file-upload experience-letter-upload">
                        <input type="file" class="file-upload-input" name="experience_letter[]" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="exp-letter-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload experience letter">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <p class="file-upload-text">Upload Experience Letter</p>
                            <p class="file-upload-hint">PDF, JPG, PNG up to 5MB (optional)</p>
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
                    <div class="form-help" id="exp-letter-help">Upload experience letter if available (PDF, JPG, PNG - max 5MB)</div>
                    <div class="form-error" role="alert"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Pay Slip</label>
                    <div class="file-upload experience-payslip-upload">
                        <input type="file" class="file-upload-input" name="experience_payslip[]" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="payslip-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload pay slip">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <p class="file-upload-text">Upload Pay Slip</p>
                            <p class="file-upload-hint">PDF, JPG, PNG up to 5MB (optional)</p>
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
                    <div class="form-help" id="payslip-help">Upload latest pay slip (PDF, JPG, PNG - max 5MB)</div>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>
        </div>
    </div>
</template>