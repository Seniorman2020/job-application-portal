<?php
require_once __DIR__ . '/includes/session_bootstrap.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/jobs_portal.php';
require_once __DIR__ . '/includes/mail.php';

$conn = app_db();
ensure_jobs_portal_schema($conn);

$openings = fetch_job_openings($conn, false);
$selectedSlug = trim((string) ($_GET['job'] ?? $_POST['job_slug'] ?? ''));
$selectedJob = $selectedSlug !== '' ? fetch_job_opening_by_slug($conn, $selectedSlug, false) : null;
if ($selectedJob === null && !empty($openings)) {
    $selectedJob = $openings[0];
    $selectedSlug = (string) ($selectedJob['slug'] ?? '');
}

function public_job_find_application_by_code(mysqli $conn, string $applicationCode, int $jobId): ?array
{
    $applicationCode = trim($applicationCode);
    if ($applicationCode === '' || $jobId <= 0) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM job_applications WHERE application_code=? AND job_id=? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('si', $applicationCode, $jobId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

$errorMessage = '';
$successApplication = null;
$successEmailMessage = '';
$appliedCode = trim((string) ($_GET['applied'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['submit_job_application'])) {
    $selectedSlug = trim((string) ($_POST['job_slug'] ?? ''));
    $selectedJob = fetch_job_opening_by_slug($conn, $selectedSlug, false);
    if (!$selectedJob) {
        $errorMessage = 'The selected job opening is no longer available.';
    } else {
        $successApplication = create_job_application($conn, $selectedJob, $_POST, $errorMessage);
        if ($successApplication) {
            $emailSent = send_job_application_confirmation_email($selectedJob, $successApplication);
            $successEmailMessage = $emailSent
                ? 'A confirmation email was sent to ' . (string) ($successApplication['email'] ?? 'your email address') . '.'
                : 'The application was saved successfully. Email delivery is optional and may require local mail configuration.';
            $_SESSION['job_portal_success'] = [
                'job_slug' => (string) ($selectedJob['slug'] ?? ''),
                'job_id' => (int) ($selectedJob['id'] ?? 0),
                'application_code' => (string) ($successApplication['application_code'] ?? ''),
                'email_message' => $successEmailMessage,
            ];
            app_redirect('jobs.php?job=' . rawurlencode((string) ($selectedJob['slug'] ?? '')) . '&applied=' . rawurlencode((string) ($successApplication['application_code'] ?? '')), 303);
        }
    }
}

if ($successApplication === null && isset($_SESSION['job_portal_success']) && is_array($_SESSION['job_portal_success'])) {
    $flash = $_SESSION['job_portal_success'];
    if ($appliedCode !== '' && (string) ($flash['application_code'] ?? '') === $appliedCode) {
        $jobId = (int) ($flash['job_id'] ?? 0);
        if ($jobId > 0) {
            $successApplication = public_job_find_application_by_code($conn, $appliedCode, $jobId);
            $successEmailMessage = (string) ($flash['email_message'] ?? '');
        }
    }
    unset($_SESSION['job_portal_success']);
}

if ($successApplication === null && $appliedCode !== '' && $selectedJob) {
    $successApplication = public_job_find_application_by_code($conn, $appliedCode, (int) $selectedJob['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Application Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#f3f7f2;--ink:#173024;--muted:#5f7267;--green:#149647;--green-deep:#0c6730;--orange:#f5821f;--line:#d7e5d4;--shadow:0 24px 60px rgba(15,23,42,.10)}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Manrope',sans-serif}body{min-height:100vh;padding:24px;background:radial-gradient(circle at top left, rgba(20,150,71,.12), transparent 24%),linear-gradient(180deg,#fbfdf8 0%,#eef6ec 100%);color:var(--ink)}h1,h2,h3,.btn,.ghost,.pill{font-family:'Sora',sans-serif}a{text-decoration:none;color:inherit}.shell{max-width:1180px;margin:0 auto}.topbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}.brand{font-size:24px;font-weight:800;letter-spacing:-.04em;color:var(--green-deep)}.hero,.panel,.job-card,.notice{background:#fff;border:1px solid #edf3ec;border-radius:32px;box-shadow:var(--shadow)}.hero{padding:34px;margin-bottom:18px;background:linear-gradient(135deg,#ffffff 0%,#f7fbf7 100%)}.eyebrow{display:inline-flex;padding:8px 12px;border-radius:999px;background:#edf9f0;color:var(--green-deep);font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:16px}.hero h1{font-size:clamp(34px,5vw,54px);letter-spacing:-.05em;line-height:1.02;max-width:700px;margin-bottom:14px}.hero p{max-width:760px;color:var(--muted);line-height:1.9;font-size:16px}.status-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:rgba(245,130,31,.10);color:#a55613;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-top:18px}.grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}.panel{padding:24px}.panel h2{font-size:24px;margin-bottom:12px}.panel p{color:var(--muted);line-height:1.85}.notice{padding:16px 18px;margin-bottom:16px;font-weight:700}.notice.success{background:#e7f6ea;color:#166534}.notice.error{background:#fde8e5;color:#b42318}.job-stack{display:grid;gap:16px}.job-card{padding:20px}.job-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}.job-head h3{font-size:24px;letter-spacing:-.03em;margin-bottom:6px}.job-head p{color:var(--muted);line-height:1.75}.pill{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:999px;background:#eef7ff;color:#1d4ed8;font-size:12px;font-weight:800}.job-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.meta{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:14px;background:#f7fbf7;border:1px solid #e6eee5;font-size:13px;font-weight:700}.job-body{padding-top:18px;display:grid;gap:16px}.job-body h4{font-size:15px;margin-bottom:8px}.job-body ul{margin:0;padding-left:18px;display:grid;gap:8px;color:var(--muted);line-height:1.8}.actions,.top-actions{display:flex;gap:12px;flex-wrap:wrap}.btn,.ghost{display:inline-flex;align-items:center;justify-content:center;padding:14px 18px;border-radius:16px;font-weight:800;border:none;cursor:pointer}.btn{background:linear-gradient(135deg,var(--green),var(--green-deep));color:#fff}.ghost{background:#fff;color:var(--green-deep);border:1px solid var(--line)}.helper-card{padding:18px;border-radius:22px;background:#f7fbf7;border:1px solid #e7efe6;margin-top:16px}.helper-card strong{display:block;margin-bottom:8px}.application-panel{margin-top:18px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{margin-bottom:14px}.field label{display:block;margin-bottom:8px;font-size:13px;font-weight:800;color:#365042}.field input,.field textarea,.field select{width:100%;padding:14px 15px;border-radius:16px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:14px}.field textarea{min-height:120px;resize:vertical}.field small{display:block;margin-top:7px;color:var(--muted);line-height:1.6}.check-grid{display:grid;gap:12px}.check-card{display:flex;gap:10px;align-items:flex-start;padding:14px 15px;border-radius:16px;background:#f7fbf7;border:1px solid #e7efe6}.success-box{padding:18px;border-radius:24px;background:#e7f6ea;border:1px solid #c9e9d0;color:#166534;margin-bottom:18px}.success-box strong{display:block;font-size:20px;margin-bottom:8px}.empty{padding:18px;border-radius:18px;background:#f7fbf7;border:1px solid #e7efe6;color:var(--muted)}@media (max-width:960px){.grid,.form-grid{grid-template-columns:1fr}.hero,.panel,.job-card{border-radius:24px}.hero{padding:24px}}
</style>
</head>
<body>
<div class="shell">
    <div class="topbar"><div class="brand">Job Application Portal</div><div class="top-actions"><a class="ghost" href="admin/login.php">Admin Login</a></div></div>
    <section class="hero"><div class="eyebrow">Hiring Workflow Demo</div><h1>Explore live roles and submit a complete application in one place.</h1><p>This standalone portfolio project demonstrates a full recruitment flow: public job listings, guided application submission, admin job management, and applicant review.</p><div class="status-chip"><?= count($openings) ?> active opening<?= count($openings) === 1 ? '' : 's' ?> available</div></section>
    <?php if ($errorMessage !== ''): ?><div class="notice error"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
    <?php if ($successApplication): ?><div class="success-box"><strong>Application Submitted</strong><p>Your application number is <strong><?= htmlspecialchars((string) ($successApplication['application_code'] ?? '')) ?></strong>. <?= htmlspecialchars($successEmailMessage !== '' ? $successEmailMessage : 'Keep this number safe for follow-up communication.') ?></p></div><?php endif; ?>
    <div class="grid">
        <section class="panel">
            <h2>Available Jobs</h2>
            <?php if (!empty($openings)): ?><div class="job-stack"><?php foreach ($openings as $opening): ?>
                <article class="job-card">
                    <div class="job-head"><div><span class="pill"><?= htmlspecialchars((string) (($opening['badge_text'] ?? '') !== '' ? $opening['badge_text'] : 'Open Role')) ?></span><h3><?= htmlspecialchars((string) ($opening['title'] ?? 'Open role')) ?></h3><p><?= htmlspecialchars((string) ($opening['company_name'] ?? 'Sample Company')) ?> | <?= htmlspecialchars((string) ($opening['summary'] ?? '')) ?></p></div></div>
                    <div class="job-meta"><span class="meta"><i class="fa fa-location-dot"></i> <?= htmlspecialchars((string) ($opening['location'] ?? 'Flexible location')) ?></span><span class="meta"><i class="fa fa-briefcase"></i> <?= htmlspecialchars((string) ($opening['employment_type'] ?? 'Open role')) ?></span></div>
                    <div class="job-body"><div><h4>Role Overview</h4><?php foreach (job_text_blocks((string) ($opening['description'] ?? '')) as $block): ?><p style="color:var(--muted);line-height:1.85;margin-bottom:10px;"><?= nl2br(htmlspecialchars($block)) ?></p><?php endforeach; ?></div><div><h4>Requirements</h4><ul><?php foreach (job_text_list((string) ($opening['requirements'] ?? '')) as $item): ?><li><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul></div><div class="actions"><a class="btn" href="jobs.php?job=<?= rawurlencode((string) ($opening['slug'] ?? '')) ?>#application-form">Apply Now</a></div></div>
                </article>
            <?php endforeach; ?></div><?php else: ?><div class="empty">There are no active openings at the moment.</div><?php endif; ?>
        </section>
        <section class="panel" id="application-form">
            <h2><?= $selectedJob ? 'Apply for ' . htmlspecialchars((string) ($selectedJob['title'] ?? 'this role')) : 'Application Form' ?></h2>
            <p><?= $selectedJob ? 'Submit your application details, upload your resume, and include a profile link or portfolio reference.' : 'Select an opening to begin your application.' ?></p>
            <?php if ($selectedJob): ?>
                <form method="POST" enctype="multipart/form-data" class="application-panel">
                    <input type="hidden" name="job_slug" value="<?= htmlspecialchars((string) ($selectedJob['slug'] ?? '')) ?>">
                    <div class="form-grid"><div class="field"><label for="full_name">Full Name</label><input id="full_name" type="text" name="full_name" value="<?= htmlspecialchars((string) ($_POST['full_name'] ?? '')) ?>" required></div><div class="field"><label for="email">Email Address</label><input id="email" type="email" name="email" value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>" required></div><div class="field"><label for="phone">Phone Number</label><input id="phone" type="text" name="phone" value="<?= htmlspecialchars((string) ($_POST['phone'] ?? '')) ?>" required></div><div class="field"><label for="nin">Portfolio / LinkedIn URL</label><input id="nin" type="text" name="nin" value="<?= htmlspecialchars((string) ($_POST['nin'] ?? '')) ?>" placeholder="https://linkedin.com/in/example" required></div></div>
                    <div class="field"><label for="home_address">Address</label><textarea id="home_address" name="home_address" required><?= htmlspecialchars((string) ($_POST['home_address'] ?? '')) ?></textarea></div>
                    <div class="field"><label for="samaru_resident">Location or Work Setup Match</label><select id="samaru_resident" name="samaru_resident" required><option value="yes"<?= (($_POST['samaru_resident'] ?? 'yes') === 'yes') ? ' selected' : '' ?>>Yes, I meet the location or work setup requirements</option><option value="no"<?= (($_POST['samaru_resident'] ?? '') === 'no') ? ' selected' : '' ?>>No</option></select></div>
                    <div class="check-grid"><label class="check-card"><input type="checkbox" name="age_confirmed" value="1"<?= isset($_POST['age_confirmed']) ? ' checked' : '' ?>> <span>I confirm that I meet the minimum age and work eligibility requirements for this role.</span></label><label class="check-card"><input type="checkbox" name="non_student_confirmed" value="1"<?= isset($_POST['non_student_confirmed']) ? ' checked' : '' ?>> <span>I can commit to this role's schedule and responsibilities.</span></label><label class="check-card"><input type="checkbox" name="resume_immediately" value="1"<?= isset($_POST['resume_immediately']) ? ' checked' : '' ?>> <span>I can start within the expected hiring timeline for this position.</span></label></div>
                    <div class="form-grid" style="margin-top:14px;"><div class="field"><label for="passport_file">Profile Photo</label><input id="passport_file" type="file" name="passport_file" accept="image/*" required><small>Accepted formats: JPG, PNG, GIF, or WEBP. Max size: 3 MB.</small></div><div class="field"><label for="cv_file">Resume (PDF)</label><input id="cv_file" type="file" name="cv_file" accept="application/pdf" required><small>Upload your current resume in PDF format. Max size: 5 MB.</small></div></div>
                    <div class="actions" style="margin-top:18px;"><button class="btn" type="submit" name="submit_job_application">Submit Application</button></div>
                </form>
                <div class="helper-card"><strong>Application guidance</strong><p>Use accurate contact details, upload a current resume, and keep your application number safe after submission.</p></div>
            <?php else: ?><div class="empty">There are no active openings available for application right now.</div><?php endif; ?>
        </section>
    </div>
</div>
</body>
</html>