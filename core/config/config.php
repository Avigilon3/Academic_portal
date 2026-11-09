<?php
// core/config/config.php

// Detect the application base URL from the current request so the same code works
// whether it is served from the document root or a subfolder.
$projectDir = realpath(dirname(__DIR__, 2));
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$baseUrl = '';

if (is_string($projectDir) && is_string($documentRoot) && $documentRoot !== false) {
    $projectDirNormalized = str_replace('\\', '/', $projectDir);
    $documentRootNormalized = rtrim(str_replace('\\', '/', $documentRoot), '/');
    if ($documentRootNormalized !== '' && strpos($projectDirNormalized, $documentRootNormalized) === 0) {
        $baseUrl = substr($projectDirNormalized, strlen($documentRootNormalized));
    }
}

$baseUrl = rtrim($baseUrl, '/');
define('BASE_URL', $baseUrl);

// DB settings (environment/server values in core/db/connection.php take precedence)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'grading_app';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';