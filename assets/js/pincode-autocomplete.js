/**
 * PIN Code Autocomplete Module
 * Automatically fills City, State, Country when PIN code is entered
 * Uses backend proxy to India Post API: https://api.postalpincode.in/pincode/{PINCODE}
 */
window.HRMS_PincodeAutocomplete = (function() {
    'use strict';

    // Cache for PIN code lookups
    const pincodeCache = new Map();
    let debounceTimers = new Map();

    // PIN code field configurations for all address sections
    const pincodeFields = [
        {
            pincodeId: 'curr-pincode',
            cityId: 'curr-city',
            stateId: 'curr-state',
            countryId: 'curr-country',
            pincodeCorrectedId: 'curr-pincode-corrected',
            cityCorrectedId: 'curr-city-corrected',
            stateCorrectedId: 'curr-state-corrected'
        },
        {
            pincodeId: 'perm-pincode',
            cityId: 'perm-city',
            stateId: 'perm-state',
            countryId: 'perm-country',
            pincodeCorrectedId: 'perm-pincode-corrected',
            cityCorrectedId: 'perm-city-corrected',
            stateCorrectedId: 'perm-state-corrected'
        },
        {
            pincodeId: 'nok-pincode',
            cityId: 'nok-city',
            stateId: 'nok-state',
            countryId: 'nok-country',
            pincodeCorrectedId: 'nok-pincode-corrected',
            cityCorrectedId: 'nok-city-corrected',
            stateCorrectedId: 'nok-state-corrected'
        }
    ];

    function init() {
        console.log('[PincodeAutocomplete] Initializing...');
        pincodeFields.forEach(fieldConfig => {
            const pincodeInput = document.getElementById(fieldConfig.pincodeId);
            if (pincodeInput) {
                setupPincodeListener(fieldConfig);
            }
        });
    }

    function setupPincodeListener(config) {
        const pincodeInput = document.getElementById(config.pincodeId);
        const pincodeCorrectedInput = document.getElementById(config.pincodeCorrectedId);
        
        if (!pincodeInput) return;

        // Handle both original and corrected PIN code inputs
        [pincodeInput, pincodeCorrectedInput].forEach(input => {
            if (!input) return;
            
            input.addEventListener('input', (e) => {
                handlePincodeInput(e.target, config);
            });

            input.addEventListener('blur', (e) => {
                handlePincodeBlur(e.target, config);
            });

            input.addEventListener('paste', (e) => {
                // Handle paste - wait for input to update
                setTimeout(() => handlePincodeInput(e.target, config), 50);
            });
        });
    }

    function handlePincodeInput(input, config) {
        const pincode = input.value.replace(/\D/g, '').slice(0, 6);
        
        // Clear any existing debounce timer for this input
        const timerKey = input.id;
        if (debounceTimers.has(timerKey)) {
            clearTimeout(debounceTimers.get(timerKey));
        }

        // Only lookup if we have exactly 6 digits
        if (pincode.length === 6) {
            debounceTimers.set(timerKey, setTimeout(() => {
                lookupPincode(pincode, config, input);
            }, 300));
        }
    }

    function handlePincodeBlur(input, config) {
        const pincode = input.value.replace(/\D/g, '').slice(0, 6);
        if (pincode.length === 6) {
            // Immediate lookup on blur if not already cached
            if (!pincodeCache.has(pincode)) {
                lookupPincode(pincode, config, input);
            }
        }
    }

    async function lookupPincode(pincode, fieldConfig, sourceInput) {
        // Check cache first
        if (pincodeCache.has(pincode)) {
            const cached = pincodeCache.get(pincode);
            fillAddressFields(fieldConfig, cached, sourceInput);
            return;
        }

        const hrmsConfig = window.HRMS_CONFIG;
        if (!hrmsConfig) {
            console.error('[PincodeAutocomplete] HRMS_CONFIG not available');
            return;
        }

        console.log('[PincodeAutocomplete] Looking up pincode:', pincode);

        // Show loading state on city field
        const cityInput = document.getElementById(fieldConfig.cityId);
        if (cityInput) {
            cityInput.classList.add('pincode-loading');
        }

        try {
            // Use the backend API endpoint
            const response = await fetch(`${hrmsConfig.basePath}/backend-integration/api/pincode-lookup.php?pincode=${encodeURIComponent(pincode)}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                console.log('[PincodeAutocomplete] Found data:', data.data);
                // Cache the result
                pincodeCache.set(pincode, data.data);
                
                // Fill the fields
                fillAddressFields(fieldConfig, data.data, sourceInput);
            } else {
                console.log('[PincodeAutocomplete] PIN code not found:', pincode, data);
                // Optionally show error to user
                showError(fieldConfig.cityId, data.message || 'PIN code not found');
            }
        } catch (error) {
            console.error('[PincodeAutocomplete] Lookup failed:', error);
        } finally {
            if (cityInput) {
                cityInput.classList.remove('pincode-loading');
            }
        }
    }

    function fillAddressFields(config, data, sourceInput) {
        const { city, state, country } = data;
        
        // Determine which set of fields to update based on source input
        const isCorrected = sourceInput && sourceInput.id && sourceInput.id.includes('-corrected');
        
        const cityId = isCorrected ? config.cityCorrectedId : config.cityId;
        const stateId = isCorrected ? config.stateCorrectedId : config.stateId;
        const countryId = config.countryId; // Country same for both

        console.log('[PincodeAutocomplete] Filling fields:', { cityId, stateId, countryId, city, state, country });

        // Ensure state/country dropdowns are populated before filling
        ensureStatesPopulated(stateId);
        ensureCountriesPopulated(countryId);

        // Fill City (text input)
        const cityInput = document.getElementById(cityId);
        if (cityInput && city) {
            if (!cityInput.value || cityInput.dataset.autoFilled === 'true') {
                cityInput.value = city;
                cityInput.dataset.autoFilled = 'true';
                triggerChange(cityInput);
                console.log('[PincodeAutocomplete] City filled:', city);
            }
        }

        // Fill State (select dropdown)
        const stateInput = document.getElementById(stateId);
        if (stateInput && state) {
            if (!stateInput.value || stateInput.dataset.autoFilled === 'true') {
                let matched = false;
                for (let i = 0; i < stateInput.options.length; i++) {
                    if (stateInput.options[i].text.toLowerCase() === state.toLowerCase() || 
                        stateInput.options[i].value.toLowerCase() === state.toLowerCase()) {
                        stateInput.selectedIndex = i;
                        stateInput.dataset.autoFilled = 'true';
                        triggerChange(stateInput);
                        matched = true;
                        console.log('[PincodeAutocomplete] State filled:', state);
                        break;
                    }
                }
                if (!matched) {
                    console.log('[PincodeAutocomplete] State not found in dropdown:', state);
                }
            }
        }

        // Fill Country (select dropdown)
        const countryInput = document.getElementById(countryId);
        if (countryInput && country) {
            if (!countryInput.value || countryInput.dataset.autoFilled === 'true') {
                let matched = false;
                if (countryInput.tagName === 'SELECT') {
                    for (let i = 0; i < countryInput.options.length; i++) {
                        if (countryInput.options[i].text.toLowerCase() === country.toLowerCase() ||
                            countryInput.options[i].value.toLowerCase() === country.toLowerCase()) {
                            countryInput.selectedIndex = i;
                            countryInput.dataset.autoFilled = 'true';
                            triggerChange(countryInput);
                            matched = true;
                            console.log('[PincodeAutocomplete] Country filled:', country);
                            break;
                        }
                    }
                }
                if (!matched) {
                    console.log('[PincodeAutocomplete] Country not found in dropdown:', country);
                }
            }
        }
    }

    function ensureStatesPopulated(stateId) {
        const stateSelect = document.getElementById(stateId);
        if (!stateSelect || stateSelect.options.length > 1) return;
        
        const hrmsConfig = window.HRMS_CONFIG;
        if (hrmsConfig && hrmsConfig.states) {
            hrmsConfig.states.forEach(state => {
                const option = document.createElement('option');
                option.value = state;
                option.textContent = state;
                stateSelect.appendChild(option);
            });
        }
    }

    function ensureCountriesPopulated(countryId) {
        const countrySelect = document.getElementById(countryId);
        if (!countrySelect || countrySelect.options.length > 1) return;
        
        const hrmsConfig = window.HRMS_CONFIG;
        if (hrmsConfig && hrmsConfig.countries) {
            hrmsConfig.countries.forEach(country => {
                const option = document.createElement('option');
                option.value = country;
                option.textContent = country;
                countrySelect.appendChild(option);
            });
            // Default to India
            countrySelect.value = 'India';
        }
    }

    function triggerChange(element) {
        // Trigger input and change events for validation and state updates
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function showError(fieldId, message) {
        const input = document.getElementById(fieldId);
        if (!input) return;
        
        // Find or create error element
        let errorEl = input.parentElement.querySelector('.form-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'form-error';
            input.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
        errorEl.style.display = 'block';
        input.classList.add('error');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                if (window.HRMS_CONFIG) {
                    window.HRMS_PincodeAutocomplete.init();
                }
            }, 100);
        });
    } else {
        setTimeout(() => {
            if (window.HRMS_CONFIG) {
                window.HRMS_PincodeAutocomplete.init();
            }
        }, 100);
    }

    // Public API
    return {
        init,
        lookupPincode,
        fillAddressFields,
        pincodeFields
    };
})();