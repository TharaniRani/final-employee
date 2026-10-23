<?php
header('Cache-Control: no-store, private');
require_once __DIR__ . '/backend-integration/config/database.php';
require_once __DIR__ . '/backend-integration/config/secure-session.php';
startSecureSession();

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$isHr = ($_SESSION['user_role'] ?? '') === 'HR'
    && is_scalar($_SESSION['hr_user_id'] ?? null)
    && trim((string) ($_SESSION['hr_user_id'] ?? '')) !== ''
    && trim((string) ($_SESSION['hr_user_name'] ?? '')) !== '';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>HR Submission Review | HRMS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/hr-review.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/hr-review.css') ?>">
</head>
<body>
    <main class="hr-page">
        <header class="hr-header">
            <div>
                <p class="eyebrow">Madras Seva Sadan HRMS</p>
                <h1>Employee submission review</h1>
                <p>Review employee changes before applying approved fields to the original records.</p>
            </div>
            <?php if ($isHr): ?>
            <div class="hr-account">
                <span>Signed in as <?= htmlspecialchars((string) $_SESSION['hr_user_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <button class="button button-secondary" id="logout-button" type="button">Sign out</button>
            </div>
            <?php endif; ?>
        </header>

        <p class="notice" id="page-message" role="status" aria-live="polite"></p>

        <?php if (!$isHr): ?>
        <section class="panel login-panel" aria-labelledby="login-title">
            <h2 id="login-title">HR sign in</h2>
            <p>Use the local HR credentials configured in your private <code>.env</code> file.</p>
            <form id="login-form">
                <label for="username">Username</label>
                <input id="username" name="username" autocomplete="username" required maxlength="100">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="button" type="submit">Sign in</button>
            </form>
        </section>
        <?php else: ?>
        <section class="dashboard panel" aria-labelledby="dashboard-title">
            <div class="dashboard-heading">
                <div>
                    <h2 id="dashboard-title">HR — Employee Requests</h2>
                    <p>Search and manage employee detail correction requests.</p>
                </div>
                <button class="button button-secondary" id="refresh-button" type="button">Refresh</button>
            </div>
            <label class="filter-label" for="submission-search">Search</label>
            <input id="submission-search" class="dashboard-search" type="search" maxlength="120"
                placeholder="Employee ID, name, mobile or submission ID">
            <div class="filter-row">
                <div>
                    <label class="filter-label" for="status-filter">Status</label>
                    <select id="status-filter" class="dashboard-select">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="all">All statuses</option>
                    </select>
                </div>
                <div>
                    <label class="filter-label" for="unit-filter">Unit</label>
                    <select id="unit-filter" class="dashboard-select">
                        <option value="">All units</option>
                    </select>
                </div>
            </div>
            <div class="status-summary" aria-label="Submission totals">
                <div><span>Pending</span><strong id="pending-count">0</strong></div>
                <div><span>Approved</span><strong id="approved-count">0</strong></div>
                <div><span>Rejected</span><strong id="rejected-count">0</strong></div>
            </div>
        </section>
        <section id="submission-list" class="submission-list" aria-live="polite"></section>
        <dialog id="review-dialog" class="review-dialog" aria-labelledby="review-title">
            <div class="review-dialog-header">
                <div>
                    <p class="eyebrow">Employee correction request</p>
                    <h2 id="review-title">Review Request</h2>
                    <p id="review-subtitle" class="review-subtitle"></p>
                </div>
                <span id="review-status" class="status-badge status-pending">Pending</span>
                <button class="dialog-close" id="close-review" type="button" aria-label="Close review">×</button>
            </div>
            <div class="review-workspace">
                <nav class="review-steps-panel" aria-label="Changed-field review steps">
                    <p class="review-steps-caption">FORM STEPS</p>
                    <p class="review-steps-hint">Review changes step by step</p>
                    <ol id="review-steps" class="review-steps"></ol>
                    <div class="review-progress-card">
                        <span>Total changed fields</span>
                        <strong id="review-total-progress">0 / 0 reviewed</strong>
                        <div class="progress-track"><span id="review-progress-bar"></span></div>
                    </div>
                </nav>
                <section class="review-step-content" aria-labelledby="review-step-title">
                    <div class="review-step-heading">
                        <div>
                            <p class="step-overline" id="review-step-overline">Step 1</p>
                            <h3 id="review-step-title">Changed fields</h3>
                            <p id="review-step-description">Review the employee's requested corrections.</p>
                        </div>
                        <span id="step-change-count" class="change-count">0 changes</span>
                    </div>
                    <div class="review-table-wrap">
                        <table class="review-table">
                            <thead>
                                <tr>
                                    <th class="select-column" scope="col"><span class="visually-hidden">Select</span></th>
                                    <th scope="col">Field Name</th>
                                    <th scope="col">Original Value</th>
                                    <th scope="col">Requested Value</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody id="changed-fields-body"></tbody>
                        </table>
                    </div>
                    <div id="review-empty" class="empty-state" hidden>No changed fields in this step.</div>
                    <div class="selection-toolbar">
                        <label class="select-all-label"><input id="select-step-fields" type="checkbox"> Select all pending in this step</label>
                        <span id="selected-count" class="selected-count">0 selected</span>
                    </div>
                </section>
            </div>
            <div class="review-actions" id="review-actions">
                <button class="button button-secondary" id="previous-step" type="button">Previous</button>
                <div class="review-action-center">
                    <span id="step-indicator">Step 1 of 1</span>
                    <div id="step-dots" class="step-dots" aria-hidden="true"></div>
                </div>
                <div class="review-action-buttons">
                    <button class="button button-secondary" id="reject-request" type="button">Reject Submission</button>
                    <button class="button reject-selected-button" id="reject-selected" type="button" disabled>Reject Selected</button>
                    <button class="button" id="approve-selected" type="button" disabled>Approve Selected Changes</button>
                </div>
                <button class="button button-next" id="next-step" type="button">Next</button>
            </div>
            <p id="completed-notice" class="completed-notice" hidden></p>
        </dialog>
        <?php endif; ?>
    </main>
    <script>
        window.HR_REVIEW_CONFIG = {
            basePath: <?= json_encode($basePath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            signedIn: <?= $isHr ? 'true' : 'false' ?>
        };
    </script>
    <script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/hr-review.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/hr-review.js') ?>" defer></script>
</body>
</html>
