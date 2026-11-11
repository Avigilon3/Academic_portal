<?php
require_once '../includes/init.php';
requireAdmin();
require_once '../../core/mail/mailer.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_announcement') {
  $submittedToken = $_POST['csrf_token'] ?? '';
  if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
    set_flash('error', 'Your session expired. Refresh the dashboard and try again.');
    header('Location: dashboard.php');
    exit;
  }

  $announcementTitle = trim(str_replace(["\r", "\n"], ' ', (string)($_POST['announcement_title'] ?? '')));
  $announcementBody = trim((string)($_POST['announcement_body'] ?? ''));

  $titleLength = function_exists('mb_strlen') ? mb_strlen($announcementTitle) : strlen($announcementTitle);
  $bodyLength = function_exists('mb_strlen') ? mb_strlen($announcementBody) : strlen($announcementBody);
  if ($announcementTitle === '' || $titleLength > 120 || $announcementBody === '' || $bodyLength > 5000) {
    set_flash('error', 'Enter a title up to 120 characters and announcement text up to 5,000 characters.');
    header('Location: dashboard.php');
    exit;
  }

  try {
    $recipientStmt = $pdo->query(
      "SELECT id, email
         FROM users
        WHERE role IN ('student', 'professor')
          AND status = 'ACTIVE'
        ORDER BY id"
    );
    $announcementRecipients = $recipientStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$announcementRecipients) {
      set_flash('warning', 'There are no active student or professor accounts to notify.');
      header('Location: dashboard.php');
      exit;
    }

    $announcementMessage = 'Announcement: ' . $announcementTitle . "\n\n" . $announcementBody;
    $pdo->beginTransaction();
    $postStmt = $pdo->prepare(
      'INSERT INTO announcements (title, body, created_by) VALUES (?, ?, ?)'
    );
    $postStmt->execute([
      $announcementTitle,
      $announcementBody,
      (int)($_SESSION['user']['id'] ?? 0),
    ]);
    $announcementId = (int)$pdo->lastInsertId();
    $notificationStmt = $pdo->prepare(
      'INSERT INTO notifications (user_id, type, message, is_read, announcement_id) VALUES (?, ?, ?, 0, ?)'
    );
    foreach ($announcementRecipients as $recipient) {
      $notificationStmt->execute([(int)$recipient['id'], 'announcement', $announcementMessage, $announcementId]);
    }
    $pdo->commit();

    $recipientEmails = array_column($announcementRecipients, 'email');
    $emailSent = send_app_email_to_many(
      $recipientEmails,
      'Academic Portal announcement: ' . $announcementTitle,
      $announcementMessage . "\n\nYou received this message because you have an active Academic Portal account."
    );

    if ($emailSent) {
      set_flash('success', 'Announcement posted to ' . count($announcementRecipients) . ' active student and professor accounts; email was accepted by the configured mail server.');
    } else {
      set_flash('warning', 'Announcement posted to ' . count($announcementRecipients) . ' active student and professor accounts in the portal. Email was not sent; SMTP must be configured to deliver email.');
    }
  } catch (Throwable $error) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('Announcement publishing failed: ' . $error->getMessage());
    set_flash('error', 'The announcement could not be published. Please try again or check the application log.');
  }

  header('Location: dashboard.php');
  exit;
}

// Optional: CSV download for the submissions chart
function outputSubmissionsCsv(PDO $pdo): void {
  try {
    $today  = new DateTimeImmutable('today');
    $start  = $today->sub(new DateInterval('P6D'));
    $end    = $today->add(new DateInterval('P1D'));

    $stmt = $pdo->prepare(
      "SELECT DATE(submitted_at) d, COUNT(*) c
       FROM grading_sheets
       WHERE status='submitted' AND submitted_at >= :start AND submitted_at < :end
       GROUP BY DATE(submitted_at)"
    );
    $stmt->execute([
      ':start' => $start->format('Y-m-d 00:00:00'),
      ':end'   => $end->format('Y-m-d 00:00:00'),
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['Y-m-d' => count]

    // Build 7-day data
    $data = [];
    for ($i = 0; $i < 7; $i++) {
      $d = $start->add(new DateInterval('P' . $i . 'D'));
      $key = $d->format('Y-m-d');
      $data[] = [$key, (int)($rows[$key] ?? 0)];
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="grading_submissions_last7days.csv"');
    echo "Date,Count\n";
    foreach ($data as $row) {
      echo $row[0] . ',' . $row[1] . "\n";
    }
  } catch (Throwable $e) {
    header('Content-Type: text/plain', true, 500);
    echo 'Failed to generate CSV.';
  }
  exit;
}

if (isset($_GET['download']) && $_GET['download'] === 'submissions_csv') {
  outputSubmissionsCsv($pdo);
}

// KPI stats for dashboard widgets
try {
  $stats = [
    'students'        => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'professors'      => (int)$pdo->query('SELECT COUNT(*) FROM professors')->fetchColumn(),
    'sections'        => (int)$pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn(),
    'submittedSheets' => (int)$pdo->query("SELECT COUNT(*) FROM grading_sheets WHERE status='submitted'")->fetchColumn(),
    'totalSheets'     => (int)$pdo->query('SELECT COUNT(*) FROM grading_sheets')->fetchColumn(),
    'pendingRequests' => (int)$pdo->query("SELECT COUNT(*) FROM edit_requests WHERE status='pending'")->fetchColumn(),
    'pendingDocumentRequests' => (int)$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status='pending'")->fetchColumn(),
  ];
} catch (Throwable $e) {
  error_log('Admin dashboard metrics failed: ' . $e->getMessage());
  $stats = [
    'students' => 0,
    'professors' => 0,
    'sections' => 0,
    'submittedSheets' => 0,
    'totalSheets' => 0,
    'pendingRequests' => 0,
    'pendingDocumentRequests' => 0,
  ];
}
$stats['submissionRate'] = $stats['totalSheets'] > 0
  ? (int)round(($stats['submittedSheets'] / $stats['totalSheets']) * 100)
  : 0;
$pendingActionCount = $stats['pendingRequests'] + $stats['pendingDocumentRequests'];

// Build last-7-days submission stats and WoW delta
$chart = [
  'labels' => [],
  'submitted' => [],
  'pending' => [],
  'total'  => 0,
  'deltaPct' => 0.0,
  'max' => 1,
];

try {
  $today = new DateTimeImmutable('today');
  $start = $today->sub(new DateInterval('P6D'));
  $end   = $today->add(new DateInterval('P1D')); // exclusive upper bound

  // Current 7 days grouped by date
  $stmt = $pdo->prepare(
    "SELECT DATE(submitted_at) d, COUNT(*) c
     FROM grading_sheets
     WHERE status='submitted' AND submitted_at >= :start AND submitted_at < :end
     GROUP BY DATE(submitted_at)"
  );
  $stmt->execute([
    ':start' => $start->format('Y-m-d 00:00:00'),
    ':end'   => $end->format('Y-m-d 00:00:00'),
  ]);
  $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['Y-m-d' => count]

  $pendingStmt = $pdo->prepare(
    "SELECT DATE(deadline_at) d, COUNT(*) c
       FROM grading_sheets
      WHERE status IN ('draft','reopened')
        AND deadline_at IS NOT NULL
        AND deadline_at >= :start
        AND deadline_at < :end
      GROUP BY DATE(deadline_at)"
  );
  $pendingStmt->execute([
    ':start' => $start->format('Y-m-d 00:00:00'),
    ':end'   => $end->format('Y-m-d 00:00:00'),
  ]);
  $pendingRows = $pendingStmt->fetchAll(PDO::FETCH_KEY_PAIR);

  $maxCount = 0;
  for ($i = 0; $i < 7; $i++) {
    $d = $start->add(new DateInterval('P' . $i . 'D'));
    $key = $d->format('Y-m-d');
    $submittedCnt = (int)($rows[$key] ?? 0);
    $pendingCnt = (int)($pendingRows[$key] ?? 0);
    $chart['labels'][] = $d->format('D');
    $chart['submitted'][] = $submittedCnt;
    $chart['pending'][] = $pendingCnt;
    $chart['total'] += $submittedCnt;
    $maxCount = max($maxCount, $submittedCnt, $pendingCnt);
  }
  $chart['max'] = max(1, $maxCount);

  // Previous 7 days (for delta)
  $prevStart = $start->sub(new DateInterval('P7D'));
  $prevEnd   = $start; // exclusive
  $stmt2 = $pdo->prepare(
    "SELECT COUNT(*) FROM grading_sheets
     WHERE status='submitted' AND submitted_at >= :start AND submitted_at < :end"
  );
  $stmt2->execute([
    ':start' => $prevStart->format('Y-m-d 00:00:00'),
    ':end'   => $prevEnd->format('Y-m-d 00:00:00'),
  ]);
  $prevTotal = (int)$stmt2->fetchColumn();
  if ($prevTotal > 0) {
    $chart['deltaPct'] = (($chart['total'] - $prevTotal) / $prevTotal) * 100.0;
  } else {
    $chart['deltaPct'] = $chart['total'] > 0 ? 100.0 : 0.0;
  }
} catch (Throwable $e) {
  error_log('Admin dashboard submission chart failed: ' . $e->getMessage());
}

$recentAnnouncements = [];
$announcementAudience = ['student' => [], 'professor' => []];
$recentActivity = [];
$recentActivityError = false;
try {
  $activityStmt = $pdo->query(
    "SELECT a.action, a.details, a.created_at,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''), 'System') AS actor
       FROM activity_logs a
       LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT 5"
  );
  $recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $error) {
  error_log('Admin dashboard recent activity failed: ' . $error->getMessage());
  $recentActivityError = true;
}
try {
  $announcementStmt = $pdo->query(
     "SELECT title, body, created_at AS published_at
       FROM announcements
    ORDER BY created_at DESC, id DESC
      LIMIT 5"
  );
  $recentAnnouncements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $error) {
  $recentAnnouncements = [];
}
try {
  $audienceStmt = $pdo->query(
    "SELECT role,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)), ''), NULLIF(email, ''), 'Portal user') AS display_name,
            email
       FROM users
      WHERE role IN ('student', 'professor')
        AND status = 'ACTIVE'
   ORDER BY role, last_name, first_name, email"
  );
  foreach ($audienceStmt->fetchAll(PDO::FETCH_ASSOC) as $audienceRow) {
    if (isset($announcementAudience[$audienceRow['role']])) {
      $announcementAudience[$audienceRow['role']][] = $audienceRow;
    }
  }
} catch (Throwable $error) {
  $announcementAudience = ['student' => [], 'professor' => []];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261111.1">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  </head>
<body>
<?php include '../includes/header.php'; ?>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="content">
    <?php show_flash(); ?>
    <div class="page-header">
      <span class="page-kicker">ADMIN OVERVIEW</span>
      <h1>Dashboard</h1>
      <p class="text-muted">Welcome, <?= htmlspecialchars(adminCurrentName()); ?>. Here's what's happening with your grading system.</p>
    </div>

    <?php
        $dashboardCards = [
          ['label' => 'Total Students', 'value' => $stats['students'], 'detail' => 'Student records in the portal', 'icon' => 'school'],
          ['label' => 'Total Professors', 'value' => $stats['professors'], 'detail' => 'Professor records in the portal', 'icon' => 'group'],
          ['label' => 'Total Sections', 'value' => $stats['sections'], 'detail' => 'Academic sections configured', 'icon' => 'class'],
          ['label' => 'Submitted Sheets', 'value' => $stats['submittedSheets'], 'detail' => 'Grading sheets submitted', 'icon' => 'task_alt'],
          ['label' => 'Pending Requests', 'value' => $pendingActionCount, 'detail' => 'Document and edit-access requests', 'icon' => 'pending_actions'],
          ['label' => 'Submission Rate', 'value' => $stats['submissionRate'] . '%', 'detail' => $stats['submittedSheets'] . ' of ' . $stats['totalSheets'] . ' sheets submitted', 'icon' => 'donut_small'],
        ];
    ?>
    <section class="dashboard-stats">
      <?php foreach ($dashboardCards as $card): ?>
        <article class="stat-card">
          <div class="stat-text">
            <p class="stat-label"><?= htmlspecialchars($card['label']); ?></p>
            <p class="stat-value" data-tabular-nums><?= htmlspecialchars((string)$card['value'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="stat-detail"><?= htmlspecialchars($card['detail'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="stat-icon">
            <span class="material-symbols-rounded"><?= htmlspecialchars($card['icon']); ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <div class="dashboard-section-heading">
      <h2 class="page-kicker">QUICK ACTIONS</h2>
    </div>
    <section class="dashboard-quick-links" aria-label="Quick access">
      <a class="dashboard-quick-link" href="./report_management.php">
        <span class="dashboard-quick-link__icon material-symbols-rounded" aria-hidden="true">pending_actions</span>
        <span><strong>Review requests</strong><small>Handle pending professor and document requests</small></span>
        <span class="dashboard-quick-link__end">
          <?php if ($pendingActionCount > 0): ?><span class="dashboard-quick-link__badge"><?= (int)$pendingActionCount; ?></span><?php endif; ?>
          <span class="material-symbols-rounded dashboard-quick-link__arrow" aria-hidden="true">arrow_forward</span>
        </span>
      </a>
      <a class="dashboard-quick-link" href="./database_management.php">
        <span class="dashboard-quick-link__icon dashboard-quick-link__icon--blue material-symbols-rounded" aria-hidden="true">database</span>
        <span><strong>Manage records</strong><small>Maintain student, professor, subject, and section data</small></span>
        <span class="material-symbols-rounded dashboard-quick-link__arrow" aria-hidden="true">arrow_forward</span>
      </a>
      <a class="dashboard-quick-link" href="./reports.php">
        <span class="dashboard-quick-link__icon dashboard-quick-link__icon--gold material-symbols-rounded" aria-hidden="true">summarize</span>
        <span><strong>View reports</strong><small>Open academic and grading summaries</small></span>
        <span class="material-symbols-rounded dashboard-quick-link__arrow" aria-hidden="true">arrow_forward</span>
      </a>
    </section>

    <section class="announcement-center announcement-center--feed" aria-labelledby="announcement-center-title">
      <div class="announcement-center__intro">
        <span class="announcement-center__icon material-symbols-rounded" aria-hidden="true">campaign</span>
        <div>
          <p class="page-kicker">COMMUNITY COMMUNICATION</p>
          <h2 id="announcement-center-title">Announcements</h2>
          <p>Publish one update across both academic portals.</p>
        </div>
        <div class="announcement-audience" aria-label="Announcement audience">
          <span class="announcement-audience__label">Active audience</span>
          <?php foreach (['student' => 'students', 'professor' => 'professors'] as $audienceRole => $audienceLabel): ?>
            <?php $audienceId = 'announcement-audience-' . $audienceRole; ?>
            <div class="announcement-audience__item" data-audience-dropdown>
              <button type="button" class="announcement-audience__chip" data-audience-toggle aria-expanded="false" aria-controls="<?= htmlspecialchars($audienceId, ENT_QUOTES); ?>" title="Show active <?= htmlspecialchars($audienceLabel); ?>">
                <span><strong><?= count($announcementAudience[$audienceRole]); ?></strong> <?= htmlspecialchars($audienceLabel); ?></span>
                <span class="material-symbols-rounded announcement-audience__chip-icon" aria-hidden="true">expand_more</span>
              </button>
              <section class="announcement-audience__panel" id="<?= htmlspecialchars($audienceId, ENT_QUOTES); ?>" data-audience-panel aria-label="Active <?= htmlspecialchars($audienceLabel); ?>" hidden>
                <div class="announcement-audience__panel-heading">
                  <strong><?= count($announcementAudience[$audienceRole]); ?> active <?= htmlspecialchars($audienceLabel); ?></strong>
                </div>
                <?php if (!$announcementAudience[$audienceRole]): ?>
                  <p class="announcement-audience__empty">No active <?= htmlspecialchars($audienceLabel); ?>.</p>
                <?php else: ?>
                  <ul class="announcement-audience__list">
                    <?php foreach ($announcementAudience[$audienceRole] as $audienceMember): ?>
                      <li>
                        <strong><?= htmlspecialchars((string)$audienceMember['display_name']); ?></strong>
                        <?php if (!empty($audienceMember['email'])): ?><span><?= htmlspecialchars((string)$audienceMember['email']); ?></span><?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </section>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="announcement-center__content">
        <form class="announcement-form" method="post" action="dashboard.php">
          <input type="hidden" name="action" value="publish_announcement">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
          <div class="announcement-composer__author">
            <span class="announcement-composer__avatar material-symbols-rounded" aria-hidden="true">campaign</span>
            <span class="announcement-composer__byline">
              <strong><?= htmlspecialchars(adminCurrentName()); ?></strong>
              <small>Posting to all active students and professors</small>
            </span>
            <span class="announcement-composer__audience material-symbols-rounded" title="Students and professors" aria-label="Students and professors">groups</span>
          </div>
          <div class="announcement-field-heading">
            <label for="announcement-title">Title</label>
            <span data-announcement-count="announcement-title">0 / 120</span>
          </div>
          <input id="announcement-title" name="announcement_title" type="text" maxlength="120" required placeholder="e.g. Enrollment schedule update" data-announcement-input>
          <div class="announcement-field-heading">
            <label for="announcement-body">Message</label>
            <span data-announcement-count="announcement-body">0 / 5000</span>
          </div>
          <textarea id="announcement-body" name="announcement_body" rows="4" maxlength="5000" required placeholder="Write the update students and professors need to know..." data-announcement-input></textarea>
          <div class="announcement-form__footer">
            <span>Appears in portal notifications and is emailed when SMTP is configured.</span>
            <button class="announcement-submit" type="submit">
              <span class="material-symbols-rounded" aria-hidden="true">send</span>
              Publish announcement
            </button>
          </div>
        </form>

        <aside class="announcement-sidebar" aria-label="Announcement preview and recent posts">
          <section class="announcement-preview" aria-labelledby="announcement-preview-title">
            <div class="announcement-preview__heading">
              <span class="material-symbols-rounded" aria-hidden="true">visibility</span>
              <h3 id="announcement-preview-title">Recipient preview</h3>
              <span class="announcement-preview__badge">LIVE PREVIEW</span>
            </div>
            <div class="announcement-preview__message" aria-live="polite" aria-atomic="true">
              <div class="announcement-post__author">
                <span class="announcement-post__avatar material-symbols-rounded" aria-hidden="true">campaign</span>
                <span><strong>PTC Academic Portal</strong><small>Announcement preview</small></span>
              </div>
              <strong data-announcement-preview-title>Announcement title</strong>
              <p data-announcement-preview-body>Your message preview will appear here as you write.</p>
            </div>
          </section>

          <div class="announcement-history">
            <div class="announcement-history__heading">
              <h3>Recent posts</h3>
              <span><?= count($recentAnnouncements); ?>/5</span>
            </div>
            <?php if (!$recentAnnouncements): ?>
              <p class="announcement-history__empty">No announcements have been posted yet.</p>
            <?php else: ?>
              <ul class="announcement-history__list">
                <?php foreach ($recentAnnouncements as $announcement): ?>
                  <li class="announcement-history__item">
                    <div class="announcement-post__author">
                      <span class="announcement-post__avatar material-symbols-rounded" aria-hidden="true">campaign</span>
                      <span><strong>PTC Academic Portal</strong><small>Sent to students and professors</small></span>
                      <time datetime="<?= htmlspecialchars((string)$announcement['published_at'], ENT_QUOTES); ?>"><?= htmlspecialchars(date('M j, g:i A', strtotime((string)$announcement['published_at']))); ?></time>
                    </div>
                    <div class="announcement-post__body">
                      <strong><?= htmlspecialchars($announcement['title']); ?></strong>
                      <p><?= htmlspecialchars($announcement['body']); ?></p>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </section>

    <!-- Submission statistics chart -->
    <div class="dashboard-insights-grid">
    <div class="chart-card">
      <p class="chart-title">Grading Sheet Submission Statistics</p>
      <p class="chart-subtitle">Number of submissions in the last 7 days.</p>
      <div class="chart-header">
        <div class="chart-total"><?= (int)$chart['total'] ?></div>
        <?php
          $delta = round($chart['deltaPct'], 1);
          $deltaSign = $delta > 0 ? '+' : ($delta < 0 ? '' : '');
          $deltaClass = $delta >= 0 ? 'chart-delta' : 'chart-delta' ; // keep same color; tweak if needed
        ?>
          <div class="<?= $deltaClass ?>"><?= $deltaSign . $delta ?>%</div>
        </div>
        <div class="chart-legend">
        <span><span class="legend-dot legend-dot--submitted"></span>Submitted</span>
        <span><span class="legend-dot legend-dot--pending"></span>Pending</span>
      </div>
      <?php if ($chart['total'] === 0 && array_sum($chart['pending']) === 0): ?>
        <div class="chart-empty" role="status">
          <span class="material-symbols-rounded">insights</span>
          <strong>No submissions recorded yet</strong>
          <span>Activity will appear here as grading sheets are submitted.</span>
        </div>
      <?php else: ?>
      <div class="bar-chart">
        <?php
          $todayLabel = (new DateTimeImmutable('today'))->format('D');
          foreach ($chart['labels'] as $idx => $lbl):
            $submittedCnt = (int)($chart['submitted'][$idx] ?? 0);
            $pendingCnt = (int)($chart['pending'][$idx] ?? 0);
            $submittedHeight = $chart['max'] > 0 ? (int)round(($submittedCnt / $chart['max']) * 100) : 0;
            $pendingHeight = $chart['max'] > 0 ? (int)round(($pendingCnt / $chart['max']) * 100) : 0;
            $isToday = ($lbl === $todayLabel);
        ?>
          <div class="bar-wrap">
            <div class="bar-track">
              <div class="bar submitted <?= $isToday ? 'today' : '' ?>" style="height: <?= $submittedHeight ?>%" title="<?= htmlspecialchars($lbl . ' submitted: ' . $submittedCnt) ?>"></div>
              <div class="bar pending" style="height: <?= $pendingHeight ?>%" title="<?= htmlspecialchars($lbl . ' pending: ' . $pendingCnt) ?>"></div>
            </div>
            <div class="bar-label"><?= htmlspecialchars($lbl) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="chart-actions">
        <a class="button btn-outline" href="./reports.php">View Full Report</a>
        <a class="button" href="?download=submissions_csv">Download CSV</a>
      </div>
    </div>
    <section class="recent-activity-card" aria-labelledby="recent-activity-title">
      <div class="recent-activity-card__heading">
        <div>
          <p class="page-kicker">SYSTEM UPDATES</p>
          <h2 id="recent-activity-title">Recent activity</h2>
        </div>
        <a href="./activity_logs.php">View all</a>
      </div>
      <?php if ($recentActivityError): ?>
        <p class="recent-activity-empty" role="status">Activity is temporarily unavailable.</p>
      <?php elseif (!$recentActivity): ?>
        <div class="recent-activity-empty" role="status">
          <span class="material-symbols-rounded" aria-hidden="true">history</span>
          <strong>No activity recorded yet</strong>
          <span>Administrative updates will appear here.</span>
        </div>
      <?php else: ?>
        <ol class="recent-activity-list">
          <?php foreach ($recentActivity as $activity): ?>
            <li class="recent-activity-item">
              <span class="recent-activity-item__marker material-symbols-rounded" aria-hidden="true">history</span>
              <div>
                <strong><?= htmlspecialchars((string)$activity['action'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <p><?= htmlspecialchars((string)($activity['details'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <small><?= htmlspecialchars((string)$activity['actor'], ENT_QUOTES, 'UTF-8'); ?> · <?= htmlspecialchars(date('M j, g:i A', strtotime((string)$activity['created_at'])), ENT_QUOTES, 'UTF-8'); ?></small>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
    </div>


  </main>
</div>
<script src="../assets/js/admin.js?v=20261109.1"></script>
</body>
<?php include '../includes/footer.php'; ?>
</html>
