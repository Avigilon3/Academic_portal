<?php
require_once '../../core/config/config.php';
require_once '../../core/auth/session.php';
require_once '../../core/auth/guards.php';
require_once '../../core/db/connection.php';
require_once '../../core/config/functions.php'; 
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireStudent();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Your session expired. Refresh the page and try again.');
}