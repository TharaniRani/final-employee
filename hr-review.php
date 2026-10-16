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
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/hr-review.css">
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
        <section class="toolbar">
            <h2>Pending submissions</h2>
            <button class="button button-secondary" id="refresh-button" type="button">Refresh queue</button>
        </section>
        <section id="submission-list" class="submission-list" aria-live="polite"></section>
        <?php endif; ?>
    </main>
    <script>
        window.HR_REVIEW_CONFIG = {
            basePath: <?= json_encode($basePath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            signedIn: <?= $isHr ? 'true' : 'false' ?>
        };
    </script>
    <script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/hr-review.js" defer></script>
</body>
</html>
