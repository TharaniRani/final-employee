<?php
/**
 * Step 10: Employment Details
 */
$isActive = $stepData['isActive'] ?? false;
$stepIndex = $stepData['stepIndex'] ?? 9;
$employmentFields = [
    ['unit', 'Unit', 'select'],
    ['campus', 'Campus', 'select'],
    ['designation', 'Designation', 'text'],
    ['employee_type', 'Employee Type (Grade)', 'select'],
    ['job_type', 'Job Type', 'select'],
    ['reporting_to', 'Reporting To', 'select'],
    ['date_of_joining', 'Date of Joining', 'date']
];
$renderEmploymentField = static function (array $field): string {
    [$key, $label, $type] = $field;
    $fieldId = 'employment-' . str_replace('_', '-', $key);
    $changeName = 'employment_' . $key . '_change';
    $updatedName = 'employment_' . $key . '_updated';
    $inputId = $fieldId . '-updated';
    $input = $type === 'select'
        ? '<select class="form-select" id="' . $inputId . '" name="' . $updatedName . '" required><option value="">Select ' . htmlspecialchars(strtolower($label), ENT_QUOTES, 'UTF-8') . '</option></select>'
        : '<input type="' . $type . '" class="form-input" id="' . $inputId . '" name="' . $updatedName . '" required>';

    return '
        <div class="form-group">
            <label for="' . $fieldId . '" class="form-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' <span class="required-indicator" aria-hidden="true">*</span></label>
            <input type="text" class="form-input" id="' . $fieldId . '" readonly aria-label="Current ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">
            <fieldset class="verification-group employment-change-group" id="' . $fieldId . '-change" tabindex="-1">
                <legend class="form-label">Change if required <span class="required-indicator" aria-hidden="true">*</span></legend>
                <div class="radio-group radio-inline">
                    <label class="radio-item" for="' . $fieldId . '-change-yes">
                        <input type="radio" id="' . $fieldId . '-change-yes" name="' . $changeName . '" value="Yes">
                        <span>Yes</span>
                    </label>
                    <label class="radio-item" for="' . $fieldId . '-change-no">
                        <input type="radio" id="' . $fieldId . '-change-no" name="' . $changeName . '" value="No">
                        <span>No</span>
                    </label>
                </div>
                <div class="form-error" id="' . $fieldId . '-change-error" role="alert"></div>
            </fieldset>
            <div id="' . $fieldId . '-replacement" hidden>
                <label for="' . $inputId . '" class="form-label">Updated ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' <span class="required-indicator" aria-hidden="true">*</span></label>
                ' . $input . '
                <div class="form-error" id="' . $fieldId . '-error" role="alert"></div>
            </div>
        </div>
    ';
};
?>
<div class="wizard-step <?php echo $isActive ? 'active' : ''; ?>" id="step-employment-details" data-step-index="<?php echo $stepIndex; ?>" role="tabpanel" aria-labelledby="step-employment-details-title" <?php echo $isActive ? '' : 'hidden'; ?>>
    <div class="step-header">
        <h2 class="step-title" id="step-employment-details-title">Employment Details</h2>
        <p class="step-description">Enter your role, workplace, reporting, and probation details.</p>
    </div>

    <div class="step-content">
        <form id="employment-details-form" novalidate>
            <input type="hidden" name="step" value="employment-details">

            <div class="alert alert-error" id="employment-options-error" role="alert" hidden></div>

            <?php foreach (array_chunk($employmentFields, 2) as $row): ?>
                <div class="form-row">
                    <?php foreach ($row as $field): ?>
                        <?php echo $renderEmploymentField($field); ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="step-footer">
        <span class="step-indicator">Step <?php echo $stepIndex + 1; ?> of <?php echo count($wizardStepKeys); ?></span>
        <div class="step-actions">
            <button type="button" class="btn btn-secondary" id="employment-details-prev">Previous</button>
            <button type="button" class="btn btn-primary" id="employment-details-next">
                Continue
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>
