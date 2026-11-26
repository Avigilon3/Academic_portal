<?php
require_once '../includes/init.php';
require_once '../../includes/services/ResumeService.php';
requireStudent();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$service = new ResumeService($pdo);

if ($userId <= 0) {
    header('Location: ' . BASE_URL . '/login.php?session=expired');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $service->saveDraft($userId, $_POST);
    }
    $resumeData = $service->getResumeData($userId);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    exit('Resume details are invalid: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
} catch (Throwable $exception) {
    error_log('CV print error: ' . $exception->getMessage());
    http_response_code(500);
    exit('The resume could not be loaded. Please try again.');
}

if ($userId <= 0 || $resumeData === null) {
    http_response_code(404);
    exit('Student information was not found.');
}

$profile = $resumeData['profile'];
$draft = $resumeData['draft'];
$completedSubjects = $resumeData['completed_subjects'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)$profile['full_name'], ENT_QUOTES, 'UTF-8'); ?> - Resume</title>
    <link rel="stylesheet" href="../assets/css/student-tools.css">
</head>
<body>
<main class="document-page">
    <div class="document-actions">
        <a href="cv_builder.php">Back to editor</a>
        <button type="button" onclick="window.print()">Download PDF / Print CV</button>
    </div>
    <article class="resume-sheet">
        <div class="resume-preview">
            <h2><?= htmlspecialchars((string)$profile['full_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="resume-contact">
                <?= htmlspecialchars((string)($profile['email'] ?: 'Email not available'), ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($profile['student_id'])): ?>
                    · <?= htmlspecialchars((string)$profile['student_id'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </p>
            <p><?= htmlspecialchars((string)($profile['course_title'] ?: 'Course not assigned'), ENT_QUOTES, 'UTF-8'); ?><?= !empty($profile['year_label']) ? ' · ' . htmlspecialchars((string)$profile['year_label'], ENT_QUOTES, 'UTF-8') : ''; ?></p>

            <?php if (trim((string)$draft['professional_summary']) !== ''): ?>
                <section class="resume-section">
                    <h3>Professional Summary</h3>
                    <p><?= nl2br(htmlspecialchars((string)$draft['professional_summary'], ENT_QUOTES, 'UTF-8')); ?></p>
                </section>
            <?php endif; ?>

            <?php if (!empty($draft['skills'])): ?>
                <section class="resume-section">
                    <h3>Skills</h3>
                    <ul>
                        <?php foreach ($draft['skills'] as $skill): ?>
                            <li><?= htmlspecialchars((string)$skill, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!empty($draft['experience'])): ?>
                <section class="resume-section">
                    <h3>Projects &amp; Experience</h3>
                    <?php foreach ($draft['experience'] as $entry): ?>
                        <p><?= nl2br(htmlspecialchars((string)$entry, ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($draft['portfolio_links'])): ?>
                <section class="resume-section">
                    <h3>Portfolio Links</h3>
                    <ul>
                        <?php foreach ($draft['portfolio_links'] as $link): ?>
                            <li><a href="<?= htmlspecialchars((string)$link, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener"><?= htmlspecialchars((string)$link, ENT_QUOTES, 'UTF-8'); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="resume-section">
                <h3>Completed Subjects</h3>
                <?php if ($completedSubjects): ?>
                    <table class="resume-subject-table">
                        <thead><tr><th>Subject</th><th>Units</th><th>Grade</th></tr></thead>
                        <tbody>
                        <?php foreach ($completedSubjects as $subject): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$subject['subject_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars(rtrim(rtrim(number_format((float)$subject['units'], 1), '0'), '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars(number_format((float)$subject['final_grade'], 2) . '%', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No passed and released subjects are available yet.</p>
                <?php endif; ?>
            </section>
        </div>
    </article>
</main>
</body>
</html>
