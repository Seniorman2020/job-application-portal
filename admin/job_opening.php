<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin_auth();
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/jobs_portal.php';

$conn = app_db();
ensure_jobs_portal_schema($conn);

$jobEditorMode = 'admin';
$jobEditorUserId = 'admin';

require __DIR__ . '/../includes/job_opening_editor.php';