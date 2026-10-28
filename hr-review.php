<?php
header('Cache-Control: no-store, private');
require_once __DIR__ . '/backend-integration/config/database.php';
require_once __DIR__ . '/backend-integration/config/secure-session.php';
require_once __DIR__ . '/backend-integration/config/admin-auth.php';
startSecureSession();

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$admin = currentAdminUser();
$isSignedIn = $admin !== null;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
$roleLabels = [
    'hr_head' => 'HR Head',
    'hr_processor' => 'HR Processor',
    'hr_executive' => 'HR Executive',
    'school_admin' => 'School Admin'
];
$roleLabel = $admin ? ($roleLabels[$admin['role']] ?? 'Administrator') : '';
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
    <header class="app-header">
        <a class="brand" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/admin" aria-label="Madras Seva Sadan HRMS Admin home">
            <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/images/mss-logo.png" alt="">
            <span>Madras Seva Sadan <strong>HRMS</strong></span>
        </a>
        <?php if ($isSignedIn): ?>
            <div class="hr-account">
                <span class="account-name">
                    <strong><?= htmlspecialchars($admin['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?><?= $admin['unit_name'] !== '' ? ' · ' . htmlspecialchars($admin['unit_name'], ENT_QUOTES, 'UTF-8') : '' ?></small>
                </span>
                <button class="button button-secondary" id="logout-button" type="button">Sign out</button>
            </div>
        <?php endif; ?>
    </header>

    <main class="hr-page">
        <?php if (!$isSignedIn): ?>
        <section class="login-shell" aria-labelledby="login-title">
            <div class="login-card panel">
                <div class="login-logo"><img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/images/mss-logo.png" alt="Madras Seva Sadan"></div>
                <p class="eyebrow">Madras Seva Sadan HRMS</p>
                <h1 id="login-title">HRMS Admin Login</h1>
                <p class="login-description">Sign in to manage employee information and approvals.</p>
                <form id="login-form">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="username" required maxlength="254" placeholder="name@organization.org">
                    <label for="password">Password</label>
                    <div class="password-control">
                        <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="1024">
                        <button class="password-toggle" id="toggle-password" type="button" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                    <p class="notice" id="page-message" role="status" aria-live="polite"></p>
                    <button class="button login-submit" type="submit">
                        <span class="login-spinner" aria-hidden="true"></span>
                        <span class="login-button-label">Sign in</span>
                    </button>
                </form>
            </div>
            <p class="login-footer">Authorized HR and School Admin access only</p>
        </section>
        <?php else: ?>
        <p class="notice" id="page-message" role="status" aria-live="polite"></p>
        <section class="page-titlebar" aria-labelledby="dashboard-title">
            <div class="dashboard-heading">
                <div>
                    <p class="eyebrow">Admin workspace</p>
                    <h1 id="dashboard-title">Employee Submissions</h1>
                    <p>Review employee information, make any needed corrections, and approve submissions.</p>
                </div>
                <button class="button button-secondary" id="refresh-button" type="button">Refresh</button>
            </div>
        </section>
        <section class="summary-grid" aria-label="Employee submission totals">
            <button class="summary-card summary-total" type="button" data-status="all">
                <span class="summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="summary-title">Total Employees</span><strong id="total-count">0</strong><span class="summary-link">View all submissions</span>
            </button>
            <button class="summary-card summary-pending" type="button" data-status="pending">
                <span class="summary-icon" aria-hidden="true">◷</span><span class="summary-title">Pending Reviews</span><strong id="pending-count">0</strong><span class="summary-link">Needs review</span>
            </button>
            <button class="summary-card summary-approved" type="button" data-status="approved">
                <span class="summary-icon" aria-hidden="true">✓</span><span class="summary-title">Approved Employees</span><strong id="approved-count">0</strong><span class="summary-link">Approved submissions</span>
            </button>
        </section>
        <section class="list-panel panel" aria-labelledby="list-title">
            <div class="list-heading">
                <div><h2 id="list-title">Employee submissions</h2><p id="result-count">Loading submissions…</p></div>
                <button class="button button-secondary clear-filters" id="clear-filters" type="button">Clear Filters</button>
            </div>
            <div class="filter-toolbar">
                <label class="search-control" for="submission-search"><span class="visually-hidden">Search employee submissions</span>
                    <span aria-hidden="true">⌕</span><input id="submission-search" type="search" maxlength="120" placeholder="Search name, email, employee ID or mobile">
                </label>
                <label class="visually-hidden" for="status-filter">Filter by status</label>
                <select id="status-filter" class="dashboard-select">
                    <option value="all">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                </select>
                <?php if (adminCanAccessAllUnits($admin)): ?>
                <label class="visually-hidden" for="unit-filter">Filter by unit</label>
                <select id="unit-filter" class="dashboard-select"><option value="">All units</option></select>
                <?php else: ?>
                <input id="unit-filter" type="hidden" value="<?= htmlspecialchars($admin['unit_name'], ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
            </div>
            <div class="submission-table-wrap">
                <table class="submission-table">
                    <thead><tr><th scope="col">S.No.</th><th scope="col">Employee ID</th><th scope="col">Employee</th><th scope="col">Email</th><th scope="col">Mobile</th><th scope="col">Unit</th><th scope="col">Designation</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                    <tbody id="submission-table-body"></tbody>
                </table>
            </div>
            <p class="empty-state" id="submission-empty" hidden>No employee submissions match these filters.</p>
            <div class="pagination">
                <span id="pagination-label">Showing 0 results</span>
                <div class="pagination-controls">
                    <button class="page-button" id="previous-page" type="button" aria-label="Previous page" disabled>‹</button>
                    <div class="page-numbers" id="page-numbers" aria-label="Submission pages"></div>
                    <button class="page-button" id="next-page" type="button" aria-label="Next page" disabled>›</button>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>
    <script>
        window.HR_REVIEW_CONFIG = {
            basePath: <?= json_encode($basePath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            signedIn: <?= $isSignedIn ? 'true' : 'false' ?>,
            role: <?= json_encode($admin['role'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            unit: <?= json_encode($admin['unit_name'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        };
    </script>
    <script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/hr-review.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/hr-review.js') ?>" defer></script>
</body>
</html>
