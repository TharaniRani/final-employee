<?php
/**
 * Employee Information Wizard Form - HRMS
 * Main entry point for the multi-step employee onboarding form
 */

require_once __DIR__ . '/backend-integration/config/secure-session.php';
startSecureSession();

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$pathInfo = rtrim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
if ($pathInfo === '/admin') {
    require __DIR__ . '/hr-review.php';
    exit;
}

$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

$steps = [
    'mobile-verification' => 'Mobile Verification',
    'personal-basic' => 'Personal Basic Info',
    'address' => 'Address Details',
    'aadhaar-pan' => 'Aadhaar & PAN',
    'personal-details' => 'Personal Details',
    'next-of-kin' => 'Next of Kin',
    'referral' => 'Referral Info',
    'education' => 'Education',
    'teacher-documents' => 'Teacher Details',
    'work-experience' => 'Work Experience',
    'employment-details' => 'Employment Details',
    'review' => 'Review & Submit'
];

$stepKeys = array_keys($steps);
// Set true to restore the retained Employment Details step.
$showEmploymentDetailsStep = false;
$wizardStepKeys = array_values(array_filter($stepKeys, static function ($key) use ($showEmploymentDetailsStep) {
    return $key !== 'mobile-verification' &&
        ($showEmploymentDetailsStep || $key !== 'employment-details');
}));
$currentStep = $_GET['step'] ?? 'mobile-verification';
$isMobileVerification = $currentStep === 'mobile-verification';
$hideEmploymentDetails = !$showEmploymentDetailsStep;
$employmentDetailsWasHidden = $hideEmploymentDetails && $currentStep === 'employment-details';
if ($employmentDetailsWasHidden) {
    $currentStep = 'review';
}
$loginMobilePrefill = '';
if ($isMobileVerification) {
    $loginMobilePrefill = (string) ($_SESSION['login_mobile_prefill'] ?? '');
    unset($_SESSION['login_mobile_prefill']);
}
$currentStepIndex = $isMobileVerification
    ? 0
    : array_search($currentStep, $wizardStepKeys);

if (!$isMobileVerification && empty($_SESSION['verified_employee_id'])) {
    header('Location: ' . $basePath . '/index.php?step=mobile-verification', true, 302);
    exit;
}

if ($currentStepIndex === false) {
    $currentStep = 'personal-basic';
    $currentStepIndex = 0;
}

$showStepper = !$isMobileVerification;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Employee Information & Onboarding Form - HRMS">
    <meta name="theme-color" content="#1e3a8a">
    <title>Employee Information & Onboarding | HRMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/variables.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/components.css?v=<?php echo filemtime(__DIR__ . '/assets/css/components.css'); ?>">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/wizard.css?v=<?php echo filemtime(__DIR__ . '/assets/css/wizard.css'); ?>">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/responsive.css">

    <style>
        .step-content { display: block; }
        .wizard-step.active .step-content { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header class="header no-print" role="banner">
        <div class="container header-content">
            <div class="header-brand">
                <img class="header-logo" src="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/assets/images/mss-logo.png" alt="">
                <span class="header-title">Madras Seva Sadan HRMS</span>
            </div>
            <div class="header-actions">
                <span class="badge badge-primary" id="form-status-badge" style="display: none;">Draft</span>
            </div>
        </div>
    </header>

    <?php if ($showStepper): ?>
    <nav class="stepper no-print" aria-label="Form progress" role="navigation">
        <?php foreach ($wizardStepKeys as $index => $key): ?>
            <?php
            $stepLabel = $steps[$key];
            $isActive = $index === $currentStepIndex;
            $isCompleted = $index < $currentStepIndex;
            $hasError = false; // Will be set by JS
            $classes = ['stepper-item'];
            if ($isActive) $classes[] = 'active';
            if ($isCompleted) $classes[] = 'completed';
            if ($hasError) $classes[] = 'error';
            ?>
            <div class="<?php echo implode(' ', $classes); ?>" data-step="<?php echo $key; ?>" role="button" tabindex="0" aria-current="<?php echo $isActive ? 'step' : 'false'; ?>">
                <div class="stepper-step" data-progress="<?php echo $index + 1; ?> of <?php echo count($wizardStepKeys); ?>" style="--step-progress: <?php echo (($index + 1) / count($wizardStepKeys)) * 100; ?>%;" aria-hidden="true"><?php echo $index + 1; ?></div>
                <span class="stepper-label"><?php echo htmlspecialchars($stepLabel); ?></span>
            </div>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <main id="main-content" class="main-content" role="main">
        <div class="container">
            <?php if (!$isMobileVerification): ?>
            <div class="page-header no-print">
                <!-- <h1 class="page-title">Employee Information & Onboarding</h1> -->
                <!-- <p class="page-description">Verify your existing information, update editable details, upload required documents, and submit your employee profile to the HRMS.</p> -->
            </div>
            <?php endif; ?>

            <div id="alert-container" aria-live="polite" aria-atomic="true"></div>

            <?php if ($isMobileVerification): ?>
                <?php include __DIR__ . '/components/mobile-verification.php'; ?>
            <?php else: ?>
                <div class="form-wizard" id="form-wizard">
                    <section class="existing-documents-summary" id="existing-legacy-details" aria-label="Additional legacy employee details" hidden></section>
                    <?php foreach ($wizardStepKeys as $index => $key): ?>
                        <?php
                        $isActive = $index === $currentStepIndex;
                        $componentFile = __DIR__ . '/components/step-' . $key . '.php';
                        if (file_exists($componentFile)) {
                            $stepData = ['isActive' => $isActive, 'stepIndex' => $index];
                            include $componentFile;
                        }
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer no-print" role="contentinfo">
        <div class="container footer-content">
            <div class="footer-brand">
                <img src="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/assets/images/mss-logo.png" alt="">
                <span>Madras Seva Sadan</span>
            </div>
            <p class="footer-text">&copy; 2026 Madras Seva Sadan. All rights reserved.</p>
            <!-- <nav class="footer-links" aria-label="Footer navigation">
                <a href="#" class="footer-link">Privacy Policy</a>
                <a href="#" class="footer-link">Terms of Service</a>
                <a href="#" class="footer-link">Contact HR</a>
            </nav> -->
        </div>
    </footer>

    <div class="modal-overlay" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title" hidden>
        <div class="hrms-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="confirm-modal-title">Confirm Action</h2>
                <button class="modal-close" id="confirm-modal-close" aria-label="Close dialog">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirm-modal-message"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="confirm-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-modal-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="info-modal" role="dialog" aria-modal="true" aria-labelledby="info-modal-title" hidden>
        <div class="hrms-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="info-modal-title">Information</h2>
                <button class="modal-close" id="info-modal-close" aria-label="Close dialog">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p id="info-modal-message"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="info-modal-ok">OK</button>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="global-loading" role="status" aria-live="polite" aria-label="Loading" hidden>
        <div class="loading-spinner" aria-hidden="true"></div>
        <p class="loading-text" id="loading-text">Processing...</p>
    </div>

    <div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="true"></div>

    <script>
        window.HRMS_CONFIG = {
            basePath: '<?php echo $basePath; ?>',
            csrfToken: '<?php echo $csrfToken; ?>',
            verifiedMobile: '<?php echo htmlspecialchars($_SESSION['verified_mobile'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
            currentStep: '<?php echo $currentStep; ?>',
            stepKeys: <?php echo json_encode($wizardStepKeys); ?>,
            steps: <?php echo json_encode(array_intersect_key($steps, array_flip($wizardStepKeys))); ?>,
            hideEmploymentDetails: <?php echo $hideEmploymentDetails ? 'true' : 'false'; ?>,
            apiEndpoints: {
                verifyMobile: '<?php echo $basePath; ?>/backend-integration/api/verify-mobile.php',
                verifyOtp: '<?php echo $basePath; ?>/backend-integration/api/verify-otp.php',
                getEmployee: '<?php echo $basePath; ?>/backend-integration/api/get-employee.php',
                uploadDocument: '<?php echo $basePath; ?>/backend-integration/api/upload-document.php',
                saveDraft: '<?php echo $basePath; ?>/backend-integration/api/save-draft.php',
                getDraft: '<?php echo $basePath; ?>/backend-integration/api/get-draft.php',
                submitForm: '<?php echo $basePath; ?>/backend-integration/api/submit-form.php',
                getSchools: '<?php echo $basePath; ?>/backend-integration/api/get-schools.php',
                getUnits: '<?php echo $basePath; ?>/backend-integration/api/get-units.php',
                getEmploymentOptions: '<?php echo $basePath; ?>/backend-integration/api/get-employment-options.php',
                getFamilyMemberEmployment: '<?php echo $basePath; ?>/backend-integration/api/get-family-member-employment.php'
            },
            validation: {
                mobilePattern: '^[6-9]\\d{9}$',
                emailPattern: '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$',
                panPattern: '^[A-Z]{5}[0-9]{4}[A-Z]{1}$',
                aadhaarPattern: '^\\d{12}$',
                pincodePattern: '^\\d{6}$',
                maxFileSize: 5242880,
                allowedFileTypes: ['application/pdf', 'image/jpeg', 'image/png']
            },
            schools: [
                'Lady Andal School - IB',
                'Sir Mutha School - CBSE',
                'Sir & Lady M. Venkatasubba Rao Hr., Sec., School, Tambaram',
                'Lady Andal Open School',
                'Lady Andal House of Children - NIOS',
                'The Madras Seva Sadan Hr., Sec., School Chetpet',
                'The Madras Seva Sadan Hr., Sec., School Tambaram',
                'Lady Andal Venkatasubba Rao Mat., Hr., Sec., School',
                'Sir M. Venkatasubba Rao Hr., Sec., School, T. Nagar'
            ],
            qualificationTypes: [
                { value: 'X', label: 'X (10th Standard)' },
                { value: 'XII', label: 'XII (12th Standard)' },
                { value: 'UG', label: 'Undergraduate (UG)' },
                { value: 'PG', label: 'Postgraduate (PG)' },
                { value: 'Doc', label: 'Doctorate (Doc)' },
                { value: 'Diploma', label: 'Diploma' },
                { value: 'Others', label: 'Others (Grade 4 only)' }
            ],
            bloodGroups: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            maritalStatuses: ['Single', 'Married', 'Divorced', 'Widowed'],
            genders: ['Male', 'Female', 'Other'],
            states: [
                'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
                'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
                'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
                'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
                'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
                'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
                'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli',
                'Daman and Diu', 'Delhi', 'Lakshadweep', 'Puducherry'
            ],
            countries: [
                'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola',
                'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
                'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados',
                'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia',
                'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria',
                'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia', 'Cameroon',
                'Canada', 'Central African Republic', 'Chad', 'Chile', 'China',
                'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Cote d\'Ivoire',
                'Croatia', 'Cuba', 'Cyprus', 'Czechia', 'Democratic Republic of the Congo',
                'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador',
                'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia',
                'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon',
                'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada',
                'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti',
                'Holy See', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia',
                'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan',
                'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan',
                'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya',
                'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi',
                'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands',
                'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova',
                'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique',
                'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand',
                'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia',
                'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama',
                'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland',
                'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda',
                'Saint Kitts and Nevis', 'Saint Lucia',
                'Saint Vincent and the Grenadines', 'Samoa', 'San Marino',
                'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia',
                'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia',
                'Solomon Islands', 'Somalia', 'South Africa', 'South Korea',
                'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden',
                'Switzerland', 'Syria', 'Tajikistan', 'Tanzania', 'Thailand',
                'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia',
                'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine',
                'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay',
                'Uzbekistan', 'Vanuatu', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia',
                'Zimbabwe'
            ]
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $basePath; ?>/assets/js/api.js?v=<?php echo filemtime(__DIR__ . '/assets/js/api.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/form-state.js?v=<?php echo filemtime(__DIR__ . '/assets/js/form-state.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/employment-details.js?v=<?php echo filemtime(__DIR__ . '/assets/js/employment-details.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/validation.js?v=<?php echo filemtime(__DIR__ . '/assets/js/validation.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/conditional-fields.js?v=<?php echo filemtime(__DIR__ . '/assets/js/conditional-fields.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/file-upload.js?v=<?php echo filemtime(__DIR__ . '/assets/js/file-upload.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/education.js?v=<?php echo filemtime(__DIR__ . '/assets/js/education.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/work-experience.js?v=<?php echo filemtime(__DIR__ . '/assets/js/work-experience.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/referral.js?v=<?php echo filemtime(__DIR__ . '/assets/js/referral.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/other-documents.js"></script>
    <script src="<?php echo $basePath; ?>/assets/js/review.js?v=<?php echo filemtime(__DIR__ . '/assets/js/review.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/wizard.js?v=<?php echo filemtime(__DIR__ . '/assets/js/wizard.js'); ?>"></script>
    <script src="<?php echo $basePath; ?>/assets/js/pincode-autocomplete.js"></script>
    <script src="<?php echo $basePath; ?>/assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/assets/js/app.js'); ?>"></script>
</body>
</html>