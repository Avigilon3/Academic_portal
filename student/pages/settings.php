<?php
require_once '../includes/init.php';
requireStudent();

$currentUserId = $_SESSION['user']['id'] ?? null;
$studentFullName = '';
$studentEmail = '';
$studentNumber = '';
$passwordErrors = [];
$passwordNotice = '';

if ($currentUserId) {
    try {
        $stmt = $pdo->prepare(
            "SELECT student_id, ptc_email, first_name, middle_name, last_name
               FROM students
              WHERE user_id = :uid
              LIMIT 1"
        );
        $stmt->execute([':uid' => $currentUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $parts = [];
            foreach (['first_name', 'middle_name', 'last_name'] as $key) {
                $val = trim((string)($row[$key] ?? ''));
                if ($val !== '') {
                    $parts[] = $val;
                }
            }
            $studentFullName = $parts ? implode(' ', $parts) : '';
            $studentEmail = trim((string)($row['ptc_email'] ?? ''));
            $studentNumber = trim((string)($row['student_id'] ?? ''));
        }
    } catch (Throwable $e) {
        // fall back to session-based values below
    }
}

if ($studentFullName === '' && function_exists('currentUserName')) {
    $studentFullName = currentUserName();
}
if ($studentEmail === '' && !empty($_SESSION['user']['email'])) {
    $studentEmail = $_SESSION['user']['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    try {
        $passwordStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $passwordStmt->execute([(int)$currentUserId]);
        $passwordHash = $passwordStmt->fetchColumn();

        if (!is_string($passwordHash) || $passwordHash === '' || !password_verify($currentPassword, $passwordHash)) {
            $passwordErrors[] = 'Current password is incorrect.';
        }
        if (strlen($newPassword) < 8) {
            $passwordErrors[] = 'New password must be at least 8 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'New password and confirmation do not match.';
        }

        if (!$passwordErrors) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePassword = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $updatePassword->execute([$newHash, (int)$currentUserId]);
            $passwordNotice = 'Password changed successfully.';
        }
    } catch (Throwable $error) {
        error_log('Student password update failed: ' . $error->getMessage());
        $passwordErrors[] = 'The password could not be changed right now. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="../assets/css/student.css?v=20260916.6">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <div class="layout">
      <?php include '../includes/sidebar.php'; ?>
      <main class="content">
          <div class="page-header">
            <h2>Settings</h2>
            <p class="text-muted">Manage your account settings and security preferences</p>
          </div>

          <div class="form-box">
            <div class="page-header icon">
              <span class="material-symbols-rounded">account_circle</span>
              <h3>Profile Information</h3>
            </div>
            <div class="row-grid cols-2">
                <div>
                  <label>Full Name</label>
                  <input class="form-control" type="text" value="<?= htmlspecialchars($studentFullName); ?>" readonly>
                </div>
                <div>
                  <label>Email Address</label>
                  <input class="form-control" type="email" value="<?= htmlspecialchars($studentEmail); ?>" readonly>
                </div>
                <div>
                  <label>Student ID Number</label>
                  <input class="form-control" type="text" value="<?= htmlspecialchars($studentNumber); ?>" readonly>
                </div>
            </div>
          </div>

          <div class="row-grid cols-1">
            <div class="form-box">
              <div class="page-header icon">
                <span class="material-symbols-rounded">settings</span>
                <h3>Change Password</h3>
              </div>
              <?php if ($passwordNotice): ?>
                <div class="feedback-toast feedback-toast-success" role="status" aria-live="polite"><?= htmlspecialchars($passwordNotice); ?></div>
              <?php endif; ?>
              <?php if ($passwordErrors): ?>
                <div class="feedback-toast feedback-toast-error" role="alert">
                  <ul>
                    <?php foreach ($passwordErrors as $passwordError): ?>
                      <li><?= htmlspecialchars($passwordError); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="change_password">
                <label>Current Password</label>
                <input class="form-control" type="password" name="current_password" required>
                <label>New Password</label>
                <input class="form-control" type="password" name="new_password" minlength="8" required>
                <label>Confirm New Password</label>
                <input class="form-control" type="password" name="confirm_password" minlength="8" required>
                <div class="form-actions" style="margin-top:12px">
                  <button type="submit">Change Password</button>
                </div>
              </form>
            </div>
          </div>

      </main>
    </div>
</body>
<script src="../assets/js/student.js?v=20260916.1"></script>
</html>
