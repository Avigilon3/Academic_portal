<?php
require_once __DIR__ . '/core/config/config.php';
require_once __DIR__ . '/core/db/connection.php';
require_once __DIR__ . '/includes/services/CertificateService.php';

$code = isset($_GET['code']) && is_string($_GET['code']) ? trim($_GET['code']) : '';
$certificate = null;
$error = '';
$serviceFailure = false;

try {
    $certificate = $code !== '' ? (new CertificateService($pdo))->verifyCertificate($code) : null;
} catch (Throwable $exception) {
    error_log('Public certificate verification error: ' . $exception->getMessage());
    http_response_code(500);
    $serviceFailure = true;
    $error = 'The verification service is temporarily unavailable.';
}

if (!$certificate && !$serviceFailure) {
    http_response_code(404);
    $error = 'No valid certificate was found for this code.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - Pateros Technological College</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/student/assets/css/student-tools.css">
</head>
<body class="document-page">
<main class="verification-card<?= $certificate ? '' : ' is-invalid'; ?>">
    <?php if ($certificate): ?>
        <h1>Certificate verified</h1>
        <p>This certificate record is valid in the Pateros Technological College Academic Portal.</p>
        <dl>
            <dt>Certificate holder</dt>
            <dd><?= htmlspecialchars((string)$certificate['full_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt>Achievement</dt>
            <dd><?= htmlspecialchars((string)$certificate['title'], ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt>Certificate type</dt>
            <dd><?= htmlspecialchars($certificate['certificate_type'] === 'course_completion' ? 'Course completion' : 'Subject completion', ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt>Units earned</dt>
            <dd><?= htmlspecialchars(rtrim(rtrim(number_format((float)$certificate['units_completed'], 1), '0'), '.'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt>Term</dt>
            <dd><?= htmlspecialchars(trim((string)$certificate['term_name'] . ' ' . (string)($certificate['school_year'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt>Issued</dt>
            <dd><?= htmlspecialchars((string)$certificate['issued_at'], ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt>Verification code</dt>
            <dd class="certificate-code"><?= htmlspecialchars((string)$certificate['certificate_code'], ENT_QUOTES, 'UTF-8'); ?></dd>
        </dl>
    <?php else: ?>
        <h1><?= $serviceFailure ? 'Verification unavailable' : 'Certificate not found'; ?></h1>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
</main>
</body>
</html>
