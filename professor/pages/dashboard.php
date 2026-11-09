<?php
require_once '../includes/init.php';
requireProfessor();

$professor = requireProfessorRecord($pdo);
$professorId = (int)$professor['id'];

$totalClasses = 0;
$totalStudents = 0;
$pendingGrades = 0;
$submittedSheets = 0;

// Total assigned section-subject pairs for this professor
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM section_subjects WHERE professor_id = ?');
$stmt->execute([$professorId]);
$totalClasses = (int)($stmt->fetchColumn() ?: 0);

// Distinct students across the professor's sections
$stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT ss.student_id) AS cnt
       FROM section_students ss
       JOIN section_subjects subj ON subj.section_id = ss.section_id
      WHERE subj.professor_id = ?'
);
$stmt->execute([$professorId]);
$totalStudents = (int)($stmt->fetchColumn() ?: 0);

// Pending grading sheets (not yet submitted)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS cnt
       FROM grading_sheets
      WHERE professor_id = ?
        AND status IN ('draft','reopened')"
);
$stmt->execute([$professorId]);
$pendingGrades = (int)($stmt->fetchColumn() ?: 0);

// Submitted / locked sheets
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS cnt
       FROM grading_sheets
      WHERE professor_id = ?
        AND status IN ('submitted','locked')"
);
$stmt->execute([$professorId]);
$submittedSheets = (int)($stmt->fetchColumn() ?: 0);

// Fetch the current teaching assignments
$stmt = $pdo->prepare(
    'SELECT ss.id AS assignment_id,
            sec.section_name,
            sec.year_level,
            sub.subject_code,
            sub.subject_title,
            t.term_name,
            gs.id AS grading_sheet_id
       FROM section_subjects ss
       JOIN sections sec ON sec.id = ss.section_id
       JOIN subjects sub ON sub.id = ss.subject_id
  LEFT JOIN terms t ON t.id = ss.term_id
  LEFT JOIN grading_sheets gs ON gs.section_subject_id = ss.id
      WHERE ss.professor_id = ?
   ORDER BY sec.section_name, sub.subject_title'
);
$stmt->execute([$professorId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Show only open grading sheets that have an actual deadline.
$stmt = $pdo->prepare(
    'SELECT gs.id,
            gs.deadline_at,
            gs.status,
        sec.section_name,
        sub.subject_code,
        sub.subject_title
       FROM grading_sheets gs
       JOIN sections sec ON sec.id = gs.section_id
  LEFT JOIN section_subjects assignment ON assignment.id = gs.section_subject_id
  LEFT JOIN subjects sub ON sub.id = assignment.subject_id
      WHERE gs.professor_id = ?
    AND gs.deadline_at IS NOT NULL
    AND gs.status IN (\'draft\', \'reopened\')
   ORDER BY (gs.deadline_at IS NULL), gs.deadline_at ASC
      LIMIT 5'
);
$stmt->execute([$professorId]);
$deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$announcementCsrfToken = (string)$_SESSION['csrf_token'];
$professorAnnouncements = [];
try {
    $announcementStmt = $pdo->prepare(
        "SELECT a.id, a.title, a.body, a.created_at, n.is_read,
                (SELECT COUNT(*) FROM announcement_reactions r WHERE r.announcement_id = a.id) AS reaction_count,
                EXISTS(SELECT 1 FROM announcement_reactions r WHERE r.announcement_id = a.id AND r.user_id = ?) AS viewer_reacted,
                (SELECT COUNT(*) FROM announcement_comments c WHERE c.announcement_id = a.id) AS comment_count
           FROM notifications n
           JOIN announcements a ON a.id = n.announcement_id
          WHERE n.user_id = ?
            AND n.type = 'announcement'
       ORDER BY a.created_at DESC, a.id DESC
          LIMIT 10"
    );
    $announcementStmt->execute([$currentUserId, $currentUserId]);
    $professorAnnouncements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $commentStmt = $pdo->prepare(
        "SELECT c.id, c.comment_text, c.created_at,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.email, 'Portal user') AS author_name
           FROM announcement_comments c
           JOIN users u ON u.id = c.user_id
          WHERE c.announcement_id = ?
       ORDER BY c.created_at DESC, c.id DESC
          LIMIT 3"
    );
    foreach ($professorAnnouncements as &$announcement) {
        $commentStmt->execute([(int)$announcement['id']]);
        $announcement['recent_comments'] = array_reverse($commentStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
    unset($announcement);
} catch (Throwable $error) {
    $professorAnnouncements = [];
}

function dueText(?string $date): string
{
    if (!$date) {
        return 'No deadline set';
    }

    $now = new DateTimeImmutable('now');
    $due = new DateTimeImmutable($date);

    if ($due < $now) {
        return 'Past due';
    }

    $diff = $now->diff($due);
    if ($diff->days === 0) {
        return 'Due today';
    }

    if ($diff->days <= 7) {
        return 'Due in ' . $diff->days . ' days';
    }

    return 'Due ' . $due->format('M j, Y');
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Professor Portal Dashboard</title>
        <link rel="stylesheet" href="../assets/css/professor.css?v=20261006.3">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    </head>
<body>
  <?php include '../includes/header.php'; ?>
<div class="layout"> 
  <?php include '../includes/sidebar.php'; ?>
  <main class="content">
    <?php show_flash(); ?>
    <div class="page-header professor-dashboard-header">
            <p class="page-kicker">PROFESSOR OVERVIEW</p>
            <h1>Teaching overview</h1>
            <p>Review your classes, student rosters, and grading deadlines.</p>
    </div>

    <section class="portal-announcements" aria-labelledby="professor-announcements-title" data-announcement-feed data-api="../../ajax/announcement_interactions.php" data-csrf="<?= htmlspecialchars($announcementCsrfToken, ENT_QUOTES); ?>">
          <div class="portal-announcements__header">
              <span class="material-symbols-rounded" aria-hidden="true">campaign</span>
              <div>
                  <h2 id="professor-announcements-title">From the administration</h2>
                  <p>Updates from PTC Academic Portal</p>
              </div>
              <span class="portal-announcements__count"><?= count($professorAnnouncements); ?></span>
          </div>
          <?php if (!$professorAnnouncements): ?>
              <p class="portal-announcements__empty">No announcements right now.</p>
          <?php else: ?>
              <div class="portal-announcements__feed">
                  <?php foreach ($professorAnnouncements as $announcement): ?>
                      <?php
                          $announcementTimestamp = strtotime((string)$announcement['created_at']);
                          $announcementDate = $announcementTimestamp ? date('M j, Y · g:i A', $announcementTimestamp) : '';
                          $announcementUnread = (int)($announcement['is_read'] ?? 0) === 0;
                      ?>
                      <article class="portal-announcement<?= $announcementUnread ? ' portal-announcement--unread' : ''; ?>" data-announcement-card data-announcement-id="<?= (int)$announcement['id']; ?>">
                          <div class="portal-announcement__author">
                              <span class="portal-announcement__avatar material-symbols-rounded" aria-hidden="true">campaign</span>
                              <div>
                                  <strong>PTC Academic Portal</strong>
                                  <?php if ($announcementDate !== ''): ?><time><?= htmlspecialchars($announcementDate); ?></time><?php endif; ?>
                              </div>
                              <?php if ($announcementUnread): ?><span class="portal-announcement__new">New</span><?php endif; ?>
                          </div>
                          <h3><?= htmlspecialchars($announcement['title']); ?></h3>
                          <p class="portal-announcement__body"><?= nl2br(htmlspecialchars($announcement['body'])); ?></p>
                          <div class="portal-announcement__actions">
                              <button type="button" class="portal-announcement__action<?= !empty($announcement['viewer_reacted']) ? ' is-active' : ''; ?>" data-reaction-toggle aria-pressed="<?= !empty($announcement['viewer_reacted']) ? 'true' : 'false'; ?>">
                                  <span class="material-symbols-rounded" aria-hidden="true">thumb_up</span>
                                  <span data-reaction-label><?= !empty($announcement['viewer_reacted']) ? 'Liked' : 'Like'; ?></span>
                                  <span class="portal-announcement__metric" data-reaction-count><?= (int)$announcement['reaction_count']; ?></span>
                              </button>
                              <button type="button" class="portal-announcement__action" data-comments-toggle aria-expanded="false" aria-controls="announcement-comments-<?= (int)$announcement['id']; ?>">
                                  <span class="material-symbols-rounded" aria-hidden="true">mode_comment</span>
                                  <span>Comment</span>
                                  <span class="portal-announcement__metric" data-comment-count><?= (int)$announcement['comment_count']; ?></span>
                              </button>
                          </div>
                          <div class="portal-announcement__comments" id="announcement-comments-<?= (int)$announcement['id']; ?>" data-comment-thread hidden>
                              <div class="portal-announcement__comment-list" data-comment-list>
                                  <?php foreach ($announcement['recent_comments'] as $comment): ?>
                                      <?php $commentTime = strtotime((string)$comment['created_at']); ?>
                                      <article class="portal-announcement__comment">
                                          <strong><?= htmlspecialchars($comment['author_name']); ?></strong>
                                          <p><?= nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                          <?php if ($commentTime): ?><time><?= htmlspecialchars(date('M j, g:i A', $commentTime)); ?></time><?php endif; ?>
                                      </article>
                                  <?php endforeach; ?>
                              </div>
                              <?php if ((int)$announcement['comment_count'] > count($announcement['recent_comments'])): ?>
                                  <button type="button" class="portal-announcement__load-comments" data-load-comments>View all <?= (int)$announcement['comment_count']; ?> comments</button>
                              <?php endif; ?>
                              <form class="portal-announcement__comment-form" data-comment-form>
                                  <label class="sr-only" for="announcement-comment-<?= (int)$announcement['id']; ?>">Write a comment</label>
                                  <textarea id="announcement-comment-<?= (int)$announcement['id']; ?>" name="comment" rows="1" maxlength="2000" placeholder="Write a comment..." required></textarea>
                                  <button type="submit" aria-label="Post comment" title="Post comment"><span class="material-symbols-rounded" aria-hidden="true">send</span></button>
                              </form>
                          </div>
                      </article>
                  <?php endforeach; ?>
              </div>
          <?php endif; ?>
      </section>

      <section class="dashboard-stats">
          <?php
              $statCards = [
                  ['label' => 'Total Classes', 'value' => $totalClasses, 'icon' => 'menu_book'],
                  ['label' => 'Total Students', 'value' => $totalStudents, 'icon' => 'groups_2'],
                  ['label' => 'Pending Grades', 'value' => $pendingGrades, 'icon' => 'schedule'],
                  ['label' => 'Submitted Sheets', 'value' => $submittedSheets, 'icon' => 'task_alt'],
              ];
          ?>
          <?php foreach ($statCards as $card): ?>
              <article class="stat-card">

                  <div class="stat-text">
                  <p class="stat-label"><?= htmlspecialchars($card['label']); ?></p>
                  <p class="stat-value"><?= (int)$card['value']; ?></p>
                  </div>

                  <div class="stat-icon">
                      <span class="material-symbols-rounded"><?= htmlspecialchars($card['icon']); ?></span>
                  </div>
              </article>
          <?php endforeach; ?>
      </section>

      <section class="dashboard-section">
          <div class="section-header">
              <h3>My Classes</h3>
              <a class="link-muted" href="./my_sections.php">View all</a>
          </div>
          <?php if (empty($classes)): ?>
              <div class="empty-state-card">
                  <p>No active teaching assignments yet.</p>
              </div>
          <?php else: ?>
              <div class="dashboard-classes">
                  <?php foreach (array_slice($classes, 0, 4) as $class): ?>
                      <?php
                          $title = trim(($class['subject_code'] ?? '') . ' - ' . ($class['section_name'] ?? ''));
                          $subtitle = $class['subject_title'] ?? '';
                          $meta = $class['term_name'] ?? '';
                          $sheetId = $class['grading_sheet_id'] ?? null;
                          $viewUrl = $sheetId ? './grading_sheet.php?sheet_id=' . (int)$sheetId : './grading_sheet.php';
                      ?>
                      <article class="class-pill">
                          <div>
                              <p class="class-pill__title"><?= htmlspecialchars($title ?: 'Assigned Class'); ?></p>
                              <?php if ($subtitle): ?>
                                  <p class="class-pill__subtitle"><?= htmlspecialchars($subtitle); ?></p>
                              <?php endif; ?>
                              <?php if ($meta): ?>
                                  <p class="class-pill__meta"><?= htmlspecialchars($meta); ?></p>
                              <?php endif; ?>
                          </div>
                          <a class="btn view" href="<?= htmlspecialchars($viewUrl); ?>">View</a>
                      </article>
                  <?php endforeach; ?>
              </div>
          <?php endif; ?>
      </section>

      <section class="dashboard-section">
          <div class="section-header">
              <h3>Due soon</h3>
          </div>
          <?php if (empty($deadlines)): ?>
              <div class="empty-state-card">
                  <p>No open grading sheets have deadlines right now.</p>
              </div>
          <?php else: ?>

                  <?php foreach ($deadlines as $d): ?>
                      <?php
                          $deadlineAt = $d['deadline_at'] ?? null;
                          $isOverdue = $deadlineAt ? (new DateTimeImmutable($deadlineAt) < new DateTimeImmutable()) : false;
                          $dueText = dueText($deadlineAt);
                          $status = ucfirst($d['status'] ?? 'draft');
                      ?>
                      <article class="deadline-card <?= $isOverdue ? 'deadline-card--warning' : 'deadline-card--info'; ?>">
                          <div>
                              <p class="deadline-card__title">
                                  <?= htmlspecialchars(trim(($d['subject_code'] ?? '') . ' ' . ($d['subject_title'] ?? '')) ?: 'Grading sheet'); ?>
                              </p>
                              <p class="deadline-card__meta"><?= htmlspecialchars($d['section_name'] ?? 'Section'); ?></p>
                              <p class="deadline-card__meta">Status: <?= htmlspecialchars($status); ?></p>
                          </div>
                          <div class="deadline-card__actions">
                              <p class="deadline-card__due"><?= htmlspecialchars($dueText); ?></p>
                              <a class="deadline-card__action" href="./grading_sheet.php?sheet_id=<?= (int)$d['id']; ?>">Open sheet</a>
                          </div>
                      </article>
                  <?php endforeach; ?>

          <?php endif; ?>
      </section>
  </main>
</div>
<script src="../../core/assets/announcement-interactions.js?v=20261109.1" defer></script>
<script src="../assets/js/professor.js?v=20260916.1"></script>
</body>
<?php include '../includes/footer.php'; ?>
</html>
