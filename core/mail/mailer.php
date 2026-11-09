<?php

function load_local_mail_settings(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $projectRoot = dirname(__DIR__, 2);
    $localSettingsPath = $projectRoot . DIRECTORY_SEPARATOR . '.env.local';
    if (!is_file($localSettingsPath)) {
        return;
    }

    $localSettings = @parse_ini_file($localSettingsPath, false, INI_SCANNER_RAW);
    if (!is_array($localSettings)) {
        error_log('Local SMTP settings could not be parsed.');
        return;
    }

    $allowedSettings = [
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_AUTH',
        'SMTP_ENCRYPTION',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
        'SMTP_FROM_EMAIL',
        'SMTP_FROM_NAME',
    ];

    foreach ($allowedSettings as $settingName) {
        if (getenv($settingName) === false && isset($localSettings[$settingName]) && is_string($localSettings[$settingName])) {
            putenv($settingName . '=' . $localSettings[$settingName]);
        }
    }
}

function send_app_email(string $to, string $subject, string $body): bool
{
    return send_app_email_to_many([$to], $subject, $body);
}

function send_app_email_to_many(array $recipients, string $subject, string $body): bool
{
    load_local_mail_settings();

    $projectRoot = dirname(__DIR__, 2);
    $autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (!is_file($autoloadPath)) {
        error_log('Email delivery unavailable: Composer dependencies are not installed.');
        return false;
    }

    require_once $autoloadPath;

    $mailerClass = 'PHPMailer\\PHPMailer\\PHPMailer';
    if (!class_exists($mailerClass)) {
        error_log('Email delivery unavailable: PHPMailer is not installed.');
        return false;
    }

    $host = trim((string)getenv('SMTP_HOST'));
    $username = trim((string)getenv('SMTP_USERNAME'));
    $password = (string)getenv('SMTP_PASSWORD');
    $fromEmail = trim((string)getenv('SMTP_FROM_EMAIL'));
    $fromName = trim((string)(getenv('SMTP_FROM_NAME') ?: 'Academic Portal'));
    $smtpAuthSetting = getenv('SMTP_AUTH');
    $smtpAuth = $smtpAuthSetting === false
        ? true
        : (filter_var($smtpAuthSetting, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true);
    $port = (int)(getenv('SMTP_PORT') ?: ($smtpAuth ? 587 : 1025));
    $encryption = strtolower(trim((string)(getenv('SMTP_ENCRYPTION') ?: ($smtpAuth ? 'tls' : 'none'))));

    $validRecipients = [];
    foreach ($recipients as $recipient) {
        if (is_string($recipient)) {
            $recipient = trim($recipient);
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $validRecipients[strtolower($recipient)] = $recipient;
            }
        }
    }

    if ($host === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || ($smtpAuth && ($username === '' || $password === ''))) {
        error_log('Email delivery unavailable: required SMTP environment settings are missing or invalid.');
        return false;
    }

    if (!$validRecipients) {
        error_log('Email delivery skipped: recipient address is invalid.');
        return false;
    }

    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        error_log('Email delivery unavailable: SMTP_ENCRYPTION must be tls, ssl, or none.');
        return false;
    }

    if (!$smtpAuth && !in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
        error_log('Unauthenticated SMTP is only allowed for a loopback development server.');
        return false;
    }

    try {
        $mailer = new $mailerClass(true);
        $mailer->isSMTP();
        $mailer->Timeout = 10;
        $mailer->Host = $host;
        $mailer->SMTPAuth = $smtpAuth;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        $mailer->SMTPAutoTLS = $encryption === 'tls';
        $mailer->Port = $port;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($fromEmail, $fromName);
        if (count($validRecipients) === 1) {
            $mailer->addAddress(reset($validRecipients));
        } else {
            $mailer->addAddress($fromEmail, $fromName);
            foreach ($validRecipients as $recipient) {
                $mailer->addBCC($recipient);
            }
        }
        $mailer->Subject = $subject;
        $mailer->Body = $body;
        $mailer->AltBody = $body;
        $mailer->send();

        return true;
    } catch (\Throwable $exception) {
        error_log('Email delivery failed: ' . $exception->getMessage());
        return false;
    }
}

function send_user_notification_email(PDO $pdo, int $userId, string $subject, string $message): bool
{
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND status = 'ACTIVE' LIMIT 1");
    $stmt->execute([$userId]);
    $email = $stmt->fetchColumn();

    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('Email notification skipped: active recipient email was not found.');
        return false;
    }

    return send_app_email($email, $subject, $message);
}