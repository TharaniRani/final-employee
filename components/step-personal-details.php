<?php
/**
 * Step 4: Employee Personal Details
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 3;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-personal-details"
    data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-personal-details-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-personal-details-title">Employee Personal Details</h2>
        <p class="step-description">Enter your personal details, upload photograph and signature.</p>
    </div>

    <div class="step-content">
        <form id="personal-details-form" novalidate>
            <input type="hidden" name="step" value="personal-details">

            <div class="existing-data-notice" id="personal-details-existing-notice" style="display: none;" role="status"
                aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Some fields are pre-filled from your employee record. Please verify
                        each field.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Recent Photograph <span class="required-indicator"
                            aria-hidden="true">*</span></label>
                    <div class="file-upload" id="photo-upload">
                        <input type="file" class="file-upload-input" id="photo-file" name="photo_file"
                            accept="image/jpeg,image/png" aria-describedby="photo-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload photograph">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="8" r="3"></circle>
                                <path
                                    d="M12 11v8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z">
                                </path>
                            </svg>
                            <p class="file-upload-text">Upload Photograph</p>
                            <p class="file-upload-hint">JPG, PNG up to 5MB (2x2 inches, less than 6 months old)</p>
                        </div>
                        <div class="file-upload-info" style="display: none;">
                            <div class="file-upload-name">
                                <svg class="file-upload-name-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="8" r="3"></circle>
                                    <path
                                        d="M12 11v8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z">
                                    </path>
                                </svg>
                                <span class="file-upload-name-text"></span>
                            </div>
                            <div class="file-upload-actions">
                                <button type="button" class="btn btn-sm file-upload-remove">Remove</button>
                                <button type="button" class="btn btn-sm file-upload-replace">Replace</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-help" id="photo-help">Recent photograph (less than 6 months old), 2x2 inches,
                        JPG/PNG max 5MB</div>
                    <div class="form-error" id="photo-error" role="alert"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Signature <span class="required-indicator"
                            aria-hidden="true">*</span></label>
                    <div class="file-upload" id="signature-upload">
                        <input type="file" class="file-upload-input" id="signature-file" name="signature_file"
                            accept="image/jpeg,image/png" aria-describedby="signature-help">
                        <div class="file-upload-zone" tabindex="0" role="button" aria-label="Upload signature">
                            <svg class="file-upload-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5">
                                <path d="M17 21l-5-5-5 5"></path>
                                <path d="M12 3v18"></path>
                            </svg>
                            <p class="file-upload-text">Upload Signature</p>
                            <p class="file-upload-hint">JPG, PNG up to 5MB (signed on white paper with blue pen)</p>
                        </div>
                        <div class="file-upload-info" style="display: none;">
                            <div class="file-upload-name">
                                <svg class="file-upload-name-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M17 21l-5-5-5 5"></path>
                                    <path d="M12 3v18"></path>
                                </svg>
                                <span class="file-upload-name-text"></span>
                            </div>
                            <div class="file-upload-actions">
                                <button type="button" class="btn btn-sm file-upload-remove">Remove</button>
                                <button type="button" class="btn btn-sm file-upload-replace">Replace</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-help" id="signature-help">Sign on white paper with blue pen, scan and upload
                        (JPG/PNG max 5MB)</div>
                    <div class="form-error" id="signature-error" role="alert"></div>
                </div>
            </div>

            <hr class="divider my-5">

            <div class="form-row">
                <div class="form-group">
                    <label for="date-of-birth" class="form-label">Date of Birth (as per Aadhaar) <span
                            class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="dob-group">
                        <span class="verification-value" id="dob-display"></span>
                        <input type="date" class="form-input" id="date-of-birth" name="date_of_birth" required
                            aria-describedby="dob-help" max="<?php echo date('Y-m-d'); ?>">
                        <div class="form-help" id="dob-help">Date of birth as per Aadhaar card</div>
                        <div class="form-error" id="dob-error" role="alert"></div>
                        <div class="verification-options" id="dob-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="dob_verification"
                                    value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="dob_verification"
                                    value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="dob-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Date of Birth</label>
                            <input type="date" class="form-input" id="dob-corrected" name="dob_corrected"
                                max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="gender" class="form-label">Gender <span class="required-indicator"
                            aria-hidden="true">*</span></label>
                    <div class="verification-group" id="gender-group">
                        <span class="verification-value" id="gender-display"></span>
                        <select class="form-select" id="gender" name="gender" required aria-describedby="gender-help">
                            <option value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <div class="form-help" id="gender-help">Select your gender</div>
                        <div class="form-error" id="gender-error" role="alert"></div>
                        <div class="verification-options" id="gender-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="gender_verification"
                                    value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="gender_verification"
                                    value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="gender-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Gender</label>
                            <select class="form-select" id="gender-corrected" name="gender_corrected">
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="marital-status" class="form-label">Marital Status <span class="required-indicator"
                            aria-hidden="true">*</span></label>
                    <div class="verification-group" id="marital-group">
                        <span class="verification-value" id="marital-display"></span>
                        <select class="form-select" id="marital-status" name="marital_status" required
                            aria-describedby="marital-help">
                            <option value="">Select marital status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                        <div class="form-help" id="marital-help">Select your marital status</div>
                        <div class="form-error" id="marital-error" role="alert"></div>
                        <div class="verification-options" id="marital-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="marital_status_verification"
                                    value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="marital_status_verification"
                                    value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="marital-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Marital Status</label>
                            <select class="form-select" id="marital-corrected" name="marital_status_corrected">
                                <option value="">Select marital status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group conditional-field" id="spouse-field" style="display: none;">
                    <label for="spouse-name" class="form-label">Spouse Full Name (as per Aadhaar) <span
                            class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="spouse-group">
                        <span class="verification-value" id="spouse-display"></span>
                        <input type="text" class="form-input" id="spouse-name" name="spouse_name"
                            placeholder="Enter spouse full name as per Aadhaar" autocomplete="name"
                            aria-describedby="spouse-help">
                        <div class="form-help" id="spouse-help">Spouse full name as per Aadhaar (required if married)
                        </div>
                        <div class="form-error" id="spouse-error" role="alert"></div>
                        <div class="verification-options" id="spouse-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="spouse_name_verification"
                                    value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="spouse_name_verification"
                                    value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="spouse-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Spouse Name</label>
                            <input type="text" class="form-input" id="spouse-corrected" name="spouse_name_corrected"
                                placeholder="Enter corrected spouse name" autocomplete="name">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="father-name" class="form-label">Father's Full Name <span class="required-indicator"
                            aria-hidden="true">*</span></label>
                    <div class="verification-group" id="father-group">
                        <span class="verification-value" id="father-display"></span>
                        <input type="text" class="form-input" id="father-name" name="father_name"
                            placeholder="Enter father's full name" autocomplete="name" required
                            aria-describedby="father-help">
                        <div class="form-help" id="father-help">Father's full name</div>
                        <div class="form-error" id="father-error" role="alert"></div>
                        <div class="verification-options" id="father-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="father_name_verification"
                                    value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="father_name_verification"
                                    value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="father-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Father's Name</label>
                            <input type="text" class="form-input" id="father-corrected" name="father_name_corrected"
                                placeholder="Enter corrected father's name" autocomplete="name">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="mother-name" class="form-label">Mother's Full Name <span class="required-indicator"
                            aria-hidden="true">*</span></label>
                    <div class="verification-group" id="mother-group">
                        <span class="verification-value" id="mother-display"></span>
                        <input type="text" class="form-input" id="mother-name" name="mother_name"
                            placeholder="Enter mother's full name" autocomplete="name" required
                            aria-describedby="mother-help">
                        <div class="form-help" id="mother-help">Mother's full name</div>
                        <div class="form-error" id="mother-error" role="alert"></div>
                        <div class="verification-options" id="mother-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="mother_name_verification"
                                    value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="mother_name_verification"
                                    value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="mother-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Mother's Name</label>
                            <input type="text" class="form-input" id="mother-corrected" name="mother_name_corrected"
                                placeholder="Enter corrected mother's name" autocomplete="name">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="blood-group" class="form-label">Blood Group <span class="required-indicator"
                        aria-hidden="true">*</span></label>
                <div class="verification-group" id="blood-group-group">
                    <span class="verification-value" id="blood-group-display"></span>
                    <select class="form-select" id="blood-group" name="blood_group" required
                        aria-describedby="blood-group-help">
                        <option value="">Select blood group</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="A1+">A1+</option>
                        <option value="A1-">A1-</option>
                        <option value="A1B+">A1B+</option>
                        <option value="A2B+">A2B+</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                    <div class="form-help" id="blood-group-help">Select your blood group</div>
                    <div class="form-error" id="blood-group-error" role="alert"></div>
                    <div class="verification-options" id="blood-group-verification" style="display: none;">
                        <label class="verification-option"><input type="radio" name="blood_group_verification"
                                value="correct" required><span>Correct</span></label>
                        <label class="verification-option"><input type="radio" name="blood_group_verification"
                                value="wrong"><span>Wrong</span></label>
                    </div>
                    <div class="verification-correction" id="blood-group-correction" style="display: none;">
                        <label class="verification-correction-label">Corrected Blood Group</label>
                        <select class="form-select" id="blood-group-corrected" name="blood_group_corrected">
                            <option value="">Select blood group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step <?php echo $stepIndex + 1; ?> of <?php echo count($wizardStepKeys); ?></span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="personal-details-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="personal-details-next">
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