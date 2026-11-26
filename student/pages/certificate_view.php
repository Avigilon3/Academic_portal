<?php
require_once '../includes/init.php';
require_once '../../includes/services/ResumeService.php';
require_once '../../includes/services/CertificateService.php';
requireStudent();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$idInput = $_GET['id'] ?? null;
$certificateId = is_string($idInput) || is_int($idInput)
    ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$resumeService = new ResumeService($pdo);
$certificateService = new CertificateService($pdo);

try {
    $studentId = $resumeService->getStudentIdForUser($userId);
    $certificate = $studentId && $certificateId
        ? $certificateService->getCertificateForStudent($studentId, (int)$certificateId)
        : null;
} catch (Throwable $exception) {
    error_log('E-certificate view error: ' . $exception->getMessage());
    http_response_code(500);
    exit('The certificate could not be loaded. Please try again.');
}

if (!$certificate) {
    http_response_code(404);
    exit('Certificate not found.');
}

$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\])(?::[0-9]{1,5})?$/D', $host)) {
    http_response_code(400);
    exit('The certificate verification link could not be created for this host.');
}
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$verifyUrl = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/verify_certificate.php?code='
    . rawurlencode((string)$certificate['certificate_code']);
$issuedAt = strtotime((string)$certificate['issued_at']);
$issuedLabel = $issuedAt ? date('F j, Y', $issuedAt) : (string)$certificate['issued_at'];
$unitValue = rtrim(rtrim(number_format((float)$certificate['units_completed'], 1), '0'), '.');
$isCourseCertificate = $certificate['certificate_type'] === 'course_completion';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)$certificate['title'], ENT_QUOTES, 'UTF-8'); ?> - PTC E-Certificate</title>
    <link rel="stylesheet" href="../assets/css/student-tools.css">
    <link rel="stylesheet" href="../assets/css/certificate-print.css">
</head>
<body class="certificate-document">
<main class="document-page">
    <div class="document-actions">
        <a href="e_certificates.php">Back to certificates</a>
        <button type="button" onclick="window.print()">Download PDF / Print</button>
        <button type="button" data-download-certificate>Download PNG</button>
    </div>
    <article class="certificate-paper">
        <p class="certificate-school">Pateros Technological College</p>
        <p class="certificate-overline">Certificate of <?= $isCourseCertificate ? 'Course' : 'Subject'; ?> Completion</p>
        <h1>Certificate</h1>
        <p class="certificate-description">This certifies that</p>
        <p class="certificate-recipient"><?= htmlspecialchars((string)$certificate['full_name'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="certificate-description">
            has successfully completed <?= $isCourseCertificate ? 'the course milestone' : 'the subject'; ?>
            <strong><?= htmlspecialchars((string)$certificate['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
            and earned <?= htmlspecialchars($unitValue, ENT_QUOTES, 'UTF-8'); ?> units.
        </p>
        <p class="certificate-details">
            Completed <?= htmlspecialchars($issuedLabel, ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($certificate['term_name'])): ?>
                · <?= htmlspecialchars((string)$certificate['term_name'], ENT_QUOTES, 'UTF-8'); ?>
                <?= !empty($certificate['school_year']) ? htmlspecialchars((string)$certificate['school_year'], ENT_QUOTES, 'UTF-8') : ''; ?>
            <?php endif; ?>
        </p>
        <div class="certificate-footer">
            <div class="certificate-signature">Registrar</div>
            <div class="certificate-qr-wrap">
                <canvas width="168" height="168" data-certificate-qr data-verify-url="<?= htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="QR code for public certificate verification"></canvas>
                <span data-qr-status>Scan to verify</span>
            </div>
            <div class="certificate-signature">Authorized Signatory</div>
        </div>
        <p class="certificate-code-print">
            Verification code:
            <a href="<?= htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$certificate['certificate_code'], ENT_QUOTES, 'UTF-8'); ?></a>
        </p>
    </article>
    <p class="tools-message is-error" data-certificate-download-status role="alert" hidden></p>
</main>
<script src="../assets/js/certificate-qr.js" defer></script>
<script src="../assets/js/certificate-image-lib.js" defer></script>
<script src="../assets/js/certificate-image.js" defer></script>
<script src="../assets/js/certificate-view.js" defer></script>
</body>
</html>
