<?php
require_once __DIR__ . '/mail.php';

function ensure_jobs_portal_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS job_openings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(140) NOT NULL UNIQUE,
        company_name VARCHAR(190) NOT NULL,
        title VARCHAR(190) NOT NULL,
        badge_text VARCHAR(120) NULL,
        location VARCHAR(255) NULL,
        employment_type VARCHAR(120) NULL,
        summary TEXT NULL,
        description LONGTEXT NULL,
        requirements LONGTEXT NULL,
        application_instructions LONGTEXT NULL,
        submission_subject VARCHAR(255) NULL,
        submission_message LONGTEXT NULL,
        interview_subject VARCHAR(255) NULL,
        interview_message LONGTEXT NULL,
        rejection_subject VARCHAR(255) NULL,
        rejection_message LONGTEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_by VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS job_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_code VARCHAR(40) NOT NULL UNIQUE,
        job_id INT NOT NULL,
        full_name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(60) NOT NULL,
        home_address TEXT NOT NULL,
        nin VARCHAR(60) NOT NULL,
        samaru_resident TINYINT(1) NOT NULL DEFAULT 0,
        age_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        non_student_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        resume_immediately TINYINT(1) NOT NULL DEFAULT 0,
        cv_file VARCHAR(255) NULL,
        cv_original_name VARCHAR(255) NULL,
        cv_file_type VARCHAR(120) NULL,
        cv_storage VARCHAR(20) NOT NULL DEFAULT 'disk',
        cv_blob_base64 LONGTEXT NULL,
        passport_file VARCHAR(255) NULL,
        passport_original_name VARCHAR(255) NULL,
        passport_file_type VARCHAR(120) NULL,
        passport_storage VARCHAR(20) NOT NULL DEFAULT 'disk',
        passport_blob_base64 LONGTEXT NULL,
        status ENUM('new','invited','rejected','archived') NOT NULL DEFAULT 'new',
        invite_subject VARCHAR(255) NULL,
        invite_message LONGTEXT NULL,
        rejection_subject VARCHAR(255) NULL,
        rejection_message LONGTEXT NULL,
        reviewed_by VARCHAR(50) NULL,
        invited_at DATETIME NULL,
        rejected_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_job_applications_job FOREIGN KEY (job_id) REFERENCES job_openings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (function_exists('has_column')) {
        if (!has_column($conn, 'job_openings', 'submission_subject')) {
            $conn->query("ALTER TABLE job_openings ADD COLUMN submission_subject VARCHAR(255) NULL AFTER application_instructions");
        }
        if (!has_column($conn, 'job_openings', 'submission_message')) {
            $conn->query("ALTER TABLE job_openings ADD COLUMN submission_message LONGTEXT NULL AFTER submission_subject");
        }
        if (!has_column($conn, 'job_applications', 'cv_original_name')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN cv_original_name VARCHAR(255) NULL AFTER cv_file");
        }
        if (!has_column($conn, 'job_applications', 'cv_file_type')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN cv_file_type VARCHAR(120) NULL AFTER cv_original_name");
        }
        if (!has_column($conn, 'job_applications', 'cv_storage')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN cv_storage VARCHAR(20) NOT NULL DEFAULT 'disk' AFTER cv_file_type");
        }
        if (!has_column($conn, 'job_applications', 'cv_blob_base64')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN cv_blob_base64 LONGTEXT NULL AFTER cv_storage");
        }
        if (!has_column($conn, 'job_applications', 'passport_original_name')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN passport_original_name VARCHAR(255) NULL AFTER passport_file");
        }
        if (!has_column($conn, 'job_applications', 'passport_file_type')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN passport_file_type VARCHAR(120) NULL AFTER passport_original_name");
        }
        if (!has_column($conn, 'job_applications', 'passport_storage')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN passport_storage VARCHAR(20) NOT NULL DEFAULT 'disk' AFTER passport_file_type");
        }
        if (!has_column($conn, 'job_applications', 'passport_blob_base64')) {
            $conn->query("ALTER TABLE job_applications ADD COLUMN passport_blob_base64 LONGTEXT NULL AFTER passport_storage");
        }
    }

    $conn->query("UPDATE job_applications SET cv_storage='disk' WHERE (cv_storage IS NULL OR cv_storage='') AND cv_file IS NOT NULL");
    $conn->query("UPDATE job_applications SET passport_storage='disk' WHERE (passport_storage IS NULL OR passport_storage='') AND passport_file IS NOT NULL");

    if (function_exists('ensure_index')) {
        ensure_index($conn, 'job_openings', 'idx_job_openings_active_sort', "(`is_active`, `sort_order`, `title`)");
        ensure_index($conn, 'job_applications', 'idx_job_applications_job_status', "(`job_id`, `status`, `created_at`)");
        ensure_index($conn, 'job_applications', 'idx_job_applications_email', "(`email`)");
        ensure_index($conn, 'job_applications', 'idx_job_applications_job_email', "(`job_id`, `email`)");
        ensure_index($conn, 'job_applications', 'idx_job_applications_job_nin', "(`job_id`, `nin`)");
    }

    seed_default_job_openings($conn);
    $done = true;
}

function default_job_opening_rows(): array
{
    $operationsDescription = implode("\n", [
        'Our team is hiring a detail-oriented Operations Coordinator to support everyday workflows and internal service delivery.',
        '',
        'The person in this role will keep recurring tasks organized, coordinate updates across teams, and help maintain reliable day-to-day execution.',
        '',
        'This sample role demonstrates a practical back-office hiring flow suitable for administrative, support, or operations-heavy teams.',
    ]);

    $operationsRequirements = implode("\n", [
        'Strong written communication and follow-up habits.',
        'Comfort with spreadsheets, shared documents, and internal process tracking.',
        'Ability to manage recurring tasks while keeping stakeholders updated.',
        'A current resume and a professional portfolio, LinkedIn profile, or reference link.',
    ]);

    $designerDescription = implode("\n", [
        'We are hiring a Product Designer to translate product goals into polished interfaces and usable workflows.',
        '',
        'This role is responsible for wireframes, high-fidelity UI work, and collaborative iteration with product and engineering stakeholders.',
        '',
        'The listing is included as a portfolio-friendly example of a creative hiring workflow with document review and status changes.',
    ]);

    $designerRequirements = implode("\n", [
        'Experience with interface design, user flows, and presentation-ready mockups.',
        'A portfolio that shows process as well as final outcomes.',
        'Ability to communicate design decisions clearly to cross-functional teams.',
        'Comfort working in a fast-moving product environment.',
    ]);

    $supportDescription = implode("\n", [
        'We are hiring a Customer Support Specialist to help users resolve issues quickly and maintain a high-quality support experience.',
        '',
        'The role focuses on communication, troubleshooting, escalation notes, and maintaining a calm, helpful tone across channels.',
        '',
        'This sample opening helps demonstrate a service-oriented hiring workflow in the public and admin portal.',
    ]);

    $supportRequirements = implode("\n", [
        'Clear and professional written communication.',
        'Ability to document customer issues accurately and escalate when needed.',
        'Comfort with structured queues, response targets, and service quality standards.',
        'A reliable internet connection and a quiet work environment for remote support.',
    ]);

    $sharedInstructions = implode("\n", [
        'Submit a working phone number and a valid email address.',
        'Upload your resume in PDF format only.',
        'Upload a clear professional profile photo.',
        'Provide a portfolio, LinkedIn profile, or reference link where requested.',
    ]);

    $sharedInterviewMessage = implode("\n", [
        'Thank you for applying for the {{job_title}} role at {{company_name}}.',
        '',
        'We would like to move your application forward to the next stage. Our team will contact you with the next steps shortly.',
        '',
        'Please keep your application number available for future communication.',
    ]);

    $sharedRejectionMessage = implode("\n", [
        'Thank you for your interest in the {{job_title}} role at {{company_name}}.',
        '',
        'After reviewing your application, we will not be progressing with it for this opening.',
        '',
        'We appreciate the time you invested and may keep your information on file for future opportunities.',
    ]);

    $sharedSubmissionMessage = implode("\n", [
        'Thank you for submitting your application for the {{job_title}} role at {{company_name}}.',
        '',
        'Your application number is {{application_code}}. Please keep it safe because it may be requested during follow-up communication.',
        '',
        'Our hiring team will review your submission and reach out if your profile is shortlisted for the next stage.',
    ]);

    return [
        [
            'slug' => 'operations-coordinator',
            'company_name' => 'Northstar Studio',
            'title' => 'Operations Coordinator',
            'badge_text' => 'Core Team Role',
            'location' => 'Remote / Hybrid',
            'employment_type' => 'Full-time',
            'summary' => 'Coordinate day-to-day operations, support internal teams, and keep delivery work organized.',
            'description' => $operationsDescription,
            'requirements' => $operationsRequirements,
            'application_instructions' => $sharedInstructions,
            'submission_subject' => 'Application Received | Job Application Portal',
            'submission_message' => $sharedSubmissionMessage,
            'interview_subject' => 'Application Update | Job Application Portal',
            'interview_message' => $sharedInterviewMessage,
            'rejection_subject' => 'Application Update | Job Application Portal',
            'rejection_message' => $sharedRejectionMessage,
            'is_active' => 1,
            'sort_order' => 1,
        ],
        [
            'slug' => 'product-designer',
            'company_name' => 'Northstar Studio',
            'title' => 'Product Designer',
            'badge_text' => 'Design Opening',
            'location' => 'Remote',
            'employment_type' => 'Contract',
            'summary' => 'Design user flows, product interfaces, and polished handoff-ready screens.',
            'description' => $designerDescription,
            'requirements' => $designerRequirements,
            'application_instructions' => $sharedInstructions,
            'submission_subject' => 'Application Received | Job Application Portal',
            'submission_message' => $sharedSubmissionMessage,
            'interview_subject' => 'Application Update | Job Application Portal',
            'interview_message' => $sharedInterviewMessage,
            'rejection_subject' => 'Application Update | Job Application Portal',
            'rejection_message' => $sharedRejectionMessage,
            'is_active' => 1,
            'sort_order' => 2,
        ],
        [
            'slug' => 'customer-support-specialist',
            'company_name' => 'Northstar Studio',
            'title' => 'Customer Support Specialist',
            'badge_text' => 'Service Team',
            'location' => 'Remote',
            'employment_type' => 'Full-time',
            'summary' => 'Support customers across channels and document issues with clarity and empathy.',
            'description' => $supportDescription,
            'requirements' => $supportRequirements,
            'application_instructions' => $sharedInstructions,
            'submission_subject' => 'Application Received | Job Application Portal',
            'submission_message' => $sharedSubmissionMessage,
            'interview_subject' => 'Application Update | Job Application Portal',
            'interview_message' => $sharedInterviewMessage,
            'rejection_subject' => 'Application Update | Job Application Portal',
            'rejection_message' => $sharedRejectionMessage,
            'is_active' => 1,
            'sort_order' => 3,
        ],
    ];
}

function seed_default_job_openings(mysqli $conn): void
{
    $rows = default_job_opening_rows();
    $stmt = $conn->prepare("INSERT INTO job_openings (
        slug, company_name, title, badge_text, location, employment_type, summary,
        description, requirements, application_instructions, submission_subject, submission_message,
        interview_subject, interview_message, rejection_subject, rejection_message, is_active, sort_order, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        return;
    }

    foreach ($rows as $row) {
        $check = $conn->prepare("SELECT id FROM job_openings WHERE slug=? LIMIT 1");
        if (!$check) {
            continue;
        }
        $slug = (string) $row['slug'];
        $check->bind_param('s', $slug);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        if ($exists) {
            continue;
        }

        $companyName = (string) $row['company_name'];
        $title = (string) $row['title'];
        $badgeText = (string) ($row['badge_text'] ?? '');
        $location = (string) ($row['location'] ?? '');
        $employmentType = (string) ($row['employment_type'] ?? '');
        $summary = (string) ($row['summary'] ?? '');
        $description = (string) ($row['description'] ?? '');
        $requirements = (string) ($row['requirements'] ?? '');
        $instructions = (string) ($row['application_instructions'] ?? '');
        $submissionSubject = (string) ($row['submission_subject'] ?? '');
        $submissionMessage = (string) ($row['submission_message'] ?? '');
        $interviewSubject = (string) ($row['interview_subject'] ?? '');
        $interviewMessage = (string) ($row['interview_message'] ?? '');
        $rejectionSubject = (string) ($row['rejection_subject'] ?? '');
        $rejectionMessage = (string) ($row['rejection_message'] ?? '');
        $isActive = !empty($row['is_active']) ? 1 : 0;
        $sortOrder = (int) ($row['sort_order'] ?? 0);
        $createdBy = 'system';

        $stmt->bind_param(
            'ssssssssssssssssiis',
            $slug,
            $companyName,
            $title,
            $badgeText,
            $location,
            $employmentType,
            $summary,
            $description,
            $requirements,
            $instructions,
            $submissionSubject,
            $submissionMessage,
            $interviewSubject,
            $interviewMessage,
            $rejectionSubject,
            $rejectionMessage,
            $isActive,
            $sortOrder,
            $createdBy
        );
        $stmt->execute();
    }
}

function job_future_opportunity_list(): array
{
    return [
        'Customer success and support roles',
        'Marketing and communications support',
        'Operations and project coordination',
        'Administrative and business support roles',
    ];
}

function fetch_job_openings(mysqli $conn, bool $includeInactive = false): array
{
    ensure_jobs_portal_schema($conn);
    $sql = "SELECT * FROM job_openings";
    if (!$includeInactive) {
        $sql .= " WHERE is_active=1";
    }
    $sql .= " ORDER BY is_active DESC, sort_order ASC, id DESC";
    return fetch_rows($conn->query($sql));
}

function fetch_job_opening(mysqli $conn, int $jobId): ?array
{
    ensure_jobs_portal_schema($conn);
    if ($jobId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM job_openings WHERE id=? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function fetch_job_opening_by_slug(mysqli $conn, string $slug, bool $includeInactive = false): ?array
{
    ensure_jobs_portal_schema($conn);
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    $sql = "SELECT * FROM job_openings WHERE slug=?";
    if (!$includeInactive) {
        $sql .= " AND is_active=1";
    }
    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function job_opening_stat_counts(mysqli $conn, int $jobId): array
{
    ensure_jobs_portal_schema($conn);
    $counts = ['total' => 0, 'new' => 0, 'invited' => 0, 'rejected' => 0];
    if ($jobId <= 0) {
        return $counts;
    }

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) AS new_total,
            SUM(CASE WHEN status='invited' THEN 1 ELSE 0 END) AS invited_total,
            SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected_total
        FROM job_applications
        WHERE job_id=?
    ");
    if (!$stmt) {
        return $counts;
    }
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    return [
        'total' => (int) ($row['total'] ?? 0),
        'new' => (int) ($row['new_total'] ?? 0),
        'invited' => (int) ($row['invited_total'] ?? 0),
        'rejected' => (int) ($row['rejected_total'] ?? 0),
    ];
}

function fetch_job_applications(mysqli $conn, int $jobId, string $statusFilter = 'all'): array
{
    ensure_jobs_portal_schema($conn);
    if ($jobId <= 0) {
        return [];
    }

    $sql = "SELECT * FROM job_applications WHERE job_id=?";
    $statusFilter = strtolower(trim($statusFilter));
    if (in_array($statusFilter, ['new', 'invited', 'rejected', 'archived'], true)) {
        $sql .= " AND status=?";
    }
    $sql .= " ORDER BY FIELD(status,'new','invited','rejected','archived'), id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if (in_array($statusFilter, ['new', 'invited', 'rejected', 'archived'], true)) {
        $stmt->bind_param('is', $jobId, $statusFilter);
    } else {
        $stmt->bind_param('i', $jobId);
    }
    $stmt->execute();
    return fetch_rows($stmt->get_result());
}

function fetch_job_application(mysqli $conn, int $applicationId): ?array
{
    ensure_jobs_portal_schema($conn);
    if ($applicationId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM job_applications WHERE id=? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function job_text_blocks(string $text): array
{
    $parts = preg_split("/\r?\n\r?\n/", trim($text));
    return array_values(array_filter(array_map(static fn($item) => trim((string) $item), $parts ?: []), static fn($item) => $item !== ''));
}

function job_text_list(string $text): array
{
    $lines = preg_split("/\r?\n/", trim($text));
    return array_values(array_filter(array_map(static function ($item) {
        $item = trim((string) $item);
        $item = preg_replace('/^[-*]\s*/', '', $item);
        return $item;
    }, $lines ?: []), static fn($item) => $item !== ''));
}

function job_application_code(): string
{
    return 'JOB-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function job_is_valid_phone(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone);
    return strlen($digits) >= 10;
}

function job_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function job_normalize_nin(string $nin): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim($nin)) ?? '');
}

function job_normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function find_duplicate_job_application(mysqli $conn, int $jobId, string $email, string $phone, string $nin): ?array
{
    ensure_jobs_portal_schema($conn);
    if ($jobId <= 0) {
        return null;
    }

    $normalizedEmail = job_normalize_email($email);
    $normalizedPhone = job_normalize_phone($phone);
    $normalizedNin = job_normalize_nin($nin);

    if ($normalizedEmail === '' && $normalizedPhone === '' && $normalizedNin === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, application_code, email, phone, nin FROM job_applications WHERE job_id=?");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($normalizedEmail !== '' && job_normalize_email((string) ($row['email'] ?? '')) === $normalizedEmail) {
            $row['duplicate_field'] = 'email';
            return $row;
        }

        if ($normalizedPhone !== '' && job_normalize_phone((string) ($row['phone'] ?? '')) === $normalizedPhone) {
            $row['duplicate_field'] = 'phone';
            return $row;
        }

        if ($normalizedNin !== '' && job_normalize_nin((string) ($row['nin'] ?? '')) === $normalizedNin) {
            $row['duplicate_field'] = 'nin';
            return $row;
        }
    }

    return null;
}

function job_application_file_column_map(string $field): ?array
{
    $map = [
        'passport_file' => [
            'file_name' => 'passport_file',
            'original_name' => 'passport_original_name',
            'file_type' => 'passport_file_type',
            'storage' => 'passport_storage',
            'blob' => 'passport_blob_base64',
            'fallback_name' => 'passport.jpg',
        ],
        'cv_file' => [
            'file_name' => 'cv_file',
            'original_name' => 'cv_original_name',
            'file_type' => 'cv_file_type',
            'storage' => 'cv_storage',
            'blob' => 'cv_blob_base64',
            'fallback_name' => 'resume.pdf',
        ],
    ];

    return $map[$field] ?? null;
}

function job_ini_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $number = (float) $value;
    $unit = strtolower(substr($value, -1));
    switch ($unit) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
            break;
    }

    return (int) round($number);
}

function job_request_exceeds_post_max_size(): bool
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength <= 0) {
        return false;
    }

    $limit = job_ini_size_to_bytes((string) ini_get('post_max_size'));
    if ($limit <= 0) {
        return false;
    }

    return $contentLength > $limit;
}

function job_upload_error_message(int $errorCode, string $label, int $maxBytes): string
{
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return $label . ' is required.';
    }
    if (in_array($errorCode, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        return $label . ' is too large. Please choose a file smaller than ' . ($maxBytes <= 3_145_728 ? '3 MB' : '5 MB') . '.';
    }
    if ($errorCode === UPLOAD_ERR_PARTIAL) {
        return 'Upload for ' . strtolower($label) . ' was interrupted. Please try again.';
    }
    if (in_array($errorCode, [UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION], true)) {
        return 'The server could not save the uploaded ' . strtolower($label) . ' right now. Please try again.';
    }

    return 'Upload for ' . strtolower($label) . ' failed. Please try again.';
}

function job_upload_file(string $fieldName, array $allowedMimes, array $allowedExtensions, string $prefix, array &$errors, string $label, int $maxBytes = 5_242_880): ?array
{
    $errorCode = (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = job_upload_error_message($errorCode, $label, $maxBytes);
        return null;
    }

    $tmpName = (string) ($_FILES[$fieldName]['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpName)) {
        $errors[] = 'Upload for ' . strtolower($label) . ' failed. Please try again.';
        return null;
    }

    $size = (int) ($_FILES[$fieldName]['size'] ?? 0);
    if ($size <= 0) {
        $errors[] = $label . ' must not be empty.';
        return null;
    }
    if ($size > $maxBytes) {
        $errors[] = $label . ' must be ' . ($maxBytes <= 3_145_728 ? '3 MB' : '5 MB') . ' or smaller.';
        return null;
    }

    $mime = detect_mime_type($tmpName);
    $extension = strtolower(pathinfo((string) ($_FILES[$fieldName]['name'] ?? ''), PATHINFO_EXTENSION));
    if (!$mime || !in_array($mime, $allowedMimes, true) || !in_array($extension, $allowedExtensions, true)) {
        $errors[] = $label . ' has an invalid file format.';
        return null;
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) ($_FILES[$fieldName]['name'] ?? 'file')));
    $safeName = trim((string) $safeName, '._-');
    if ($safeName === '') {
        $safeName = trim($prefix, '_') . '.' . $extension;
    }
    $fileName = uniqid($prefix, true) . '_' . $safeName;
    $contents = @file_get_contents($tmpName);
    if ($contents === false || $contents === '') {
        $errors[] = 'We could not read the uploaded ' . strtolower($label) . '.';
        return null;
    }

    $savedToDisk = @move_uploaded_file($tmpName, uploads_dir() . '/' . $fileName);
    if ($savedToDisk) {
        return [
            'file_name' => $fileName,
            'original_name' => $safeName,
            'file_type' => $mime,
            'storage' => 'disk',
            'blob_base64' => '',
            'saved_to_disk' => true,
        ];
    }

    return [
        'file_name' => $fileName,
        'original_name' => $safeName,
        'file_type' => $mime,
        'storage' => 'db',
        'blob_base64' => base64_encode($contents),
        'saved_to_disk' => false,
    ];
}

function create_job_application(mysqli $conn, array $job, array $post, string &$errorMessage = ''): ?array
{
    ensure_jobs_portal_schema($conn);

    $fullName = trim((string) ($post['full_name'] ?? ''));
    $email = trim((string) ($post['email'] ?? ''));
    $phone = trim((string) ($post['phone'] ?? ''));
    $homeAddress = trim((string) ($post['home_address'] ?? ''));
    $nin = trim((string) ($post['nin'] ?? ''));
    $samaruResident = strtolower(trim((string) ($post['samaru_resident'] ?? 'no'))) === 'yes' ? 1 : 0;
    $ageConfirmed = isset($post['age_confirmed']) ? 1 : 0;
    $nonStudentConfirmed = isset($post['non_student_confirmed']) ? 1 : 0;
    $resumeImmediately = isset($post['resume_immediately']) ? 1 : 0;

    $errors = [];
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!job_is_valid_phone($phone)) {
        $errors[] = 'Enter a valid phone number.';
    }
    if ($homeAddress === '') {
        $errors[] = 'Home address is required.';
    }
    if ($nin === '') {
        $errors[] = 'Portfolio, LinkedIn, or reference link is required.';
    }
    if (!$samaruResident) {
        $errors[] = 'Please confirm that you meet the location or work setup requirements for this role.';
    }
    if (!$ageConfirmed) {
        $errors[] = 'Please confirm that you meet the minimum age and work eligibility requirements.';
    }
    if (!$nonStudentConfirmed) {
        $errors[] = 'Please confirm that you can commit to this role\'s schedule requirements.';
    }
    if (!$resumeImmediately) {
        $errors[] = 'Please confirm that you can start within the expected timeframe.';
    }

    if (!empty($errors)) {
        $errorMessage = implode(' ', $errors);
        return null;
    }

    $jobId = (int) ($job['id'] ?? 0);
    $duplicateApplication = find_duplicate_job_application($conn, $jobId, $email, $phone, $nin);
    if ($duplicateApplication !== null) {
        $fieldLabels = [
            'email' => 'email address',
            'phone' => 'phone number',
            'nin' => 'portfolio or reference link',
        ];
        $matchedField = (string) ($duplicateApplication['duplicate_field'] ?? '');
        $matchedLabel = $fieldLabels[$matchedField] ?? 'details';
        $errorMessage = 'An application has already been submitted for this job with this ' . $matchedLabel . '.';
        return null;
    }

    $passportUpload = job_upload_file(
        'passport_file',
        ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'job_passport_',
        $errors,
        'Passport photograph',
        3_145_728
    );
    $cvUpload = job_upload_file(
        'cv_file',
        ['application/pdf'],
        ['pdf'],
        'job_cv_',
        $errors,
        'CV',
        5_242_880
    );

    if (!empty($errors)) {
        foreach ([$passportUpload, $cvUpload] as $uploadedFile) {
            $uploadedFile = trim((string) ($uploadedFile['file_name'] ?? ''));
            if ($uploadedFile === '') {
                continue;
            }
            $path = resolve_upload_file_path($uploadedFile) ?? (uploads_dir() . '/' . $uploadedFile);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $errorMessage = implode(' ', $errors);
        return null;
    }

    $passportFile = (string) ($passportUpload['file_name'] ?? '');
    $passportOriginalName = (string) ($passportUpload['original_name'] ?? '');
    $passportFileType = (string) ($passportUpload['file_type'] ?? '');
    $passportStorage = (string) ($passportUpload['storage'] ?? 'disk');
    $passportBlobBase64 = (string) ($passportUpload['blob_base64'] ?? '');
    $cvFile = (string) ($cvUpload['file_name'] ?? '');
    $cvOriginalName = (string) ($cvUpload['original_name'] ?? '');
    $cvFileType = (string) ($cvUpload['file_type'] ?? '');
    $cvStorage = (string) ($cvUpload['storage'] ?? 'disk');
    $cvBlobBase64 = (string) ($cvUpload['blob_base64'] ?? '');
    $applicationCode = job_application_code();
    $stmt = $conn->prepare("INSERT INTO job_applications (
        application_code, job_id, full_name, email, phone, home_address, nin,
        samaru_resident, age_confirmed, non_student_confirmed, resume_immediately,
        cv_file, cv_original_name, cv_file_type, cv_storage, cv_blob_base64,
        passport_file, passport_original_name, passport_file_type, passport_storage, passport_blob_base64, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')");
    if (!$stmt) {
        $errorMessage = 'We could not save the application right now.';
        return null;
    }
    $stmt->bind_param(
        'sisssssiiiissssssssss',
        $applicationCode,
        $jobId,
        $fullName,
        $email,
        $phone,
        $homeAddress,
        $nin,
        $samaruResident,
        $ageConfirmed,
        $nonStudentConfirmed,
        $resumeImmediately,
        $cvFile,
        $cvOriginalName,
        $cvFileType,
        $cvStorage,
        $cvBlobBase64,
        $passportFile,
        $passportOriginalName,
        $passportFileType,
        $passportStorage,
        $passportBlobBase64
    );
    if (!$stmt->execute()) {
        error_log('Job application insert failed: ' . $stmt->error);
        foreach ([$passportFile, $cvFile] as $uploadedFile) {
            $path = resolve_upload_file_path($uploadedFile) ?? (uploads_dir() . '/' . $uploadedFile);
            if ($uploadedFile !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        $errorMessage = 'We could not save the application right now.';
        return null;
    }

    return fetch_job_application($conn, (int) $stmt->insert_id);
}

function job_status_label(string $status): string
{
    $map = [
        'new' => 'New',
        'invited' => 'Accepted',
        'rejected' => 'Not Selected',
        'archived' => 'Archived',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function job_status_badge_class(string $status): string
{
    $map = [
        'new' => 'blue',
        'invited' => 'green',
        'rejected' => 'red',
        'archived' => 'slate',
    ];
    return $map[$status] ?? 'slate';
}

function job_datetime_label($value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return 'Not recorded';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y g:i A', $timestamp);
}

function job_application_file_payload(array $application, string $field): ?array
{
    $columns = job_application_file_column_map($field);
    if ($columns === null) {
        return null;
    }

    $fileName = trim((string) ($application[$columns['file_name']] ?? ''));
    if ($fileName === '') {
        return null;
    }

    $storage = trim((string) ($application[$columns['storage']] ?? 'disk'));
    $mimeType = trim((string) ($application[$columns['file_type']] ?? ''));
    $originalName = trim((string) ($application[$columns['original_name']] ?? ''));
    if ($originalName === '') {
        $originalName = trim((string) ($columns['fallback_name'] ?? $fileName));
    }

    if ($storage === 'db') {
        $decoded = base64_decode((string) ($application[$columns['blob']] ?? ''), true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        return [
            'storage' => 'db',
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_type' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
            'content' => $decoded,
        ];
    }

    $path = resolve_upload_file_path($fileName);
    if ($path === null || !is_file($path) || !is_readable($path)) {
        return null;
    }

    return [
        'storage' => 'disk',
        'file_name' => $fileName,
        'original_name' => $originalName,
        'file_type' => $mimeType !== '' ? $mimeType : (detect_mime_type($path) ?: 'application/octet-stream'),
        'path' => $path,
    ];
}

function job_file_url(array $application, string $field, bool $download = true): string
{
    if ((int) ($application['id'] ?? 0) <= 0 || job_application_file_column_map($field) === null) {
        return '';
    }

    if (job_application_file_payload($application, $field) === null) {
        return '';
    }

    $url = rtrim(app_base_url(), '/') . '/file.php?job_application_id=' . (int) $application['id'] . '&field=' . rawurlencode($field);
    if ($download) {
        $url .= '&download=1';
    }

    return $url;
}

function job_file_is_available(array $application, string $field): bool
{
    return job_application_file_payload($application, $field) !== null;
}

function job_file_recorded(array $application, string $field): bool
{
    $columns = job_application_file_column_map($field);
    if ($columns === null) {
        return false;
    }

    return trim((string) ($application[$columns['file_name']] ?? '')) !== '';
}

function job_template_tokens(array $job, array $application): array
{
    return [
        '{{full_name}}' => (string) ($application['full_name'] ?? 'Applicant'),
        '{{job_title}}' => (string) ($job['title'] ?? 'the role'),
        '{{company_name}}' => (string) ($job['company_name'] ?? 'the hiring team'),
        '{{location}}' => (string) ($job['location'] ?? ''),
        '{{application_code}}' => (string) ($application['application_code'] ?? ''),
        '{{email}}' => (string) ($application['email'] ?? ''),
        '{{phone}}' => (string) ($application['phone'] ?? ''),
    ];
}

function render_job_template(string $template, array $job, array $application): string
{
    return strtr($template, job_template_tokens($job, $application));
}

function job_application_export_rows(array $job, array $application): array
{
    return [
        ['Application Code', (string) ($application['application_code'] ?? '')],
        ['Job Title', (string) ($job['title'] ?? '')],
        ['Company Name', (string) ($job['company_name'] ?? '')],
        ['Applicant Full Name', (string) ($application['full_name'] ?? '')],
        ['Email Address', (string) ($application['email'] ?? '')],
        ['Phone Number', (string) ($application['phone'] ?? '')],
        ['Address', (string) ($application['home_address'] ?? '')],
        ['Portfolio / LinkedIn URL', (string) ($application['nin'] ?? '')],
        ['Location Requirement Met', !empty($application['samaru_resident']) ? 'Yes' : 'No'],
        ['Eligibility Confirmed', !empty($application['age_confirmed']) ? 'Yes' : 'No'],
        ['Schedule Availability Confirmed', !empty($application['non_student_confirmed']) ? 'Yes' : 'No'],
        ['Available To Start', !empty($application['resume_immediately']) ? 'Yes' : 'No'],
        ['Status', job_status_label((string) ($application['status'] ?? 'new'))],
        ['Submitted At', (string) ($application['created_at'] ?? '')],
        ['Reviewed By', (string) ($application['reviewed_by'] ?? '')],
        ['Accepted At', (string) ($application['invited_at'] ?? '')],
        ['Rejected At', (string) ($application['rejected_at'] ?? '')],
    ];
}

function job_application_csv_content(array $job, array $application): string
{
    $stream = fopen('php://temp', 'r+');
    if (!$stream) {
        return '';
    }

    fputcsv($stream, ['Field', 'Value']);
    foreach (job_application_export_rows($job, $application) as $row) {
        fputcsv($stream, $row);
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    return $content !== false ? $content : '';
}

function job_application_export_filename(array $job, array $application): string
{
    $title = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower((string) ($job['title'] ?? 'application')));
    $code = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower((string) ($application['application_code'] ?? 'application')));
    return trim($title . '_' . $code, '_') . '.csv';
}

function job_application_mail_attachments(array $job, array $application): array
{
    $attachments = [];
    $csvContent = job_application_csv_content($job, $application);
    if ($csvContent !== '') {
        $attachments[] = [
            'name' => job_application_export_filename($job, $application),
            'type' => 'text/csv',
            'content' => $csvContent,
        ];
    }

    foreach (['passport_file', 'cv_file'] as $field) {
        $payload = job_application_file_payload($application, $field);
        if ($payload === null) {
            continue;
        }

        if (($payload['storage'] ?? '') === 'db') {
            $content = (string) ($payload['content'] ?? '');
        } else {
            $path = (string) ($payload['path'] ?? '');
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $content = (string) @file_get_contents($path);
        }
        if ($content === '') {
            continue;
        }

        $attachments[] = [
            'name' => (string) ($payload['original_name'] ?? basename((string) ($payload['file_name'] ?? 'attachment'))),
            'type' => (string) ($payload['file_type'] ?? 'application/octet-stream'),
            'content' => $content,
        ];
    }

    return $attachments;
}

function send_job_application_confirmation_email(array $job, array $application): bool
{
    $subject = trim((string) ($job['submission_subject'] ?? 'Application Received | Job Application Portal'));
    $message = trim((string) ($job['submission_message'] ?? ''));
    if ($message === '') {
        $message = "Thank you for submitting your application for the {{job_title}} position at {{company_name}}.\n\nYour application number is {{application_code}}. Please keep it safe for future follow-up.\n\nOur team will review your submission and contact you through your registered email address if you are shortlisted.";
    }

    return send_job_application_email($job, $application, $subject, $message);
}

function send_job_application_package_email(array $job, array $application, string $destinationEmail): bool
{
    $destinationEmail = trim($destinationEmail);
    if (!filter_var($destinationEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'Application Package | ' . (string) ($application['application_code'] ?? 'Applicant');
    $plain = "Attached is the application package for " . (string) ($application['full_name'] ?? 'the applicant') . " applying for " . (string) ($job['title'] ?? 'this role') . ".";
    $html = app_mail_html_shell(
        $subject,
        '<p style="margin:0 0 14px;">Attached is the application package for <strong>'
        . htmlspecialchars((string) ($application['full_name'] ?? 'the applicant'), ENT_QUOTES, 'UTF-8')
        . '</strong>.</p>'
        . '<p style="margin:0; color:#395244; line-height:1.8;">The attached files include the Excel-ready application table, passport photograph, and CV where available.</p>'
    );

    return send_app_mail_message($destinationEmail, $subject, $plain, $html, job_application_mail_attachments($job, $application));
}

function send_job_application_email(array $job, array $application, string $subject, string $message): bool
{
    $subject = trim($subject);
    $message = trim($message);
    if ($subject === '' || $message === '') {
        return false;
    }

    $subject = render_job_template($subject, $job, $application);
    $message = render_job_template($message, $job, $application);
    $fullName = trim((string) ($application['full_name'] ?? 'Applicant'));
    $plain = "Hello {$fullName},\n\n" . $message . "\n\nRegards,\nJob Application Portal Team";
    $html = app_mail_html_shell(
        $subject,
        '<p style="margin:0 0 14px;">Hello ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<div style="color:#395244; line-height:1.85;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</div>'
        . '<p style="margin:16px 0 0; font-size:13px; color:#395244; line-height:1.7; font-weight:700;">Regards,<br>Job Application Portal Team</p>'
    );

    return send_app_mail_message((string) ($application['email'] ?? ''), $subject, $plain, $html);
}

function save_job_opening(mysqli $conn, array $post, string $actorUserId, int $jobId = 0): int
{
    ensure_jobs_portal_schema($conn);
    $slug = trim((string) ($post['slug'] ?? ''));
    $slug = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($slug));
    $slug = trim((string) $slug, '-');
    $title = trim((string) ($post['title'] ?? ''));
    if ($slug === '' && $title !== '') {
        $slug = trim((string) preg_replace('/[^a-z0-9-]+/i', '-', strtolower($title)), '-');
    }

    $companyName = trim((string) ($post['company_name'] ?? ''));
    $badgeText = trim((string) ($post['badge_text'] ?? ''));
    $location = trim((string) ($post['location'] ?? ''));
    $employmentType = trim((string) ($post['employment_type'] ?? ''));
    $summary = trim((string) ($post['summary'] ?? ''));
    $description = trim((string) ($post['description'] ?? ''));
    $requirements = trim((string) ($post['requirements'] ?? ''));
    $instructions = trim((string) ($post['application_instructions'] ?? ''));
    $submissionSubject = trim((string) ($post['submission_subject'] ?? ''));
    $submissionMessage = trim((string) ($post['submission_message'] ?? ''));
    $interviewSubject = trim((string) ($post['interview_subject'] ?? ''));
    $interviewMessage = trim((string) ($post['interview_message'] ?? ''));
    $rejectionSubject = trim((string) ($post['rejection_subject'] ?? ''));
    $rejectionMessage = trim((string) ($post['rejection_message'] ?? ''));
    $isActive = isset($post['is_active']) ? 1 : 0;
    $sortOrder = (int) ($post['sort_order'] ?? 0);

    if ($jobId > 0) {
        $stmt = $conn->prepare("UPDATE job_openings SET slug=?, company_name=?, title=?, badge_text=?, location=?, employment_type=?, summary=?, description=?, requirements=?, application_instructions=?, submission_subject=?, submission_message=?, interview_subject=?, interview_message=?, rejection_subject=?, rejection_message=?, is_active=?, sort_order=?, created_by=? WHERE id=?");
        if (!$stmt) {
            return $jobId;
        }
        $stmt->bind_param(
            'ssssssssssssssssiisi',
            $slug,
            $companyName,
            $title,
            $badgeText,
            $location,
            $employmentType,
            $summary,
            $description,
            $requirements,
            $instructions,
            $submissionSubject,
            $submissionMessage,
            $interviewSubject,
            $interviewMessage,
            $rejectionSubject,
            $rejectionMessage,
            $isActive,
            $sortOrder,
            $actorUserId,
            $jobId
        );
        $stmt->execute();
        return $jobId;
    }

    $stmt = $conn->prepare("INSERT INTO job_openings (slug, company_name, title, badge_text, location, employment_type, summary, description, requirements, application_instructions, submission_subject, submission_message, interview_subject, interview_message, rejection_subject, rejection_message, is_active, sort_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param(
        'ssssssssssssssssiis',
        $slug,
        $companyName,
        $title,
        $badgeText,
        $location,
        $employmentType,
        $summary,
        $description,
        $requirements,
        $instructions,
        $submissionSubject,
        $submissionMessage,
        $interviewSubject,
        $interviewMessage,
        $rejectionSubject,
        $rejectionMessage,
        $isActive,
        $sortOrder,
        $actorUserId
    );
    $stmt->execute();
    return (int) $stmt->insert_id;
}

function delete_job_opening(mysqli $conn, int $jobId): void
{
    if ($jobId <= 0) {
        return;
    }

    $applications = fetch_job_applications($conn, $jobId, 'all');
    foreach ($applications as $application) {
        delete_job_application($conn, (int) ($application['id'] ?? 0));
    }

    $stmt = $conn->prepare("DELETE FROM job_openings WHERE id=?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
}

function delete_job_application(mysqli $conn, int $applicationId): void
{
    $application = fetch_job_application($conn, $applicationId);
    if (!$application) {
        return;
    }

    foreach (['cv_file', 'passport_file'] as $field) {
        $fileName = trim((string) ($application[$field] ?? ''));
        if ($fileName !== '') {
            $path = resolve_upload_file_path($fileName) ?? (uploads_dir() . '/' . $fileName);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    $stmt = $conn->prepare("DELETE FROM job_applications WHERE id=?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
}

function export_job_applications_csv(mysqli $conn, int $jobId): void
{
    $job = fetch_job_opening($conn, $jobId);
    if (!$job) {
        return;
    }
    $applications = fetch_job_applications($conn, $jobId, 'all');

    $filename = 'job_applications_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower((string) ($job['title'] ?? 'export'))) . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    if (!$output) {
        exit();
    }

    fputcsv($output, [
        'Application Code',
        'Job Title',
        'Full Name',
        'Email',
        'Phone',
        'Location Requirement Met',
        'Age Confirmed',
        'Not Student',
        'Resume Immediately',
        'Portfolio / LinkedIn URL',
        'Home Address',
        'Status',
        'Submitted At',
    ]);

    foreach ($applications as $application) {
        fputcsv($output, [
            $application['application_code'] ?? '',
            $job['title'] ?? '',
            $application['full_name'] ?? '',
            $application['email'] ?? '',
            $application['phone'] ?? '',
            !empty($application['samaru_resident']) ? 'Yes' : 'No',
            !empty($application['age_confirmed']) ? 'Yes' : 'No',
            !empty($application['non_student_confirmed']) ? 'Yes' : 'No',
            !empty($application['resume_immediately']) ? 'Yes' : 'No',
            $application['nin'] ?? '',
            $application['home_address'] ?? '',
            job_status_label((string) ($application['status'] ?? 'new')),
            $application['created_at'] ?? '',
        ]);
    }

    fclose($output);
    exit();
}
