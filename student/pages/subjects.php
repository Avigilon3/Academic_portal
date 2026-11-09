<?php
require_once '../includes/init.php';
requireStudent();

// 1. Get the current logged-in student's ID (from the students table)
$user_id = $_SESSION['user']['id'];
$student_id = getStudentIDByUserID($pdo, $user_id);

// If the student ID isn't found, stop and display an error.
if (!$student_id) {
    echo "Error: Student information not found.";
    exit;
}

// 2. Function to get the student's subjects (Enrolled and Archived)
function getStudentSubjects(PDO $pdo, int $student_id): array
{

    $sql = "
        SELECT
            t.term_name,
            t.is_active AS term_is_active,
            s.subject_code,
            s.subject_title,
            s.is_active AS subject_is_active,
            sec.section_name,
            sec.id AS section_id,
            ssub.id AS section_subject_id,
            CONCAT(p.first_name, ' ', p.last_name) AS professor_name
        FROM
            section_students ss
        INNER JOIN section_subjects ssub ON ss.section_id = ssub.section_id
        INNER JOIN sections sec ON ss.section_id = sec.id
        INNER JOIN subjects s ON ssub.subject_id = s.id
        LEFT JOIN terms t ON ssub.term_id = t.id
        LEFT JOIN professors p ON ssub.professor_id = p.id
        WHERE
            ss.student_id = :student_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['student_id' => $student_id]);
    return $stmt->fetchAll();
}

// Helper to get the student's ID (from the students table) using their logged-in user ID (from the users table)
function getStudentIDByUserID(PDO $pdo, int $user_id): ?int
{
    $sql = "SELECT id FROM students WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetchColumn();
    return $result !== false ? (int)$result : null;
}

// 3. Fetch all subjects and categorize them
$all_subjects = getStudentSubjects($pdo, $student_id);

$enrolled_subjects = [];
$archived_subjects = [];

foreach ($all_subjects as $subject) {
    $subjectIsActive = isset($subject['subject_is_active']) ? (int)$subject['subject_is_active'] === 1 : true;
    $termFlag = $subject['term_is_active'] ?? null;
    $termIsActive = $termFlag === null ? true : ((int)$termFlag === 1);

    if ($subjectIsActive && $termIsActive) {
        $enrolled_subjects[] = $subject;
    } else {
        $archived_subjects[] = $subject;
    }
}

$subjectGroups = [
    ['title' => 'Current Classes', 'subjects' => $enrolled_subjects, 'archived' => false],
    ['title' => 'Archived Classes', 'subjects' => $archived_subjects, 'archived' => true],
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects</title>
    <link rel="stylesheet" href="../assets/css/student.css?v=20261006.1">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <div class="layout">
        <?php include '../includes/sidebar.php'; ?>
        <main class="content">
            <div class="page-header">
              <h1>Subjects</h1>
              <p class="text-muted">
                View all your enrolled subjects and current grades.
              </p>
            </div>

                        <label class="search-subjects">
              <span class="material-symbols-rounded">search</span>
                            <input type="search" placeholder="Search classes or professors" aria-label="Search classes" data-subject-search>
                        </label>

                        <?php foreach ($subjectGroups as $group): ?>
                            <section class="subject-group<?= $group['archived'] ? ' subject-group--archived' : ''; ?>" aria-labelledby="<?= $group['archived'] ? 'archived-classes-heading' : 'current-classes-heading'; ?>">
                                <div class="subject-group__heading">
                                    <h2 id="<?= $group['archived'] ? 'archived-classes-heading' : 'current-classes-heading'; ?>"><?= htmlspecialchars($group['title']); ?></h2>
                                    <span><?= count($group['subjects']); ?> <?= count($group['subjects']) === 1 ? 'class' : 'classes'; ?></span>
                                </div>

                                <?php if (empty($group['subjects'])): ?>
                                    <div class="subject-empty-state">
                                        <span class="material-symbols-rounded" aria-hidden="true"><?= $group['archived'] ? 'inventory_2' : 'menu_book'; ?></span>
                                        <p><?= $group['archived'] ? 'No archived classes.' : 'No classes are assigned for an active term yet.'; ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="subject-card-grid">
                                        <?php foreach ($group['subjects'] as $subject): ?>
                                            <?php
                                                $sectionId = (int)($subject['section_id'] ?? 0);
                                                $sectionSubjectId = (int)($subject['section_subject_id'] ?? 0);
                                                $subjectTitle = trim((string)($subject['subject_title'] ?? ''));
                                                $subjectCode = trim((string)($subject['subject_code'] ?? ''));
                                                $professorName = trim((string)($subject['professor_name'] ?? '')) ?: 'To be assigned';
                                                $termName = trim((string)($subject['term_name'] ?? '')) ?: 'Term not set';
                                                $sectionName = trim((string)($subject['section_name'] ?? '')) ?: 'Section not set';
                                                $gradeInfo = $sectionId ? computeStudentGradeForSection($pdo, $student_id, $sectionId, $sectionSubjectId ?: null) : null;
                                                if (!empty($gradeInfo['equivalent'])) {
                                                        $gradeDisplay = (string)$gradeInfo['equivalent'];
                                                } elseif (isset($gradeInfo['final_grade_display']) && $gradeInfo['final_grade_display'] !== null) {
                                                        $gradeDisplay = number_format((float)$gradeInfo['final_grade_display'], 2) . '%';
                                                } else {
                                                        $gradeDisplay = 'Pending';
                                                }
                                                $searchText = strtolower(implode(' ', [$subjectCode, $subjectTitle, $sectionName, $professorName, $termName]));
                                            ?>
                                            <article class="subject-card<?= $group['archived'] ? ' subject-card--archived' : ''; ?>" data-subject-card data-search="<?= htmlspecialchars($searchText, ENT_QUOTES); ?>">
                                                <div class="subject-card__banner" aria-hidden="true">
                                                    <span class="material-symbols-rounded"><?= $group['archived'] ? 'archive' : 'auto_stories'; ?></span>
                                                    <span class="subject-card__code"><?= htmlspecialchars($subjectCode ?: 'SUBJECT'); ?></span>
                                                </div>
                                                <div class="subject-card__content">
                                                    <div class="subject-card__title-row">
                                                        <div>
                                                            <h3><?= htmlspecialchars($subjectTitle ?: $subjectCode ?: 'Subject'); ?></h3>
                                                            <p class="subject-card__section"><?= htmlspecialchars($sectionName); ?></p>
                                                        </div>
                                                        <span class="subject-grade<?= $gradeDisplay === 'Pending' ? ' subject-grade--pending' : ''; ?>">
                                                            <span class="subject-grade__label">Grade</span>
                                                            <?= htmlspecialchars($gradeDisplay); ?>
                                                        </span>
                                                    </div>
                                                    <dl class="subject-card__details">
                                                        <div><dt>Professor</dt><dd><?= htmlspecialchars($professorName); ?></dd></div>
                                                        <div><dt>Term</dt><dd><?= htmlspecialchars($termName); ?></dd></div>
                                                    </dl>
                                                </div>
                                                <div class="subject-card__footer">
                                                    <span class="subject-card__status"><?= $group['archived'] ? 'Archived' : 'Enrolled'; ?></span>
                                                    <button
                                                        type="button"
                                                        class="subject-card__action"
                                                        data-grade-view
                                                        data-subject="<?= htmlspecialchars($subjectTitle ?: $subjectCode, ENT_QUOTES); ?>"
                                                        data-professor="<?= htmlspecialchars($professorName, ENT_QUOTES); ?>"
                                                        data-section-id="<?= $sectionId; ?>"
                                                        data-section-subject-id="<?= $sectionSubjectId; ?>"
                                                        <?= $sectionId ? '' : 'disabled'; ?>
                                                    >View grade details <span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></button>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="subject-search-empty" data-subject-search-empty hidden>No classes match your search.</p>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>

            <div class="grade-modal" data-grade-modal hidden>
                <div class="grade-modal__backdrop" data-grade-modal-close></div>
                <div class="grade-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="grade-modal-title">
                    <div class="grade-modal__header">
                        <div>
                            <h3 id="grade-modal-title" data-grade-modal-title>Grading Sheet</h3>
                            <p class="grade-modal__professor" data-grade-modal-professor></p>
                        </div>
                        <button type="button" class="grade-modal__close" data-grade-modal-close aria-label="Close grading sheet">&times;</button>
                    </div>
                    <div class="grade-modal__body" data-grade-modal-body>
                        <p class="text-muted">Select a subject to view how your grade was computed.</p>
                    </div>
                </div>
            </div>
        </main>

    </div>

    
</body>
<script src="../assets/js/student.js?v=20261006.1"></script>
</html>
