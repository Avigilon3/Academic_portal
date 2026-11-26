<?php
require_once '../includes/init.php';
require_once '../../includes/services/CertificateService.php';
require_once '../../includes/services/ResumeService.php';
requireStudent();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$resumeService = new ResumeService($pdo);
$certificateService = new CertificateService($pdo);
$certificates = [];
$error = '';

try {
    $studentId = $resumeService->getStudentIdForUser($userId);
    if (!$studentId) {
        http_response_code(404);
        $error = 'Student information was not found for this account.';
    } else {
        $certificates = $certificateService->getCertificatesForStudent($studentId);
    }
} catch (Throwable $exception) {
    error_log('E-certificate gallery error: ' . $exception->getMessage());
    http_response_code(500);
    $error = 'Your certificates could not be loaded. Please try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Certificates</title>
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/student-tools.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="layout">
    <?php include '../includes/sidebar.php'; ?>
    <main class="content">
        <div class="student-tools-page">
            <div class="student-tools-heading">
                <h1>E-Certificates</h1>
                <p>Certificates are issued for passed subjects with a locked, released grading sheet.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="tools-message is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="tools-message" role="note">Course-completion certificates are not issued until the Registrar defines an approved course-completion rule.</div>

            <?php if ($certificates): ?>
                <section class="certificate-grid" aria-label="Earned certificates">
                    <?php foreach ($certificates as $certificate): ?>
                        <?php
                        $issuedAt = strtotime((string)$certificate['issued_at']);
                        $issuedLabel = $issuedAt ? date('F j, Y', $issuedAt) : (string)$certificate['issued_at'];
                        $unitValue = rtrim(rtrim(number_format((float)$certificate['units_completed'], 1), '0'), '.');
                        ?>
                        <article class="certificate-card">
                            <h2><?= htmlspecialchars((string)$certificate['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p><?= htmlspecialchars($certificate['certificate_type'] === 'course_completion' ? 'Course completion' : 'Subject completion', ENT_QUOTES, 'UTF-8'); ?><?= !empty($certificate['term_name']) ? ' · ' . htmlspecialchars((string)$certificate['term_name'], ENT_QUOTES, 'UTF-8') : ''; ?></p>
                            <p><?= htmlspecialchars($unitValue, ENT_QUOTES, 'UTF-8'); ?> units · Issued <?= htmlspecialchars($issuedLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="certificate-code"><?= htmlspecialchars((string)$certificate['certificate_code'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="tools-actions">
                                <a class="btn solid" href="certificate_view.php?id=<?= (int)$certificate['id']; ?>">View / Download</a>
                                <a class="btn ghost" href="../../verify_certificate.php?code=<?= rawurlencode((string)$certificate['certificate_code']); ?>">Verify</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php elseif ($error === ''): ?>
                <section class="tools-card">
                    <h2>No E-Certificates yet</h2>
                    <p class="tools-hint">A subject certificate appears after its grading sheet is locked and the computed final grade is at least 75.</p>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="../assets/js/student.js?v=20260916.7"></script>
</body>
</html>
