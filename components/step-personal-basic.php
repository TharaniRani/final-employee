<?php
/**
 * Step 1: Personal Basic Information
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 0;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-personal-basic"
    data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-personal-basic-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-personal-basic-title">Personal Basic Information</h2>
        <p class="step-description">Enter your basic personal details as per your KYC documents.</p>
    </div>

    <div class="step-content">
        <form id="personal-basic-form" novalidate>
            <input type="hidden" name="step" value="personal-basic">

            <div class="existing-data-notice" id="personal-existing-notice" style="display: none;" role="status"
                aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Some fields below are pre-filled from your employee record. Please
                        verify each field and mark as Correct or Wrong.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="full-name" class="form-label">
                        Full Name (as per Aadhaar) <span class="required-indicator" aria-hidden="true">*</span>
                    </label>
                    <div class="verification-group" id="full-name-group">
                        <span class="verification-value" id="full-name-display"></span>
                        <input type="text" class="form-input" id="full-name" name="full_name"
                            placeholder="Enter full name as per Aadhaar" autocomplete="name" required
                            aria-describedby="full-name-help">
                        <div class="form-help" id="full-name-help">Full name as per your Aadhaar card</div>
                        <div class="form-error" id="full-name-error" role="alert"></div>
                        <div class="verification-options" id="full-name-verification" style="display: none;">
                            <label class="verification-option">
                                <input type="radio" name="full_name_verification" value="correct" required>
                                <span>Correct</span>
                            </label>
                            <label class="verification-option">
                                <input type="radio" name="full_name_verification" value="wrong">
                                <span>Wrong</span>
                            </label>
                        </div>
                        <div class="verification-correction" id="full-name-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Name</label>
                            <input type="text" class="form-input" id="full-name-corrected" name="full_name_corrected"
                                placeholder="Enter corrected name" autocomplete="name">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        Email <span class="required-indicator" aria-hidden="true">*</span>
                    </label>
                    <div class="verification-group" id="email-group">
                        <span class="verification-value" id="email-display"></span>
                        <input type="email" class="form-input" id="email" name="email" placeholder="Enter email address"
                            autocomplete="email" required aria-describedby="email-help">
                        <div class="form-help" id="email-help">Your official email address</div>
                        <div class="form-error" id="email-error" role="alert"></div>
                        <div class="verification-options" id="email-verification" style="display: none;">
                            <label class="verification-option">
                                <input type="radio" name="email_verification" value="correct" required>
                                <span>Correct</span>
                            </label>
                            <label class="verification-option">
                                <input type="radio" name="email_verification" value="wrong">
                                <span>Wrong</span>
                            </label>
                        </div>
                        <div class="verification-correction" id="email-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Email</label>
                            <input type="email" class="form-input" id="email-corrected" name="email_corrected"
                                placeholder="Enter corrected email" autocomplete="email">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="mobile-number-display" class="form-label">
                    Mobile Number <span class="required-indicator" aria-hidden="true">*</span>
                </label>
                <div class="verification-group" id="mobile-group">
                    <span class="verification-value" id="mobile-display"></span>
                    <input type="tel" class="form-input" id="mobile-number-display" name="mobile_number"
                        placeholder="Enter mobile number" autocomplete="tel" maxlength="10" inputmode="numeric"
                        pattern="[6-9]\d{9}" required aria-describedby="mobile-help" readonly>
                    <div class="form-help" id="mobile-help">This number was verified by OTP and is locked here. To use
                        another number, return to mobile verification.</div>
                    <div class="form-error" id="mobile-error" role="alert"></div>
                    <div class="verification-options" id="mobile-verification" style="display: none;">
                        <label class="verification-option">
                            <input type="radio" name="mobile_verification" value="correct" required>
                            <span>Correct</span>
                        </label>
                        <label class="verification-option">
                            <input type="radio" name="mobile_verification" value="wrong">
                            <span>Wrong</span>
                        </label>
                    </div>
                    <div class="verification-correction" id="mobile-correction" style="display: none;">
                        <label class="verification-correction-label">Corrected Mobile</label>
                        <input type="tel" class="form-input" id="mobile-corrected" name="mobile_corrected"
                            placeholder="Enter corrected mobile" autocomplete="tel" maxlength="10" inputmode="numeric"
                            pattern="[6-9]\d{9}">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="form-label" id="alumni-label">Are you an alumnus of any Madras Seva Sadan school? <span
                        class="required-indicator" aria-hidden="true">*</span></div>
                <fieldset class="verification-group" id="alumni-group" aria-labelledby="alumni-label">
                    <div class="radio-group radio-inline" role="radiogroup" aria-label="Alumni status">
                        <label class="radio-item">
                            <input type="radio" name="is_alumni" value="Yes" id="alumni-yes" required>
                            <span>Yes</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="is_alumni" value="No" id="alumni-no" required>
                            <span>No</span>
                        </label>
                    </div>
                    <div class="form-help">Select Yes if you studied at a Madras Seva Sadan school.</div>
                    <div class="form-error" id="alumni-error" role="alert"></div>

                    <div class="conditional-field" id="alumni-school-field" style="display: none;">
                        <label for="alumni-school" class="form-label mt-3">Alumni School <span
                                class="required-indicator" aria-hidden="true">*</span></label>
                        <select class="form-select" id="alumni-school" name="alumni_school"
                            aria-describedby="alumni-school-help">
                            <option value="">Select school</option>
                        </select>
                        <div class="form-help" id="alumni-school-help">Select the Madras Seva Sadan school you attended
                        </div>
                        <div class="form-error" id="alumni-school-error" role="alert"></div>
                    </div>
                </fieldset>
            </div>

            <div id="resume-upload-field">
                <div class="form-group">
                    <label class="form-label">Resume Upload <span class="required-indicator"
                            aria-hidden="true">*</span></label>
                    <div class="file-upload" id="resume-upload">
                        <input type="file" class="file-upload-input" id="resume-file" name="resume_file"
                            accept=".pdf,.jpg,.jpeg,.png" aria-describedby="resume-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload resume">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <p class="file-upload-text">Upload Resume</p>
                            <p class="file-upload-hint">PDF, JPG, PNG up to 5MB</p>
                        </div>
                        <div class="file-upload-info" style="display: none;">
                            <div class="file-upload-name">
                                <svg class="file-upload-name-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
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
                    <div class="form-help" id="resume-help">Upload your current resume (PDF, JPG, PNG - max 5MB)</div>
                    <div class="form-error" id="resume-error" role="alert"></div>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step 1 of 11</span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="personal-basic-prev" disabled>Previous</button>
            <button type="button" class="btn btn-primary" id="personal-basic-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>