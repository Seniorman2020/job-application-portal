<?php
require_once __DIR__ . '/bootstrap.php';

function app_mail_set_last_error(string $message): void
{
    $GLOBALS['app_mail_last_error'] = trim($message);
}

function app_mail_last_error(): string
{
    return trim((string) ($GLOBALS['app_mail_last_error'] ?? ''));
}

function app_mail_html_shell(string $subject, string $bodyHtml): string
{
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
        . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
        . '</title></head><body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,sans-serif;color:#1f2937;">'
        . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #dbe4ee;border-radius:18px;padding:28px;">'
        . '<h1 style="margin:0 0 16px;font-size:22px;color:#0f4c81;">Job Application Portal</h1>'
        . $bodyHtml
        . '</div></body></html>';
}

function send_app_mail_message(string $to, string $subject, string $plain, string $html, array $attachments = []): bool
{
    $to = trim($to);
    $subject = trim($subject);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '') {
        app_mail_set_last_error('A valid recipient email and subject are required.');
        return false;
    }

    if (strtolower(app_env('MAIL_ENABLED', '0')) !== '1') {
        app_mail_set_last_error('Mail delivery is disabled. Set MAIL_ENABLED=1 to enable outgoing email.');
        return false;
    }

    $fromAddress = trim(app_env('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
    $fromName = trim(app_env('MAIL_FROM_NAME', 'Job Application Portal'));
    $boundary = 'mail_' . bin2hex(random_bytes(12));
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . sprintf('%s <%s>', $fromName, $fromAddress);
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $body = '--' . $boundary . "\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=alt_{$boundary}\r\n\r\n";
    $body .= '--alt_' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plain . "\r\n\r\n";
    $body .= '--alt_' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= '--alt_' . $boundary . "--\r\n";

    foreach ($attachments as $attachment) {
        $name = trim((string) ($attachment['name'] ?? 'attachment'));
        $type = trim((string) ($attachment['type'] ?? 'application/octet-stream'));
        $content = (string) ($attachment['content'] ?? '');
        if ($content === '') {
            continue;
        }

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: ' . $type . '; name="' . addslashes($name) . '"' . "\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= 'Content-Disposition: attachment; filename="' . addslashes($name) . '"' . "\r\n\r\n";
        $body .= chunk_split(base64_encode($content)) . "\r\n";
    }
    $body .= '--' . $boundary . '--';

    $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    if (!$sent) {
        app_mail_set_last_error('Native mail transport was not able to send the message.');
        return false;
    }

    app_mail_set_last_error('');
    return true;
}