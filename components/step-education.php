<?php
/**
 * Step 7: Educational Qualifications
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 6;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-education" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-education-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-education-title">Educational Qualifications</h2>
        <p class="step-description">Add your educational qualifications starting from the highest. You can add multiple qualifications.</p>
    </div>

    <div class="step-content">
        <form id="education-form" novalidate>
            <input type="hidden" name="step" value="education">

            <div class="existing-data-notice" id="education-existing-notice" style="display: none;" role="status" aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Your educational qualifications from the employee record are displayed below. Please verify and add any missing qualifications.</div>
                </div>
            </div>

            <div class="repeater-section" id="education-repeater">
                <div class="repeater-header">
                    <h3 class="repeater-title">Qualifications</h3>
                    <button type="button" class="btn btn-sm repeater-add-btn" id="add-education" data-repeater="education">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Qualification
                    </button>
                </div>
                <div class="repeater-items" id="education-items">
                    <!-- Education cards added dynamically -->
                </div>
                <div class="form-error" id="education-error" role="alert"></div>
            </div>

            <div class="alert alert-info mt-4" role="alert">
                <svg class="alert-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="alert-content">
                    <div class="alert-title">Important Notes</div>
                    <div class="alert-message">
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li>Add qualifications starting from the highest (e.g., PhD, Master's, Bachelor's, Diploma, 12th, 10th)</li>
                            <li>Upload provisional or completion certificates only (no mark sheets)</li>
                            <li>For "Others (Grade 4 only)" qualification type, additional fields will appear</li>
                            <li>Each qualification entry is independent with its own certificate upload</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step 7 of 11</span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="education-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="education-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>

<template id="education-template">
    <div class="repeater-item education-card education-item" data-id="">
        <div class="education-card-header">
            <h5 class="education-card-title">Qualification <span class="item-number"></span></h5>
            <div class="repeater-item-actions">
                <button type="button" class="btn btn-sm repeater-item-remove" aria-label="Remove qualification">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Remove
                </button>
            </div>
        </div>
        <div class="education-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Highest Qualification Type <span class="required-indicator" aria-hidden="true">*</span></label>
                    <select class="form-select education-qualification-type" name="education_qualification_type[]" required aria-describedby="qual-type-help">
                        <option value="">Select qualification type</option>
                    </select>
                    <div class="form-help" id="qual-type-help">Select the type of qualification</div>
                    <div class="form-error" role="alert"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Highest Qualification Name <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="education_qualification_name[]" placeholder="e.g., M.E., B.E., B.Sc., Diploma" autocomplete="off" required aria-describedby="qual-name-help">
                    <div class="form-help" id="qual-name-help">Enter the specific qualification name</div>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>

            <div class="conditional-field education-others-fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Skilled In <span class="required-indicator" aria-hidden="true">*</span></label>
                        <input type="text" class="form-input" name="education_skilled_in[]" placeholder="e.g., Driving, Carpentry, Welding" autocomplete="off" aria-describedby="skilled-help">
                        <div class="form-help" id="skilled-help">Enter skills (required for Grade 4 / Others)</div>
                        <div class="form-error" role="alert"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Special Talents</label>
                        <input type="text" class="form-input" name="education_special_talents[]" placeholder="Enter any special talents" autocomplete="off" aria-describedby="talents-help">
                        <div class="form-help" id="talents-help">Any special talents or abilities</div>
                        <div class="form-error" role="alert"></div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Institution <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="education_institution[]" placeholder="Enter institution name" autocomplete="organization" required aria-describedby="inst-help">
                    <div class="form-help" id="inst-help">Name of the institution/university</div>
                    <div class="form-error" role="alert"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Year of Completion <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="education_year[]" placeholder="YYYY" maxlength="4" pattern="[0-9]{4}" inputmode="numeric" required aria-describedby="year-help">
                    <div class="form-help" id="year-help">Year of completion (4 digits, e.g., 2020)</div>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Provisional/Completion Certificate <span class="required-indicator" aria-hidden="true">*</span></label>
                <div class="file-upload education-certificate-upload">
                    <input type="file" class="file-upload-input" name="education_certificate[]" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="cert-help">
                    <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload certificate">
                        <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <p class="file-upload-text">Upload Certificate</p>
                        <p class="file-upload-hint">PDF, JPG, PNG up to 5MB (provisional or completion certificate only)</p>
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
                <div class="form-help" id="cert-help">Upload provisional or completion certificate only (PDF, JPG, PNG - max 5MB)</div>
                <div class="form-error" role="alert"></div>
            </div>
        </div>
    </div>
</template>