<?php
require_once 'core/config/config.php';
require_once 'core/auth/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | Academic Portal</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Arimo:wght@400..700&family=Space+Grotesk:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="dist/tailwind.css">
</head>
<body class="public-notice-page">
    <main class="public-notice-card" aria-labelledby="notice-title">
        <h1 id="notice-title">Reset Password</h1>
        <p>Password reset links are not issued through this portal. Please contact the MIS/Registrar office for assistance.</p>
        <a href="<?= htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES) ?>">Back to login</a>
    </main>
</body>
</html>