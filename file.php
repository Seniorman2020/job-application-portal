<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_auth();
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/jobs_portal.php';

$conn = app_db();
$jobApplicationId = (int) ($_GET['job_application_id'] ?? 0);
$field = trim((string) ($_GET['field'] ?? ''));
$download = isset($_GET['download']) && $_GET['download'] === '1';

if ($jobApplicationId <= 0 || !in_array($field, ['passport_file', 'cv_file'], true)) {
    http_response_code(404);
    exit('File not found.');
}

$application = fetch_job_application($conn, $jobApplicationId);
if (!$application) {
    http_response_code(404);
    exit('File not found.');
}

$payload = job_application_file_payload($application, $field);
if ($payload === null) {
    http_response_code(404);
    exit('File not found.');
}

$safeFileName = str_replace(['"', "\r", "\n"], '', (string) ($payload['original_name'] ?? $payload['file_name'] ?? 'attachment'));
$mimeType = (string) ($payload['file_type'] ?? 'application/octet-stream');
$disposition = $download ? 'attachment' : ((str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf') ? 'inline' : 'attachment');

header('Content-Type: ' . $mimeType);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Disposition: ' . $disposition . '; filename="' . $safeFileName . '"');

if (($payload['storage'] ?? '') === 'db') {
    $content = (string) ($payload['content'] ?? '');
    header('Content-Length: ' . (string) strlen($content));
    echo $content;
    exit();
}

$filePath = (string) ($payload['path'] ?? '');
if ($filePath === '' || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Length: ' . (string) filesize($filePath));
readfile($filePath);
exit();