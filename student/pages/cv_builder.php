<?php
require_once '../includes/init.php';
require_once '../../includes/services/ResumeService.php';
requireStudent();

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . BASE_URL . '/login.php?session=expired');
    exit;
}

$service = new ResumeService($pdo);
$error = '';
$success = '';
$resumeData = null;

try {
    $resumeData = $service->getResumeData($userId);
    if ($resumeData === null) {
        http_response_code(404);
        $error = 'Student information was not found for this account.';
    }

    if ($resumeData !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $service->saveDraft($userId, $_POST);
        $success = 'Your resume draft has been saved.';
        $resumeData = $service->getResumeData($userId);
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('CV builder error: ' . $exception->getMessage());
    http_response_code(500);
    $error = 'The resume could not be loaded or saved. Please try again.';
}

$profile = $resumeData['profile'] ?? [];
$draft = $resumeData['draft'] ?? [
    'professional_summary' => '',
    'skills' => [],
    'experience' => [],
    'portfolio_links' => [],
];
$completedSubjects = $resumeData['completed_subjects'] ?? [];
$valueFor = static function (string $key, string $draftValue): string {
    return $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key]) && is_string($_POST[$key])
        ? $_POST[$key]
        : $draftValue;
};
$linesFor = static function (array $items): string {
    return implode("\n", array_map('strval', $items));
};
$summaryValue = $valueFor('professional_summary', (string)($draft['professional_summary'] ?? ''));
$skillsValue = $valueFor('skills', $linesFor($draft['skills'] ?? []));
$experienceValue = $valueFor('experience', $linesFor($draft['experience'] ?? []));
$linksValue = $valueFor('portfolio_links', $linesFor($draft['portfolio_links'] ?? []));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CV Builder</title>
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
                <h1>CV Builder</h1>
                <p>Start with your student record and completed subjects, then add your own experience and skills.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="tools-message is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($success !== ''): ?>
                <div class="tools-message" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($resumeData !== null): ?>
                <div class="cv-builder-grid">
                    <section class="tools-card" aria-labelledby="cv-editor-title">
                        <h2 id="cv-editor-title">Resume details</h2>
                        <form class="cv-form" method="post" data-cv-form>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <label>
                                Professional summary
                                <textarea name="professional_summary" maxlength="5000" data-preview-summary><?= htmlspecialchars($summaryValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </label>
                            <label>
                                Technical and soft skills
                                <textarea name="skills" maxlength="3600" placeholder="One skill per line" data-preview-lines="skills"><?= htmlspecialchars($skillsValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <span class="tools-hint">Enter up to 30 skills, one per line.</span>
                            </label>
                            <label>
                                Projects and experience
                                <textarea name="experience" maxlength="24000" placeholder="One project or experience entry per line" data-preview-lines="experience"><?= htmlspecialchars($experienceValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <span class="tools-hint">Enter up to 20 entries, one per line.</span>
                            </label>
                            <label>
                                Portfolio, LinkedIn, or GitHub links
                                <textarea name="portfolio_links" maxlength="7500" placeholder="https://example.com/profile" data-preview-lines="links"><?= htmlspecialchars($linksValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <span class="tools-hint">Use a full HTTP or HTTPS URL, one per line.</span>
                            </label>
                            <p class="tools-hint">Your email, student number, course, year level, and passed subjects are read from your academic record. Trophy-case achievements are not available because no achievement table is configured.</p>
                            <div class="tools-actions">
                                <button class="btn solid" type="submit">Save resume</button>
                                <button class="btn ghost" type="submit" formaction="cv_print.php" formtarget="_blank">Save &amp; Print CV</button>
                            </div>
                        </form>
                    </section>

                    <section class="tools-card" aria-labelledby="cv-preview-title">
                        <h2 id="cv-preview-title">Live preview</h2>
                        <article class="resume-preview" aria-label="Resume preview">
                            <h2><?= htmlspecialchars((string)($profile['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="resume-contact">
                                <?= htmlspecialchars((string)($profile['email'] ?? 'Email not available'), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($profile['student_id'])): ?>
                                    · <?= htmlspecialchars((string)$profile['student_id'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </p>
                            <p><?= htmlspecialchars((string)($profile['course_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><?= !empty($profile['year_label']) ? ' · ' . htmlspecialchars((string)$profile['year_label'], ENT_QUOTES, 'UTF-8') : ''; ?></p>
                            <section class="resume-section">
                                <h3>Professional Summary</h3>
                                <p data-preview-summary-output><?= nl2br(htmlspecialchars($summaryValue, ENT_QUOTES, 'UTF-8')); ?></p>
                            </section>
                            <section class="resume-section">
                                <h3>Skills</h3>
                                <ul data-preview-skills>
                                    <?php foreach (preg_split('/\r\n|\r|\n/', $skillsValue) as $skill): ?>
                                        <?php if (trim($skill) !== ''): ?><li><?= htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                            <section class="resume-section">
                                <h3>Projects &amp; Experience</h3>
                                <div data-preview-experience>
                                    <?php foreach (preg_split('/\r\n|\r|\n/', $experienceValue) as $entry): ?>
                                        <?php if (trim($entry) !== ''): ?><p><?= nl2br(htmlspecialchars(trim($entry), ENT_QUOTES, 'UTF-8')); ?></p><?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <section class="resume-section">
                                <h3>Portfolio Links</h3>
                                <ul data-preview-links>
                                    <?php foreach (preg_split('/\r\n|\r|\n/', $linksValue) as $link): ?>
                                        <?php if (trim($link) !== ''): ?><li><?= htmlspecialchars(trim($link), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
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
                        </article>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="../assets/js/student.js?v=20260916.7"></script>
<script src="../assets/js/cv-builder.js" defer></script>
</body>
</html>
