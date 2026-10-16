<?php
/**
 * Step 6: Referral Information
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 5;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-referral" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-referral-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-referral-title">Referral Information</h2>
        <p class="step-description">Enter referral details and family member information.</p>
    </div>

    <div class="step-content">
        <form id="referral-form" novalidate>
            <input type="hidden" name="step" value="referral">

            <div class="existing-data-notice" id="referral-existing-notice" style="display: none;" role="status" aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Some fields are pre-filled from your employee record. Please verify each field.</div>
                </div>
            </div>

            <h3 class="h5 mb-4">Referred by Employee Details</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="referred-by-name" class="form-label">Full Name of Referring Employee</label>
                    <div class="verification-group" id="referred-by-name-group">
                        <span class="verification-value" id="referred-by-name-display"></span>
                        <input type="text" class="form-input" id="referred-by-name" name="referred_by_name" placeholder="Enter referring employee full name" autocomplete="name" aria-describedby="referred-by-name-help">
                        <div class="form-help" id="referred-by-name-help">Full name of the employee who referred you</div>
                        <div class="form-error" id="referred-by-name-error" role="alert"></div>
                        <div class="verification-options" id="referred-by-name-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="referred_by_name_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="referred_by_name_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="referred-by-name-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Name</label>
                            <input type="text" class="form-input" id="referred-by-name-corrected" name="referred_by_name_corrected" placeholder="Enter corrected name" autocomplete="name">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="referral-source" class="form-label">Referral Source</label>
                    <div class="verification-group" id="referral-source-group">
                        <span class="verification-value" id="referral-source-display"></span>
                        <select class="form-select" id="referral-source" name="referral_source" aria-describedby="referral-source-help">
                            <option value="">Select source</option>
                            <option value="LinkedIn">LinkedIn</option>
                            <option value="Internal Referal">Internal Referal</option>
                            <option value="External Referal">External Referal</option>
                            <option value="Covenant">Covenant</option>
                            <option value="TransHR">TransHR</option>
                            <option value="Tree">Tree</option>
                            <option value="Applied via email">Applied via email</option>
                            <option value="Indeed">Indeed</option>
                            <option value="Outreach">Outreach</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Job Hai">Job Hai</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="TSOW">TSOW</option>
                            <option value="Plug n Play - Applied for another role">Plug n Play - Applied for another role</option>
                        </select>
                        <div class="form-help" id="referral-source-help">How did you learn about this position?</div>
                        <div class="form-error" id="referral-source-error" role="alert"></div>
                        <div class="verification-options" id="referral-source-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="referral_source_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="referral_source_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="divider my-5">

            <h3 class="h5 mb-4">Family Members Working in the Organisation</h3>

            <div class="form-group">
                <fieldset class="verification-group" id="family-working-group">
                    <legend class="form-label">Do you have family members working in the organisation? <span class="required-indicator" aria-hidden="true">*</span></legend>
                    <div class="radio-group radio-inline" role="radiogroup" aria-label="Family members working">
                        <label class="radio-item">
                            <input type="radio" name="family_working" value="Yes" id="family-working-yes" required>
                            <span>Yes</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="family_working" value="No" id="family-working-no" required>
                            <span>No</span>
                        </label>
                    </div>
                    <div class="form-error" id="family-working-error" role="alert"></div>
                </fieldset>
            </div>

            <div class="conditional-field" id="family-working-details" style="display: none;">
                <div class="repeater-section" id="family-working-repeater">
                    <div class="repeater-header">
                        <h4 class="repeater-title">Family Members Working</h4>
                        <button type="button" class="btn btn-sm repeater-add-btn" id="add-family-working" data-repeater="family-working">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add Family Member
                        </button>
                    </div>
                    <div class="repeater-items" id="family-working-items">
                        <!-- Items added dynamically -->
                    </div>
                </div>
            </div>

            <hr class="divider my-5">

            <h3 class="h5 mb-4">Family Members Studying in the Institution</h3>

            <div class="form-group">
                <fieldset class="verification-group" id="family-studying-group">
                    <legend class="form-label">Do you have family members studying in the institution? <span class="required-indicator" aria-hidden="true">*</span></legend>
                    <div class="radio-group radio-inline" role="radiogroup" aria-label="Family members studying">
                        <label class="radio-item">
                            <input type="radio" name="family_studying" value="Yes" id="family-studying-yes" required>
                            <span>Yes</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="family_studying" value="No" id="family-studying-no" required>
                            <span>No</span>
                        </label>
                    </div>
                    <div class="form-error" id="family-studying-error" role="alert"></div>
                </fieldset>
            </div>

            <div class="conditional-field" id="family-studying-details" style="display: none;">
                <div class="repeater-section" id="family-studying-repeater">
                    <div class="repeater-header">
                        <h4 class="repeater-title">Family Members Studying</h4>
                        <button type="button" class="btn btn-sm repeater-add-btn" id="add-family-studying" data-repeater="family-studying">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add Family Member
                        </button>
                    </div>
                    <div class="repeater-items" id="family-studying-items">
                        <!-- Items added dynamically -->
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step 6 of 11</span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="referral-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="referral-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>

<template id="family-working-template">
    <div class="repeater-item family-working-item" data-id="">
        <div class="repeater-item-header">
            <h5 class="repeater-item-title">Family Member <span class="item-number"></span></h5>
            <div class="repeater-item-actions">
                <button type="button" class="btn btn-sm repeater-item-remove" aria-label="Remove family member">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Remove
                </button>
            </div>
        </div>
        <div class="repeater-item-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="family_working_name[]" placeholder="Enter full name" autocomplete="name" required>
                    <div class="form-error" role="alert"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Unit Name <span class="required-indicator" aria-hidden="true">*</span></label>
                    <select class="form-select" name="family_working_unit[]" required>
                        <option value="">Select unit</option>
                    </select>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Designation <span class="required-indicator" aria-hidden="true">*</span></label>
                <input type="text" class="form-input" name="family_working_designation[]" placeholder="Enter designation" autocomplete="off" required>
                <div class="form-error" role="alert"></div>
            </div>
        </div>
    </div>
</template>

<template id="family-studying-template">
    <div class="repeater-item family-studying-item" data-id="">
        <div class="repeater-item-header">
            <h5 class="repeater-item-title">Family Member <span class="item-number"></span></h5>
            <div class="repeater-item-actions">
                <button type="button" class="btn btn-sm repeater-item-remove" aria-label="Remove family member">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Remove
                </button>
            </div>
        </div>
        <div class="repeater-item-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required-indicator" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" name="family_studying_name[]" placeholder="Enter full name" autocomplete="name" required>
                    <div class="form-error" role="alert"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">School Name <span class="required-indicator" aria-hidden="true">*</span></label>
                    <select class="form-select" name="family_studying_school[]" required>
                        <option value="">Select school</option>
                    </select>
                    <div class="form-error" role="alert"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Class Studying In <span class="required-indicator" aria-hidden="true">*</span></label>
                <input type="text" class="form-input" name="family_studying_class[]" placeholder="Enter class (e.g., Grade 5)" autocomplete="off" required>
                <div class="form-error" role="alert"></div>
            </div>
        </div>
    </div>
</template>