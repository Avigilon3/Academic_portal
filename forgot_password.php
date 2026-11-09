<?php
require_once 'core/config/config.php';
require_once 'core/auth/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Academic Portal</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Arimo:wght@400..700&family=Space+Grotesk:wght@500;600;700&display=swap">
</head>
<body style="font-family:Arimo,sans-serif;max-width:480px;margin:80px auto;padding:0 20px;color:#162125;">
    <h1 style="font-family:'Space Grotesk',sans-serif;">Forgot Password</h1>
    <p>Password reset is handled by the MIS/Registrar office. Please contact them to have your account password reset, then sign in again.</p>
    <p><a href="<?= htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES) ?>">Back to login</a></p>
</body>
</html>