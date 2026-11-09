<?php
require_once '../core/config/config.php';
require_once '../core/auth/session.php';
require_once '../core/auth/guards.php';
require_once '../core/db/connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function announcement_api_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    announcement_api_response(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

if (!isLoggedIn()) {
    announcement_api_response(401, ['ok' => false, 'message' => 'Please sign in again.']);
}

$user = $_SESSION['user'];
$userId = (int)($user['id'] ?? 0);
$role = (string)($user['role'] ?? '');
$isAdmin = in_array($role, ['admin', 'registrar', 'mis', 'super_admin'], true);
if ($userId <= 0 || !in_array($role, ['student', 'professor', 'admin', 'registrar', 'mis', 'super_admin'], true)) {
    announcement_api_response(403, ['ok' => false, 'message' => 'This action is not available for your account.']);
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    announcement_api_response(400, ['ok' => false, 'message' => 'Invalid request.']);
}

$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$requestToken = (string)($payload['csrf_token'] ?? '');
if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
    announcement_api_response(403, ['ok' => false, 'message' => 'Your session expired. Refresh and try again.']);
}

$announcementId = filter_var($payload['announcement_id'] ?? null, FILTER_VALIDATE_INT);
$action = (string)($payload['action'] ?? '');
if (!$announcementId || $announcementId < 1 || !in_array($action, ['toggle_reaction', 'add_comment', 'list_comments'], true)) {
    announcement_api_response(400, ['ok' => false, 'message' => 'Invalid announcement action.']);
}

$parentId = null;
if (isset($payload['parent_id']) && $payload['parent_id'] !== '' && $payload['parent_id'] !== null) {
    $validatedParent = filter_var($payload['parent_id'], FILTER_VALIDATE_INT);
    if (!$validatedParent || $validatedParent < 1) {
        announcement_api_response(400, ['ok' => false, 'message' => 'Invalid reply target.']);
    }
    $parentStmt = $pdo->prepare('SELECT id FROM announcement_comments WHERE id = ? AND announcement_id = ? LIMIT 1');
    $parentStmt->execute([(int)$validatedParent, $announcementId]);
    if (!$parentStmt->fetchColumn()) {
        announcement_api_response(404, ['ok' => false, 'message' => 'Reply target not found.']);
    }
    $parentId = (int)$validatedParent;
}

if ($isAdmin) {
    $accessStmt = $pdo->prepare('SELECT id FROM announcements WHERE id = ? LIMIT 1');
    $accessStmt->execute([$announcementId]);
} else {
    $accessStmt = $pdo->prepare(
        "SELECT a.id
           FROM announcements a
           JOIN notifications n ON n.announcement_id = a.id
                 JOIN users u ON u.id = n.user_id
          WHERE a.id = ?
            AND n.user_id = ?
            AND n.type = 'announcement'
                    AND u.status = 'ACTIVE'
                    AND u.role IN ('student', 'professor')
          LIMIT 1"
    );
    $accessStmt->execute([$announcementId, $userId]);
}
if (!$accessStmt->fetchColumn()) {
    announcement_api_response(404, ['ok' => false, 'message' => 'Announcement not found.']);
}

if ($action === 'toggle_reaction') {
    $deleteStmt = $pdo->prepare('DELETE FROM announcement_reactions WHERE announcement_id = ? AND user_id = ?');
    $deleteStmt->execute([$announcementId, $userId]);
    $reacted = $deleteStmt->rowCount() === 0;

    if ($reacted) {
        $insertStmt = $pdo->prepare(
            "INSERT IGNORE INTO announcement_reactions (announcement_id, user_id, reaction_type) VALUES (?, ?, 'like')"
        );
        $insertStmt->execute([$announcementId, $userId]);

        try {
            $metaStmt = $pdo->prepare('SELECT title, created_by FROM announcements WHERE id = ? LIMIT 1');
            $metaStmt->execute([$announcementId]);
            $meta = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $recipientIds = [];
            if (!empty($meta['created_by']) && (int)$meta['created_by'] !== $userId) {
                $recipientIds[] = (int)$meta['created_by'];
            }
            if (!$recipientIds) {
                $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin','registrar','mis','super_admin') AND status = 'ACTIVE' AND id <> ?");
                $adminStmt->execute([$userId]);
                $recipientIds = array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            }
            $actorStmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
            $actorStmt->execute([$userId]);
            $actor = $actorStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
            if ($actorName === '') {
                $actorName = (string)($actor['email'] ?? 'A portal user');
            }
            $title = trim((string)($meta['title'] ?? ''));
            $message = $actorName . ' liked your announcement' . ($title !== '' ? ': ' . $title : '.');
            $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, is_read, announcement_id) VALUES (?, ?, ?, 0, ?)');
            foreach (array_unique($recipientIds) as $recipientId) {
                $notifStmt->execute([(int)$recipientId, 'announcement_reaction', $message, $announcementId]);
            }
        } catch (Throwable $e) {
        }
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM announcement_reactions WHERE announcement_id = ?');
    $countStmt->execute([$announcementId]);
    $currentStmt = $pdo->prepare('SELECT 1 FROM announcement_reactions WHERE announcement_id = ? AND user_id = ?');
    $currentStmt->execute([$announcementId, $userId]);

    announcement_api_response(200, [
        'ok' => true,
        'reacted' => (bool)$currentStmt->fetchColumn(),
        'reaction_count' => (int)$countStmt->fetchColumn(),
    ]);
}

if ($action === 'add_comment') {
    $commentText = trim((string)($payload['comment'] ?? ''));
    $commentLength = function_exists('mb_strlen') ? mb_strlen($commentText) : strlen($commentText);
    if ($commentText === '' || $commentLength > 2000) {
        announcement_api_response(422, ['ok' => false, 'message' => 'Comments must be between 1 and 2,000 characters.']);
    }

    $insertStmt = $pdo->prepare('INSERT INTO announcement_comments (announcement_id, user_id, parent_comment_id, comment_text) VALUES (?, ?, ?, ?)');
    $insertStmt->execute([$announcementId, $userId, $parentId, $commentText]);
    $commentId = (int)$pdo->lastInsertId();

    $commentStmt = $pdo->prepare(
        "SELECT c.id, c.parent_comment_id, c.comment_text, c.created_at,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.email, 'Portal user') AS author_name
           FROM announcement_comments c
           JOIN users u ON u.id = c.user_id
          WHERE c.id = ? AND c.announcement_id = ?
          LIMIT 1"
    );
    $commentStmt->execute([$commentId, $announcementId]);
    $comment = $commentStmt->fetch(PDO::FETCH_ASSOC);

    try {
        $metaStmt = $pdo->prepare('SELECT title, created_by FROM announcements WHERE id = ? LIMIT 1');
        $metaStmt->execute([$announcementId]);
        $meta = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $recipientIds = [];
        if ($parentId) {
            $parentAuthorStmt = $pdo->prepare('SELECT user_id FROM announcement_comments WHERE id = ? LIMIT 1');
            $parentAuthorStmt->execute([$parentId]);
            $parentAuthorId = (int)($parentAuthorStmt->fetchColumn() ?: 0);
            if ($parentAuthorId > 0 && $parentAuthorId !== $userId) {
                $recipientIds[] = $parentAuthorId;
            }
        }
        if (!empty($meta['created_by']) && (int)$meta['created_by'] !== $userId) {
            $recipientIds[] = (int)$meta['created_by'];
        }
        if (!$recipientIds) {
            $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin','registrar','mis','super_admin') AND status = 'ACTIVE' AND id <> ?");
            $adminStmt->execute([$userId]);
            $recipientIds = array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        $actorStmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
        $actorStmt->execute([$userId]);
        $actor = $actorStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
        if ($actorName === '') {
            $actorName = (string)($actor['email'] ?? 'A portal user');
        }
        $title = trim((string)($meta['title'] ?? ''));
        $suffix = $parentId ? 'replied to a comment' : 'commented on your announcement';
        $message = $actorName . ' ' . $suffix . ($title !== '' ? ': ' . $title : '.');
        $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, is_read, announcement_id) VALUES (?, ?, ?, 0, ?)');
        foreach (array_unique($recipientIds) as $recipientId) {
            $notifStmt->execute([(int)$recipientId, 'announcement_comment', $message, $announcementId]);
        }
    } catch (Throwable $e) {
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM announcement_comments WHERE announcement_id = ?');
    $countStmt->execute([$announcementId]);

    announcement_api_response(201, [
        'ok' => true,
        'comment' => $comment,
        'comment_count' => (int)$countStmt->fetchColumn(),
    ]);
}

$commentsStmt = $pdo->prepare(
    "SELECT c.id, c.parent_comment_id, c.comment_text, c.created_at,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.email, 'Portal user') AS author_name
       FROM announcement_comments c
       JOIN users u ON u.id = c.user_id
      WHERE c.announcement_id = ?
   ORDER BY c.created_at ASC, c.id ASC
      LIMIT 100"
);
$commentsStmt->execute([$announcementId]);
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM announcement_comments WHERE announcement_id = ?');
$countStmt->execute([$announcementId]);

announcement_api_response(200, [
    'ok' => true,
    'comments' => $commentsStmt->fetchAll(PDO::FETCH_ASSOC),
    'comment_count' => (int)$countStmt->fetchColumn(),
]);
