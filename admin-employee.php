<?php
header('Cache-Control: no-store, private');
require_once __DIR__ . '/backend-integration/config/database.php';
require_once __DIR__ . '/backend-integration/config/secure-session.php';
require_once __DIR__ . '/backend-integration/config/admin-auth.php';
startSecureSession();

$admin = currentAdminUser();
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$submissionId = filter_var($_GET['submission_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($admin === null) {
    header('Location: ' . $basePath . '/admin', true, 302);
    exit;
}
if ($submissionId === false) {
    http_response_code(400);
    exit('A valid submission ID is required.');
}

try {
    $pdo = getDatabaseConnection();
    $submission = $pdo->prepare('SELECT employee_id, status FROM employee_form_submissions WHERE id = ?');
    $submission->execute([$submissionId]);
    $submissionRecord = $submission->fetch(PDO::FETCH_ASSOC);
    if (!$submissionRecord || !in_array($submissionRecord['status'], ['pending', 'approved'], true) ||
        !submissionIsInAdminScope($pdo, (int) $submissionId, $admin)) {
        http_response_code(404);
        exit('Submission not found in your authorized unit.');
    }
    $employeeId = (int) $submissionRecord['employee_id'];
} catch (Throwable $e) {
    error_log('Admin employee review page authorization failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to open this employee submission.');
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Employee Review | Madras Seva Sadan HRMS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/hr-review.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/hr-review.css') ?>">
</head>
<body>
    <header class="app-header">
        <a class="brand" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/admin" aria-label="Return to HRMS Admin dashboard">
            <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/images/mss-logo.png" alt="">
            <span>Madras Seva Sadan <strong>HRMS</strong></span>
        </a>
        <a class="button button-secondary profile-back-link" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/admin">Back to submissions</a>
    </header>
    <main class="hr-page employee-review-page">
        <p class="notice" id="profile-message" role="status" aria-live="polite"></p>
        <section class="employee-review-heading panel">
            <div>
                <p class="eyebrow">Employee submission review</p>
                <h1 id="employee-name">Loading employee…</h1>
                <p id="employee-identifiers"></p>
            </div>
            <span id="employee-status" class="status-badge status-pending">Pending</span>
        </section>
        <div id="profile-sections" class="profile-sections" aria-live="polite">
            <p class="empty-state">Loading employee information…</p>
        </div>
        <!-- <section class="panel history-panel" aria-labelledby="history-title">
            <div class="profile-section-heading">
                <div><h2 id="history-title">Change history</h2><p>Administrator edits are saved immediately and recorded here.</p></div>
            </div>
            <div id="history-list" class="history-list"><p class="empty-state">Loading change history…</p></div>
        </section> -->
        <section class="panel submission-approval" aria-label="Submission approval">
            <p id="approval-message">Review the employee information above before approving this submission.</p>
            <div class="submission-approval-actions">
                <!-- <button class="button button-danger" id="profile-delete" type="button" hidden>Delete submission</button> -->
                <button class="button" id="profile-approve" type="button" disabled>Approve submission</button>
            </div>
        </section>
    </main>
    <dialog class="confirmation-dialog" id="delete-confirmation" aria-labelledby="delete-confirmation-title" aria-describedby="delete-confirmation-message">
        <div class="confirmation-card">
            <span class="confirmation-icon" aria-hidden="true">!</span>
            <h2 id="delete-confirmation-title">Delete submission?</h2>
            <p id="delete-confirmation-message">This pending employee submission will be permanently deleted. This action cannot be undone.</p>
            <div class="confirmation-actions">
                <button class="button button-secondary" id="cancel-delete" type="button">Cancel</button>
                <button class="button button-danger" id="confirm-delete" type="button">Delete submission</button>
            </div>
        </div>
    </dialog>
    <script>
        window.ADMIN_EMPLOYEE_CONFIG = {
            basePath: <?= json_encode($basePath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            employeeId: <?= (int) $employeeId ?>,
            submissionId: <?= (int) $submissionId ?>,
            role: <?= json_encode($admin['role'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        };
    </script>
    <script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/admin-employee.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/admin-employee.js') ?>" defer></script>
</body>
</html>
