<?php
/**
 * Step 5: Next of Kin and Emergency Contact
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 4;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-next-of-kin" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-next-of-kin-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-next-of-kin-title">Next of Kin & Emergency Contact</h2>
        <p class="step-description">Enter your Next of Kin details and emergency contact information.</p>
    </div>

    <div class="step-content">
        <form id="next-of-kin-form" novalidate>
            <input type="hidden" name="step" value="next-of-kin">

            <div class="existing-data-notice" id="nok-existing-notice" style="display: none;" role="status" aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Some fields are pre-filled from your employee record. Please verify each field.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="nok-name-source" class="form-label">Next of Kin (NOK) Name Selection <span class="required-indicator" aria-hidden="true">*</span></label>
                <div class="verification-group" id="nok-name-source-group">
                    <span class="verification-value" id="nok-name-source-display"></span>
                    <div class="radio-group radio-inline" role="radiogroup" aria-label="NOK name source">
                        <label class="radio-item">
                            <input type="radio" name="nok_name_source" value="select" id="nok-source-select" required>
                            <span>Select from above</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="nok_name_source" value="manual" id="nok-source-manual" required>
                            <span>Enter manually</span>
                        </label>
                    </div>
                    <div class="form-error" id="nok-name-source-error" role="alert"></div>

                    <div class="conditional-field" id="nok-select-field" style="display: none;">
                        <label for="nok-select" class="form-label mt-3">Select Next of Kin <span class="required-indicator" aria-hidden="true">*</span></label>
                        <select class="form-select" id="nok-select" name="nok_select" aria-describedby="nok-select-help">
                            <option value="">Select from family members</option>
                        </select>
                        <div class="form-help" id="nok-select-help">Select a family member as your Next of Kin</div>
                        <div class="form-error" id="nok-select-error" role="alert"></div>
                    </div>

                    <div class="conditional-field" id="nok-manual-field" style="display: none;">
                        <label for="nok-manual-name" class="form-label mt-3">Next of Kin Full Name <span class="required-indicator" aria-hidden="true">*</span></label>
                        <input type="text" class="form-input" id="nok-manual-name" name="nok_manual_name" placeholder="Enter Next of Kin full name" autocomplete="name" aria-describedby="nok-manual-help">
                        <div class="form-help" id="nok-manual-help">Enter full name of your Next of Kin</div>
                        <div class="form-error" id="nok-manual-error" role="alert"></div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nok-contact" class="form-label">Next of Kin Contact No. <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="nok-contact-group">
                        <span class="verification-value" id="nok-contact-display"></span>
                        <input type="tel" class="form-input" id="nok-contact" name="nok_contact" placeholder="10-digit mobile number" autocomplete="tel" maxlength="10" inputmode="numeric" pattern="[6-9]\d{9}" required aria-describedby="nok-contact-help">
                        <div class="form-help" id="nok-contact-help">10-digit mobile number of your Next of Kin</div>
                        <div class="form-error" id="nok-contact-error" role="alert"></div>
                        <div class="verification-options" id="nok-contact-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="nok_contact_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="nok_contact_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="nok-contact-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Contact No.</label>
                            <input type="tel" class="form-input" id="nok-contact-corrected" name="nok_contact_corrected" placeholder="Enter corrected contact" autocomplete="tel" maxlength="10" inputmode="numeric" pattern="[6-9]\d{9}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-label" id="nok-emergency-label">Is this your emergency contact? <span class="required-indicator" aria-hidden="true">*</span></div>
                    <fieldset class="verification-group" id="nok-emergency-group" aria-labelledby="nok-emergency-label">
                        <div class="radio-group radio-inline" role="radiogroup" aria-label="Emergency contact confirmation">
                            <label class="radio-item">
                                <input type="radio" name="nok_is_emergency" value="Yes" id="emergency-yes" required>
                                <span>Yes</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="nok_is_emergency" value="No" id="emergency-no" required>
                                <span>No</span>
                            </label>
                        </div>
                        <div class="form-help">Choose No if you need to provide a different emergency contact number.</div>
                        <div class="form-error" id="nok-emergency-error" role="alert"></div>
                        <div class="conditional-field" id="emergency-contact-field" style="display: none;">
                            <label for="emergency-contact" class="form-label mt-3">Emergency Contact Number <span class="required-indicator" aria-hidden="true">*</span></label>
                            <input type="tel" class="form-input" id="emergency-contact" name="nok_emergency_contact" placeholder="10-digit mobile number" autocomplete="tel" maxlength="10" inputmode="numeric" pattern="[6-9]\d{9}" aria-describedby="emergency-contact-help">
                            <div class="form-help" id="emergency-contact-help">10-digit mobile number of your emergency contact</div>
                            <div class="form-error" id="emergency-contact-error" role="alert"></div>
                        </div>
                    </fieldset>
                </div>
            </div>

            <hr class="divider my-5">

            <h3 class="h5 mb-4">Next of Kin Address (as per Aadhaar)</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="nok-address-line1" class="form-label">Address Line 1 (as per Aadhaar) <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="nok-address-line1-group">
                        <span class="verification-value" id="nok-address-line1-display"></span>
                        <input type="text" class="form-input" id="nok-address-line1" name="nok_address_line1" placeholder="House/Flat No., Building, Street" autocomplete="address-line1" required aria-describedby="nok-address-line1-help">
                        <div class="form-help" id="nok-address-line1-help">House/Flat number, building name, street as per Aadhaar</div>
                        <div class="form-error" id="nok-address-line1-error" role="alert"></div>
                        <div class="verification-options" id="nok-address-line1-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="nok_address_line1_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="nok_address_line1_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="nok-address-line1-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Address Line 1</label>
                            <input type="text" class="form-input" id="nok-address-line1-corrected" name="nok_address_line1_corrected" placeholder="Enter corrected address" autocomplete="address-line1">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nok-address-line2" class="form-label">Address Line 2 (as per Aadhaar)</label>
                    <div class="verification-group" id="nok-address-line2-group">
                        <span class="verification-value" id="nok-address-line2-display"></span>
                        <input type="text" class="form-input" id="nok-address-line2" name="nok_address_line2" placeholder="Area/Locality/Sector" autocomplete="address-line2" aria-describedby="nok-address-line2-help">
                        <div class="form-help" id="nok-address-line2-help">Area, locality, sector (optional)</div>
                        <div class="form-error" id="nok-address-line2-error" role="alert"></div>
                        <div class="verification-options" id="nok-address-line2-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="nok_address_line2_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="nok_address_line2_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="nok-address-line2-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Address Line 2</label>
                            <input type="text" class="form-input" id="nok-address-line2-corrected" name="nok_address_line2_corrected" placeholder="Enter corrected address" autocomplete="address-line2">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nok-address-line3" class="form-label">Address Line 3 (as per Aadhaar)</label>
                    <div class="verification-group" id="nok-address-line3-group">
                        <span class="verification-value" id="nok-address-line3-display"></span>
                        <input type="text" class="form-input" id="nok-address-line3" name="nok_address_line3" placeholder="Landmark/Nearby reference" autocomplete="address-line3" aria-describedby="nok-address-line3-help">
                        <div class="form-help" id="nok-address-line3-help">Landmark or nearby reference (optional)</div>
                        <div class="form-error" id="nok-address-line3-error" role="alert"></div>
                        <div class="verification-options" id="nok-address-line3-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="nok_address_line3_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="nok_address_line3_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="nok-address-line3-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Address Line 3</label>
                            <input type="text" class="form-input" id="nok-address-line3-corrected" name="nok_address_line3_corrected" placeholder="Enter corrected address" autocomplete="address-line3">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row-4">

                <div class="form-group">
                    <label for="nok-pincode" class="form-label">PIN Code <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="nok-pincode-group">
                        <span class="verification-value" id="nok-pincode-display"></span>
                        <input type="text" class="form-input" id="nok-pincode" name="nok_pincode" placeholder="6-digit PIN" autocomplete="postal-code" maxlength="6" inputmode="numeric" pattern="\d{6}" required aria-describedby="nok-pincode-help">
                        <div class="form-help" id="nok-pincode-help">6-digit PIN code</div>
                        <div class="form-error" id="nok-pincode-error" role="alert"></div>
                        <div class="verification-options" id="nok-pincode-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="nok_pincode_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="nok_pincode_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="nok-pincode-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected PIN Code</label>
                            <input type="text" class="form-input" id="nok-pincode-corrected" name="nok_pincode_corrected" placeholder="Enter corrected PIN" autocomplete="postal-code" maxlength="6" inputmode="numeric" pattern="\d{6}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nok-city" class="form-label">City <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="nok-city-group">
                        <span class="verification-value" id="nok-city-display"></span>
                        <input type="text" class="form-input" id="nok-city" name="nok_city" placeholder="City" autocomplete="address-level2" required aria-describedby="nok-city-help">
                        <div class="form-help" id="nok-city-help">City name</div>
                        <div class="form-error" id="nok-city-error" role="alert"></div>
                        <div class="verification-options" id="nok-city-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="nok_city_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="nok_city_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="nok-city-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected City</label>
                            <input type="text" class="form-input" id="nok-city-corrected" name="nok_city_corrected" placeholder="Enter corrected city" autocomplete="address-level2">
                        </div>
                    </div>
                </div>

               <div class="form-group">
                    <label for="nok-state" class="form-label">State <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="nok-state-group">
                        <span class="verification-value" id="nok-state-display"></span>
                        <select class="form-select" id="nok-state" name="nok_state" required aria-describedby="nok-state-help">
                            <option value="">Select state</option>
                        </select>
                        <div class="form-help" id="nok-state-help">Select state</div>
                        <div class="form-error" id="nok-state-error" role="alert"></div>
                        <div class="verification-options" id="nok-state-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="nok_state_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="nok_state_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="nok-state-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected State</label>
                            <select class="form-select" id="nok-state-corrected" name="nok_state_corrected">
                                <option value="">Select state</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nok-country" class="form-label">Country <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="nok-country-group">
                        <span class="verification-value" id="nok-country-display"></span>
                        <select class="form-select" id="nok-country" name="nok_country" required aria-describedby="nok-country-help">
                            <option value="India">India</option>
                        </select>
                        <div class="form-help" id="nok-country-help">Country</div>
                        <div class="form-error" id="nok-country-error" role="alert"></div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step <?php echo $stepIndex + 1; ?> of <?php echo count($wizardStepKeys); ?></span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="next-of-kin-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="next-of-kin-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>