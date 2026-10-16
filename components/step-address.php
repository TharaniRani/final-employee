<?php
/**
 * Step 2: Employee Identification and Residence Details
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 1;
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-address" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-address-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-address-title">Employee Identification & Residence Details</h2>
        <p class="step-description">Enter your current and permanent address details as per Aadhaar.</p>
    </div>

    <div class="step-content">
        <form id="address-form" novalidate>
            <input type="hidden" name="step" value="address">

            <div class="existing-data-notice" id="address-existing-notice" style="display: none;" role="status" aria-live="polite">
                <svg class="existing-data-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16v-4M12 8h.01"></path>
                </svg>
                <div class="existing-data-content">
                    <div class="existing-data-title">Existing Information Found</div>
                    <div class="existing-data-text">Some address fields are pre-filled from your employee record. Please verify each field.</div>
                </div>
            </div>

            <h3 class="h5 mb-4">Current Residence Address</h3>

            <div class="form-row-3">
                <div class="form-group">
                    <label for="curr-address-line1" class="form-label">Address Line 1 <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="curr-address-line1-group">
                        <span class="verification-value" id="curr-address-line1-display"></span>
                        <input type="text" class="form-input" id="curr-address-line1" name="curr_address_line1" placeholder="House/Flat No., Building, Street" autocomplete="address-line1" required aria-describedby="curr-address-line1-help">
                        <div class="form-help" id="curr-address-line1-help">House/Flat number, building name, street</div>
                        <div class="form-error" id="curr-address-line1-error" role="alert"></div>
                        <div class="verification-options" id="curr-address-line1-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="curr_address_line1_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="curr_address_line1_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="curr-address-line1-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Address Line 1</label>
                            <input type="text" class="form-input" id="curr-address-line1-corrected" name="curr_address_line1_corrected" placeholder="Enter corrected address" autocomplete="address-line1">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="curr-address-line2" class="form-label">Address Line 2</label>
                    <div class="verification-group" id="curr-address-line2-group">
                        <span class="verification-value" id="curr-address-line2-display"></span>
                        <input type="text" class="form-input" id="curr-address-line2" name="curr_address_line2" placeholder="Area/Locality/Sector" autocomplete="address-line2" aria-describedby="curr-address-line2-help">
                        <div class="form-help" id="curr-address-line2-help">Area, locality, sector (optional)</div>
                        <div class="form-error" id="curr-address-line2-error" role="alert"></div>
                        <div class="verification-options" id="curr-address-line2-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="curr_address_line2_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="curr_address_line2_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="curr-address-line2-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Address Line 2</label>
                            <input type="text" class="form-input" id="curr-address-line2-corrected" name="curr_address_line2_corrected" placeholder="Enter corrected address" autocomplete="address-line2">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="curr-address-line3" class="form-label">Address Line 3</label>
                    <div class="verification-group" id="curr-address-line3-group">
                        <span class="verification-value" id="curr-address-line3-display"></span>
                        <input type="text" class="form-input" id="curr-address-line3" name="curr_address_line3" placeholder="Landmark/Nearby reference" autocomplete="address-line3" aria-describedby="curr-address-line3-help">
                        <div class="form-help" id="curr-address-line3-help">Landmark or nearby reference (optional)</div>
                        <div class="form-error" id="curr-address-line3-error" role="alert"></div>
                        <div class="verification-options" id="curr-address-line3-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="curr_address_line3_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="curr_address_line3_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="curr-address-line3-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected Address Line 3</label>
                            <input type="text" class="form-input" id="curr-address-line3-corrected" name="curr_address_line3_corrected" placeholder="Enter corrected address" autocomplete="address-line3">
                        </div>
                    </div>
                </div>
            </div>


            <div class="form-row-4">
                <div class="form-group">
                    <label for="curr-pincode" class="form-label">PIN Code <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="curr-pincode-group">
                        <span class="verification-value" id="curr-pincode-display"></span>
                        <input type="text" class="form-input" id="curr-pincode" name="curr_pincode" placeholder="6-digit PIN" autocomplete="postal-code" maxlength="6" inputmode="numeric" pattern="\d{6}" required aria-describedby="curr-pincode-help">
                        <div class="form-help" id="curr-pincode-help">6-digit PIN code</div>
                        <div class="form-error" id="curr-pincode-error" role="alert"></div>
                        <div class="verification-options" id="curr-pincode-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="curr_pincode_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="curr_pincode_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="curr-pincode-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected PIN Code</label>
                            <input type="text" class="form-input" id="curr-pincode-corrected" name="curr_pincode_corrected" placeholder="Enter corrected PIN" autocomplete="postal-code" maxlength="6" inputmode="numeric" pattern="\d{6}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="curr-city" class="form-label">City <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="curr-city-group">
                        <span class="verification-value" id="curr-city-display"></span>
                        <input type="text" class="form-input" id="curr-city" name="curr_city" placeholder="City" autocomplete="address-level2" required aria-describedby="curr-city-help">
                        <div class="form-help" id="curr-city-help">City name</div>
                        <div class="form-error" id="curr-city-error" role="alert"></div>
                        <div class="verification-options" id="curr-city-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="curr_city_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="curr_city_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="curr-city-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected City</label>
                            <input type="text" class="form-input" id="curr-city-corrected" name="curr_city_corrected" placeholder="Enter corrected city" autocomplete="address-level2">
                        </div>
                    </div>
                </div>

               <div class="form-group">
                    <label for="curr-state" class="form-label">State <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="curr-state-group">
                        <span class="verification-value" id="curr-state-display"></span>
                        <select class="form-select" id="curr-state" name="curr_state" required aria-describedby="curr-state-help">
                            <option value="">Select state</option>
                        </select>
                        <div class="form-help" id="curr-state-help">Select state</div>
                        <div class="form-error" id="curr-state-error" role="alert"></div>
                        <div class="verification-options" id="curr-state-verification" style="display: none;">
                            <label class="verification-option"><input type="radio" name="curr_state_verification" value="correct" required><span>Correct</span></label>
                            <label class="verification-option"><input type="radio" name="curr_state_verification" value="wrong"><span>Wrong</span></label>
                        </div>
                        <div class="verification-correction" id="curr-state-correction" style="display: none;">
                            <label class="verification-correction-label">Corrected State</label>
                            <select class="form-select" id="curr-state-corrected" name="curr_state_corrected">
                                <option value="">Select state</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="curr-country" class="form-label">Country <span class="required-indicator" aria-hidden="true">*</span></label>
                    <div class="verification-group" id="curr-country-group">
                        <span class="verification-value" id="curr-country-display"></span>
                        <select class="form-select" id="curr-country" name="curr_country" required aria-describedby="curr-country-help">
                            <option value="India">India</option>
                        </select>
                        <div class="form-help" id="curr-country-help">Country</div>
                        <div class="form-error" id="curr-country-error" role="alert"></div>
                    </div>
                </div>
            </div>

            <hr class="divider my-5">

            <div class="form-group">
                <div class="form-label" id="same-address-label">Is current residence address the same as permanent address? <span class="required-indicator" aria-hidden="true">*</span></div>
                <fieldset class="verification-group" id="same-address-group" aria-labelledby="same-address-label">
                    <div class="radio-group radio-inline" role="radiogroup" aria-label="Same as permanent address">
                        <label class="radio-item">
                            <input type="radio" name="same_as_permanent" value="Yes" id="same-yes" required>
                            <span>Yes</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="same_as_permanent" value="No" id="same-no" required>
                            <span>No</span>
                        </label>
                    </div>
                    <div class="form-help">Choose Yes if both addresses are the same. Choose No to enter your permanent address separately.</div>
                    <div class="form-error" id="same-address-error" role="alert"></div>
                </fieldset>
            </div>

            <div class="conditional-field" id="permanent-address-section" style="display: none;">
                <h3 class="h5 mb-4">Permanent Address (as per Aadhaar)</h3>

                <div class="form-row-3">
                    <div class="form-group">
                        <label for="perm-address-line1" class="form-label">Address Line 1 <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="verification-group" id="perm-address-line1-group">
                            <span class="verification-value" id="perm-address-line1-display"></span>
                            <input type="text" class="form-input" id="perm-address-line1" name="perm_address_line1" placeholder="House/Flat No., Building, Street as per Aadhaar" autocomplete="address-line1" required aria-describedby="perm-address-line1-help">
                            <div class="form-help" id="perm-address-line1-help">House/Flat number, building name, street as per Aadhaar</div>
                            <div class="form-error" id="perm-address-line1-error" role="alert"></div>
                            <div class="verification-options" id="perm-address-line1-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="perm_address_line1_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="perm_address_line1_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                            <div class="verification-correction" id="perm-address-line1-correction" style="display: none;">
                                <label class="verification-correction-label">Corrected Address Line 1</label>
                                <input type="text" class="form-input" id="perm-address-line1-corrected" name="perm_address_line1_corrected" placeholder="Enter corrected address" autocomplete="address-line1">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="perm-address-line2" class="form-label">Address Line 2</label>
                        <div class="verification-group" id="perm-address-line2-group">
                            <span class="verification-value" id="perm-address-line2-display"></span>
                            <input type="text" class="form-input" id="perm-address-line2" name="perm_address_line2" placeholder="Area/Locality/Sector as per Aadhaar" autocomplete="address-line2" aria-describedby="perm-address-line2-help">
                            <div class="form-help" id="perm-address-line2-help">Area, locality, sector as per Aadhaar (optional)</div>
                            <div class="form-error" id="perm-address-line2-error" role="alert"></div>
                            <div class="verification-options" id="perm-address-line2-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="perm_address_line2_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="perm_address_line2_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                            <div class="verification-correction" id="perm-address-line2-correction" style="display: none;">
                                <label class="verification-correction-label">Corrected Address Line 2</label>
                                <input type="text" class="form-input" id="perm-address-line2-corrected" name="perm_address_line2_corrected" placeholder="Enter corrected address" autocomplete="address-line2">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="perm-address-line3" class="form-label">Address Line 3</label>
                        <div class="verification-group" id="perm-address-line3-group">
                            <span class="verification-value" id="perm-address-line3-display"></span>
                            <input type="text" class="form-input" id="perm-address-line3" name="perm_address_line3" placeholder="Landmark/Nearby reference as per Aadhaar" autocomplete="address-line3" aria-describedby="perm-address-line3-help">
                            <div class="form-help" id="perm-address-line3-help">Landmark or nearby reference as per Aadhaar (optional)</div>
                            <div class="form-error" id="perm-address-line3-error" role="alert"></div>
                            <div class="verification-options" id="perm-address-line3-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="perm_address_line3_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="perm_address_line3_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                            <div class="verification-correction" id="perm-address-line3-correction" style="display: none;">
                                <label class="verification-correction-label">Corrected Address Line 3</label>
                                <input type="text" class="form-input" id="perm-address-line3-corrected" name="perm_address_line3_corrected" placeholder="Enter corrected address" autocomplete="address-line3">
                            </div>
                        </div>
                    </div>
                </div>


                <div class="form-row-4">
                    <div class="form-group">
                        <label for="perm-pincode" class="form-label">PIN Code <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="verification-group" id="perm-pincode-group">
                            <span class="verification-value" id="perm-pincode-display"></span>
                            <input type="text" class="form-input" id="perm-pincode" name="perm_pincode" placeholder="6-digit PIN as per Aadhaar" autocomplete="postal-code" maxlength="6" inputmode="numeric" pattern="\d{6}" required aria-describedby="perm-pincode-help">
                            <div class="form-help" id="perm-pincode-help">6-digit PIN code as per Aadhaar</div>
                            <div class="form-error" id="perm-pincode-error" role="alert"></div>
                            <div class="verification-options" id="perm-pincode-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="perm_pincode_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="perm_pincode_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                            <div class="verification-correction" id="perm-pincode-correction" style="display: none;">
                                <label class="verification-correction-label">Corrected PIN Code</label>
                                <input type="text" class="form-input" id="perm-pincode-corrected" name="perm_pincode_corrected" placeholder="Enter corrected PIN" autocomplete="postal-code" maxlength="6" inputmode="numeric" pattern="\d{6}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="perm-city" class="form-label">City <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="verification-group" id="perm-city-group">
                            <span class="verification-value" id="perm-city-display"></span>
                            <input type="text" class="form-input" id="perm-city" name="perm_city" placeholder="City as per Aadhaar" autocomplete="address-level2" required aria-describedby="perm-city-help">
                            <div class="form-help" id="perm-city-help">City name as per Aadhaar</div>
                            <div class="form-error" id="perm-city-error" role="alert"></div>
                            <div class="verification-options" id="perm-city-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="perm_city_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="perm_city_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                            <div class="verification-correction" id="perm-city-correction" style="display: none;">
                                <label class="verification-correction-label">Corrected City</label>
                                <input type="text" class="form-input" id="perm-city-corrected" name="perm_city_corrected" placeholder="Enter corrected city" autocomplete="address-level2">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="perm-state" class="form-label">State <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="verification-group" id="perm-state-group">
                            <span class="verification-value" id="perm-state-display"></span>
                            <select class="form-select" id="perm-state" name="perm_state" required aria-describedby="perm-state-help">
                                <option value="">Select state</option>
                            </select>
                            <div class="form-help" id="perm-state-help">Select state as per Aadhaar</div>
                            <div class="form-error" id="perm-state-error" role="alert"></div>
                            <div class="verification-options" id="perm-state-verification" style="display: none;">
                                <label class="verification-option"><input type="radio" name="perm_state_verification" value="correct" required><span>Correct</span></label>
                                <label class="verification-option"><input type="radio" name="perm_state_verification" value="wrong"><span>Wrong</span></label>
                            </div>
                            <div class="verification-correction" id="perm-state-correction" style="display: none;">
                                <label class="verification-correction-label">Corrected State</label>
                                <select class="form-select" id="perm-state-corrected" name="perm_state_corrected">
                                    <option value="">Select state</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="perm-country" class="form-label">Country <span class="required-indicator" aria-hidden="true">*</span></label>
                        <div class="verification-group" id="perm-country-group">
                            <span class="verification-value" id="perm-country-display"></span>
                            <select class="form-select" id="perm-country" name="perm_country" required aria-describedby="perm-country-help">
                                <option value="">Select country</option>
                            </select>
                            <div class="form-help" id="perm-country-help">Country</div>
                            <div class="form-error" id="perm-country-error" role="alert"></div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step 2 of 11</span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="address-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="address-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>