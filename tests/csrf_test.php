<?php
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../admin/includes/functions.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$token = csrf_token();
expect(strlen($token) === 64, 'CSRF tokens should contain 32 random bytes encoded as hex.');
expect(is_valid_csrf_token($token), 'The current session token should validate.');
expect(
    preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', adminCsrfField(), $matches) === 1
        && is_valid_csrf_token($matches[1]),
    'Admin forms should receive a valid hidden CSRF token.'
);
expect(!is_valid_csrf_token(''), 'An empty request token should be rejected.');
expect(!is_valid_csrf_token('incorrect-token'), 'An incorrect request token should be rejected.');
expect(!is_valid_csrf_token(['invalid']), 'A non-string request token should be rejected.');

regenerate_authenticated_session();
$rotatedToken = csrf_token();
expect($rotatedToken !== $token, 'Authenticated session regeneration should rotate the CSRF token.');
expect(!is_valid_csrf_token($token), 'The previous CSRF token should be rejected after authentication.');
expect(is_valid_csrf_token($rotatedToken), 'The rotated CSRF token should validate.');

echo "CSRF token tests passed.\n";
