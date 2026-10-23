<?php
/**
 * Step 3: Aadhaar and PAN Details
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 2;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-aadhaar-pan" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-aadhaar-pan-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-aadhaar-pan-title">Aadhaar and PAN Details</h2>
        <p class="step-description">Enter your Aadhaar and PAN details and upload the scanned copies.</p>
    </div>

    <div class="step-content">
        <form id="aadhaar-pan-form" novalidate>
            <input type="hidden" name="step" value="aadhaar-pan">

            <div class="existing-data-notice" id="aadhaar-pan-existing-notice" style="display: none;" role="status" aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Some fields are pre-filled from your employee record. Please verify each field.</div>
                </div>
            </div>

            <h3 class="h5 mb-4">Aadhaar Details</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="aadhaar-name" class="form-label">Full Name (as per Aadhaar) <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="aadhaar-name-group">
                        <span class="verification-value" id="aadhaar-name-display"></span>
                        <input type="text" class="form-input" id="aadhaar-name" name="aadhaar_name" placeholder="Enter name as per Aadhaar" autocomplete="name" required aria-describedby="aadhaar-name-help">
                        <div class="form-help" id="aadhaar-name-help">Full name exactly as printed on your Aadhaar card</div>
                        <div class="form-error" id="aadhaar-name-error" role="alert"></div>
                        <div class="verification-options" id="aadhaar-name-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="aadhaar_name_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="aadhaar_name_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="aadhaar-name-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Name</label>
                            <input type="text" class="form-input" id="aadhaar-name-corrected" name="aadhaar_name_corrected" placeholder="Enter corrected name" autocomplete="name">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="aadhaar-number" class="form-label">Aadhaar Number <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="aadhaar-number-group">
                        <span class="verification-value" id="aadhaar-number-display"></span>
                        <input type="text" class="form-input" id="aadhaar-number" name="aadhaar_number" placeholder="12-digit Aadhaar number" autocomplete="off" maxlength="12" inputmode="numeric" pattern="\d{12}" required aria-describedby="aadhaar-number-help">
                        <div class="form-help" id="aadhaar-number-help">12-digit Aadhaar number (no spaces)</div>
                        <div class="form-error" id="aadhaar-number-error" role="alert"></div>
                        <div class="verification-options" id="aadhaar-number-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="aadhaar_number_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="aadhaar_number_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="aadhaar-number-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Aadhaar Number</label>
                            <input type="text" class="form-input" id="aadhaar-number-corrected" name="aadhaar_number_corrected" placeholder="Enter corrected Aadhaar number" autocomplete="off" maxlength="12" inputmode="numeric" pattern="\d{12}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Aadhaar Document Upload <span class="required-indicator" aria-hidden="true">*</span></label>
                <div class="file-upload" id="aadhaar-upload">
                    <input type="file" class="file-upload-input" id="aadhaar-file" name="aadhaar_file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="aadhaar-upload-help">
                    <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload Aadhaar card">
                        <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                            <path d="M8 21h8M12 17v4"></path>
                        </svg>
                        <p class="file-upload-text">Upload Aadhaar Card</p>
                        <p class="file-upload-hint">PDF, JPG, PNG up to 5MB (scanned copy)</p>
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
                <div class="form-help" id="aadhaar-upload-help">Upload scanned copy of your Aadhaar card (PDF, JPG, PNG - max 5MB)</div>
                <div class="form-error" id="aadhaar-upload-error" role="alert"></div>
            </div>

            <hr class="divider my-5">

            <h3 class="h5 mb-4">PAN Details</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="pan-number" class="form-label">PAN Card Number <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="pan-number-group">
                        <span class="verification-value" id="pan-number-display"></span>
                        <input type="text" class="form-input" id="pan-number" name="pan_number" placeholder="ABCDE1234F" autocomplete="off" maxlength="10" style="text-transform: uppercase;" required aria-describedby="pan-number-help">
                        <div class="form-help" id="pan-number-help">10-character PAN (e.g., ABCDE1234F)</div>
                        <div class="form-error" id="pan-number-error" role="alert"></div>
                        <div class="verification-options" id="pan-number-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="pan_number_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="pan_number_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="pan-number-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected PAN Number</label>
                            <input type="text" class="form-input" id="pan-number-corrected" name="pan_number_corrected" placeholder="Enter corrected PAN" autocomplete="off" maxlength="10" style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">PAN Document Upload <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="file-upload" id="pan-upload">
                        <input type="file" class="file-upload-input" id="pan-file" name="pan_file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="pan-upload-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload PAN card">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                                <path d="M8 21h8M12 17v4"></path>
                            </svg>
                            <p class="file-upload-text">Upload PAN Card</p>
                            <p class="file-upload-hint">PDF, JPG, PNG up to 5MB (scanned copy)</p>
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
                    <div class="form-help" id="pan-upload-help">Upload scanned copy of your PAN card (PDF, JPG, PNG - max 5MB)</div>
                    <div class="form-error" id="pan-upload-error" role="alert"></div>
                </div>
            </div>

            <div class="form-group">
                <fieldset class="verification-group" id="aadhaar-pan-linked-group">
                    <legend class="form-label">Is Aadhaar and PAN linked? <span class="required-indicator" aria-hidden="true">*</span></legend>
                    <div class="radio-group radio-inline" role="radiogroup" aria-label="Aadhaar PAN linked status">
                        <label class="radio-item">
                            <input type="radio" name="aadhaar_pan_linked" value="Yes" id="linked-yes" required>
                            <span>Yes</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="aadhaar_pan_linked" value="No" id="linked-no" required>
                            <span>No</span>
                        </label>
                    </div>
                    <div class="form-error" id="aadhaar-pan-linked-error" role="alert"></div>
                    <div class="verification-options" id="aadhaar-pan-linked-verification" style="display: none;">
                        <label class="verification-option"><input type="radio" name="aadhaar_pan_linked_verification" value="correct" required><span>Correct</span></label>
                        <label class="verification-option"><input type="radio" name="aadhaar_pan_linked_verification" value="wrong"><span>Wrong</span></label>
                    </div>
                </fieldset>
                <div class="form-help mt-3">
                    <a href="https://www.youtube.com/shorts/fbk_J1SDW7Q"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="help-link-card">
                        <svg class="help-link-icon" aria-hidden="true" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>

                        <span class="help-link-text">
                            <strong>How to check if Aadhaar &amp; PAN are linked?</strong>
                        </span>

                        <svg class="help-link-arrow" aria-hidden="true" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step 3 of 11</span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="aadhaar-pan-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="aadhaar-pan-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>

<style>
    .help-link-card {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        background: var(--color-bg-focus);
        border: 1px solid var(--color-border-focus);
        border-radius: 10px;
        color: #3730a3;
        text-decoration: none;
        font-size: 0.9rem;
        line-height: 1.4;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(79, 70, 229, 0.1);
    }
    .help-link-card:hover {
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        border-color: #a5b4fc;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        transform: translateY(-1px);
        color: #312e81;
        text-decoration: none;
    }
    .help-link-card:focus {
        outline: 2px solid #4f46e5;
        outline-offset: 2px;
    }
    .help-link-icon {
        flex-shrink: 0;
        width: 22px;
        height: 22px;
        color: #4f46e5;
    }
    .help-link-arrow {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        color: #4f46e5;
        margin-left: 4px;
        transition: transform 0.2s ease;
    }
    .help-link-card:hover .help-link-arrow {
        transform: translateX(3px);
    }
    .help-link-text strong {
        display: block;
        font-size: 14px;
        margin-bottom: 2px;
    }
    .help-link-text small {
        color: #5b57d4;
        font-weight: 500;
    }
</style>