<div class="mobile-verification-form" id="mobile-verification-form">
    <div class="text-center mb-5">
        <img class="verification-logo mx-auto mb-4" src="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/assets/images/mss-logo.png" alt="Madras Seva Sadan logo">
        <h1 class="page-title">Welcome Back</h1>
        <p class="page-description">Enter your registered mobile number to verify your identity and access your employee information.</p>
    </div>

    <div id="mobile-step" class="verification-step active">
        <form id="mobile-form" novalidate>
            <div class="form-group">
                <label for="mobile-number" class="form-label">
                    Mobile Number <span class="required-indicator" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                    </span>
                    <input type="tel" class="form-input" id="mobile-number" name="mobile_number" placeholder="Enter 10-digit mobile number" autocomplete="tel" maxlength="10" inputmode="numeric" pattern="[6-9]\d{9}" required aria-describedby="mobile-help">
                </div>
                <div id="mobile-help" class="form-help">Enter the mobile number registered with your employee record</div>
                <div class="form-error" id="mobile-error" role="alert" aria-live="polite"></div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg" id="verify-mobile-btn">
                    <span class="btn-text">Send OTP</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner spinner-sm me-2" aria-hidden="true"></span>
                        Sending OTP...
                    </span>
                </button>
            </div>
        </form>
    </div>

    <div id="otp-step" class="verification-step" style="display: none;" aria-live="polite">
        <div class="text-center mb-4">
            <h2 class="h4">Enter OTP</h2>
            <p class="text-secondary">Enter the 6-digit verification code for <strong id="otp-mobile-display"></strong>.</p>
            <p class="form-help" id="otp-delivery-status" role="status" aria-live="polite"></p>
        </div>

        <form id="otp-form" novalidate>
            <div class="form-group">
                <label class="form-label visually-hidden">One Time Password</label>
                <div class="otp-inputs" role="group" aria-label="Enter 6-digit OTP">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input type="text" class="form-input otp-input" id="otp-<?php echo $i; ?>" name="otp_<?php echo $i; ?>" maxlength="1" inputmode="numeric" pattern="\d" autocomplete="one-time-code" aria-label="OTP digit <?php echo $i; ?>" <?php echo $i === 1 ? 'autofocus' : ''; ?>>
                    <?php endfor; ?>
                </div>
                <div class="form-error" id="otp-error" role="alert" aria-live="polite"></div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg" id="verify-otp-btn">
                    <span class="btn-text">Verify OTP</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner spinner-sm me-2" aria-hidden="true"></span>
                        Verifying...
                    </span>
                </button>
            </div>
        </form>

        <div class="resend-timer text-center mt-4">
            <p class="mb-2">Didn't receive the OTP?</p>
            <button type="button" class="resend-link" id="resend-otp-btn" disabled>Resend OTP (60s)</button>
        </div>

        <div class="text-center mt-3">
            <button type="button" class="btn btn-ghost" id="back-to-mobile">← Change Mobile Number</button>
        </div>
    </div>

    <div id="success-step" class="verification-step" style="display: none; text-align: center;">
        <div class="success-icon mx-auto mb-4" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="40" height="40">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
        </div>
        <h2 class="h4 mb-2">Mobile Verified Successfully!</h2>
        <p class="text-secondary mb-4">Redirecting to your employee information form...</p>
        <div class="spinner mx-auto" aria-hidden="true"></div>
    </div>
</div>

<div class="otp-preview-overlay" id="otp-preview-modal" role="dialog" aria-modal="true" aria-labelledby="otp-preview-title" aria-describedby="otp-preview-description" hidden>
    <div class="otp-preview-dialog">
        <div class="otp-preview-heading">
            <div class="otp-preview-icon" aria-hidden="true">✓</div>
            <h2 class="otp-preview-title" id="otp-preview-title">Your local OTP is ready</h2>
        </div>
        <p class="otp-preview-description" id="otp-preview-description">For local development only. Enter this code on the next screen.</p>
        <output class="otp-preview-code" id="otp-preview-code" aria-label="Generated one-time password"></output>
        <p class="otp-preview-copy-status" id="otp-preview-copy-status" role="status" aria-live="polite"></p>
        <div class="otp-preview-actions">
            <button type="button" class="btn btn-secondary" id="copy-otp-btn">Copy OTP</button>
            <button type="button" class="btn btn-primary" id="otp-preview-ok">OK</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileForm = document.getElementById('mobile-form');
    const otpForm = document.getElementById('otp-form');
    const mobileInput = document.getElementById('mobile-number');
    const otpInputs = document.querySelectorAll('.otp-input');
    const verifyMobileBtn = document.getElementById('verify-mobile-btn');
    const verifyOtpBtn = document.getElementById('verify-otp-btn');
    const resendOtpBtn = document.getElementById('resend-otp-btn');
    const backToMobileBtn = document.getElementById('back-to-mobile');
    const mobileStep = document.getElementById('mobile-step');
    const otpStep = document.getElementById('otp-step');
    const successStep = document.getElementById('success-step');
    const otpMobileDisplay = document.getElementById('otp-mobile-display');
    const otpDeliveryStatus = document.getElementById('otp-delivery-status');
    const otpPreviewModal = document.getElementById('otp-preview-modal');
    const otpPreviewCode = document.getElementById('otp-preview-code');
    const otpPreviewCopyStatus = document.getElementById('otp-preview-copy-status');
    const copyOtpBtn = document.getElementById('copy-otp-btn');
    const otpPreviewOkBtn = document.getElementById('otp-preview-ok');
    const mobileError = document.getElementById('mobile-error');
    const otpError = document.getElementById('otp-error');

    let resendTimer = null;
    let countdown = 60;
    let onOtpPreviewOk = null;

    function showStep(step) {
        [mobileStep, otpStep, successStep].forEach(s => s.style.display = 'none');
        step.style.display = 'block';
        step.classList.add('active');
    }

    function setButtonLoading(btn, loading) {
        const text = btn.querySelector('.btn-text');
        const loadingEl = btn.querySelector('.btn-loading');
        if (loading) {
            btn.disabled = true;
            text.style.display = 'none';
            loadingEl.style.display = 'inline-flex';
        } else {
            btn.disabled = false;
            text.style.display = 'inline';
            loadingEl.style.display = 'none';
        }
    }

    function showError(element, message) {
        element.textContent = message;
        element.style.display = 'flex';
    }

    function hideError(element) {
        element.textContent = '';
        element.style.display = 'none';
    }

    function startResendCountdown() {
        if (resendTimer) clearInterval(resendTimer);
        countdown = 60;
        resendOtpBtn.disabled = true;
        resendOtpBtn.textContent = `Resend OTP (${countdown}s)`;

        resendTimer = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                clearInterval(resendTimer);
                resendTimer = null;
                resendOtpBtn.disabled = false;
                resendOtpBtn.textContent = 'Resend OTP';
            } else {
                resendOtpBtn.textContent = `Resend OTP (${countdown}s)`;
            }
        }, 1000);
    }

    function showOtpPreview(otp, onConfirm) {
        otpPreviewCode.textContent = otp;
        otpPreviewCopyStatus.textContent = '';
        onOtpPreviewOk = onConfirm;
        otpPreviewModal.hidden = false;
        requestAnimationFrame(() => {
            otpPreviewModal.classList.add('active');
            otpPreviewOkBtn.focus();
        });
    }

    function closeOtpPreview() {
        otpPreviewModal.classList.remove('active');
        otpPreviewModal.hidden = true;
        otpPreviewCode.textContent = '';
        const onConfirm = onOtpPreviewOk;
        onOtpPreviewOk = null;
        if (onConfirm) onConfirm();
    }

    async function copyOtpToClipboard() {
        const otp = otpPreviewCode.textContent;
        try {
            await navigator.clipboard.writeText(otp);
            otpPreviewCopyStatus.textContent = 'OTP copied to clipboard.';
        } catch (error) {
            const temporaryInput = document.createElement('textarea');
            temporaryInput.value = otp;
            temporaryInput.setAttribute('readonly', '');
            temporaryInput.style.position = 'fixed';
            temporaryInput.style.opacity = '0';
            document.body.appendChild(temporaryInput);
            temporaryInput.select();
            const copied = document.execCommand('copy');
            temporaryInput.remove();
            otpPreviewCopyStatus.textContent = copied
                ? 'OTP copied to clipboard.'
                : 'Copy was unavailable. Select and copy the code above.';
        }
    }

    function formatMobile(number) {
        return number.replace(/(\d{5})(\d{5})/, '$1 $2');
    }

    mobileForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        hideError(mobileError);

        const mobile = mobileInput.value.trim();
        const pattern = new RegExp(window.HRMS_CONFIG.validation.mobilePattern);

        if (!mobile) {
            showError(mobileError, 'Please enter your mobile number');
            mobileInput.focus();
            return;
        }

        if (!pattern.test(mobile)) {
            showError(mobileError, 'Please enter a valid 10-digit Indian mobile number');
            mobileInput.focus();
            return;
        }

        setButtonLoading(verifyMobileBtn, true);

        try {
            const response = await window.HRMS_API.verifyMobile(mobile);

            if (response.success) {
                otpMobileDisplay.textContent = formatMobile(mobile);
                otpDeliveryStatus.textContent = response.message || '';
                startResendCountdown();
                if (typeof response.otp === 'string' && /^\d{6}$/.test(response.otp)) {
                    showOtpPreview(response.otp, function() {
                        showStep(otpStep);
                        otpInputs[0].focus();
                    });
                } else {
                    showStep(otpStep);
                    otpInputs[0].focus();
                }
            } else {
                showError(mobileError, response.message || 'This mobile number is not registered. Please contact HR.');
            }
        } catch (error) {
            showError(mobileError, error.message || 'Unable to verify this mobile number. Please try again.');
            console.error('Mobile verification error:', error);
        } finally {
            setButtonLoading(verifyMobileBtn, false);
        }
    });

    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
            hideError(otpError);
            this.classList.remove('error', 'filled');

            if (this.value) {
                this.classList.add('filled');
                if (index < 5) {
                    otpInputs[index + 1].focus();
                } else {
                    verifyOtpBtn.focus();
                }
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && index > 0) {
                otpInputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            if (paste.length === 6) {
                paste.split('').forEach((digit, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = digit;
                        otpInputs[i].classList.add('filled');
                    }
                });
                otpInputs[5].focus();
            }
        });
    });

    otpForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        hideError(otpError);

        const otp = Array.from(otpInputs).map(i => i.value).join('');

        if (otp.length !== 6) {
            showError(otpError, 'Please enter the complete 6-digit OTP');
            otpInputs[otp.length].focus();
            otpInputs.forEach(i => i.classList.add('error'));
            return;
        }

        setButtonLoading(verifyOtpBtn, true);

        try {
            const mobile = mobileInput.value.trim();
            const response = await window.HRMS_API.verifyOtp(mobile, otp);

            if (response.success) {
                showStep(successStep);
                if (resendTimer) clearInterval(resendTimer);

                window.location.assign(window.HRMS_CONFIG.basePath + '/index.php?step=personal-basic');
            } else {
                showError(otpError, response.message || 'Invalid OTP. Please try again.');
                otpInputs.forEach(i => i.classList.add('error'));
                otpInputs[0].focus();
            }
        } catch (error) {
            showError(otpError, error.message || 'Unable to verify the OTP. Please try again.');
            console.error('OTP verification error:', error);
        } finally {
            setButtonLoading(verifyOtpBtn, false);
        }
    });

    resendOtpBtn.addEventListener('click', async function() {
        if (this.disabled) return;

        const mobile = mobileInput.value.trim();
        this.disabled = true;
        this.textContent = 'Sending...';

        try {
            const response = await window.HRMS_API.verifyMobile(mobile);
            if (response.success) {
                otpDeliveryStatus.textContent = response.message || '';
                startResendCountdown();
                otpInputs.forEach(input => {
                    input.value = '';
                    input.classList.remove('filled', 'error');
                });
                hideError(otpError);
                if (typeof response.otp === 'string' && /^\d{6}$/.test(response.otp)) {
                    showOtpPreview(response.otp, function() {
                        otpInputs[0].focus();
                    });
                }
            } else {
                showError(otpError, response.message || 'Failed to resend OTP');
                this.disabled = false;
                this.textContent = 'Resend OTP';
            }
        } catch (error) {
            showError(otpError, error.message || 'Unable to resend the OTP. Please try again.');
            this.disabled = false;
            this.textContent = 'Resend OTP';
        }
    });

    copyOtpBtn.addEventListener('click', copyOtpToClipboard);
    otpPreviewOkBtn.addEventListener('click', closeOtpPreview);

    backToMobileBtn.addEventListener('click', function() {
        if (resendTimer) clearInterval(resendTimer);
        showStep(mobileStep);
        mobileInput.focus();
    });

    mobileInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
        hideError(mobileError);
    });
});
</script>