<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    throw new RuntimeException('Job opening editor requires a database connection.');
}
if (!isset($jobEditorMode, $jobEditorUserId)) {
    throw new RuntimeException('Job opening editor context is missing.');
}

require_once __DIR__ . '/jobs_portal.php';

ensure_jobs_portal_schema($conn);

$mode = $jobEditorMode === 'moderator' ? 'moderator' : 'admin';
$actorUserId = trim((string) $jobEditorUserId);
$jobId = (int) ($_POST['job_id'] ?? $_GET['job_id'] ?? 0);
$openingFilter = strtolower(trim((string) ($_POST['opening_filter'] ?? $_GET['opening_filter'] ?? 'all')));
if (!in_array($openingFilter, ['all', 'active', 'inactive'], true)) {
    $openingFilter = 'all';
}

$jobEditorBasePath = 'job_opening.php';
$jobsBasePath = 'jobs.php';
$filterQuery = $openingFilter !== 'all' ? 'opening_filter=' . rawurlencode($openingFilter) : '';
$jobsBackQuery = [];
if ($jobId > 0) {
    $jobsBackQuery[] = 'id=' . $jobId;
}
if ($filterQuery !== '') {
    $jobsBackQuery[] = $filterQuery;
}
$backHref = $jobsBasePath . (!empty($jobsBackQuery) ? '?' . implode('&', $jobsBackQuery) : '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['save_job_opening'])) {
        $savedJobId = save_job_opening($conn, $_POST, $actorUserId, $jobId);
        $redirectQuery = ['job_id=' . $savedJobId, 'saved=1'];
        if ($filterQuery !== '') {
            $redirectQuery[] = $filterQuery;
        }
        app_redirect($jobEditorBasePath . '?' . implode('&', $redirectQuery));
    }

    if (isset($_POST['delete_job_opening'])) {
        if ($jobId > 0) {
            delete_job_opening($conn, $jobId);
        }
        $redirectQuery = ['deleted=1'];
        if ($filterQuery !== '') {
            $redirectQuery[] = $filterQuery;
        }
        app_redirect($jobsBasePath . '?' . implode('&', $redirectQuery));
    }
}

$job = $jobId > 0 ? fetch_job_opening($conn, $jobId) : null;
if ($jobId > 0 && !$job) {
    $redirectQuery = ['error=1'];
    if ($filterQuery !== '') {
        $redirectQuery[] = $filterQuery;
    }
    app_redirect($jobsBasePath . '?' . implode('&', $redirectQuery));
}

$pageTitle = $job ? 'Edit Job Opening' : 'Create Job Opening';
$pageIntro = $job
    ? 'Update the selected role on its own dedicated editor page, then return to the jobs desk to review applications.'
    : 'Create a new job opening on a dedicated page, then return to the jobs desk to monitor applications.';
$jobStats = $job ? job_opening_stat_counts($conn, (int) $job['id']) : ['total' => 0, 'new' => 0, 'invited' => 0, 'rejected' => 0];
$visibilityLabel = $job ? (!empty($job['is_active']) ? 'Active' : 'Inactive') : 'Draft';
$visibilityClass = $job ? (!empty($job['is_active']) ? 'green' : 'red') : 'blue';
$locationLabel = $job && !empty($job['location']) ? (string) $job['location'] : 'Add a job location';
$roleLabel = $job && !empty($job['title']) ? (string) $job['title'] : 'New job opening';
$companyLabel = $job && !empty($job['company_name']) ? (string) $job['company_name'] : 'Job Application Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | Job Application Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#edf3f8;--surface:#fff;--surface-soft:#f7fafc;--line:#dbe6ef;--ink:#102133;--muted:#607186;--brand:#0f4c81;--brand-strong:#0b365c;--ok:#177245;--ok-soft:#dff5e9;--danger:#b42318;--danger-soft:#fde8e5;--blue-soft:#e2efff;--shadow:0 24px 56px rgba(15,23,42,.10)}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif}
body{background:radial-gradient(circle at top left, rgba(15,76,129,.12), transparent 24%),linear-gradient(180deg,#f9fbfd 0%,var(--bg) 100%);color:var(--ink);padding:28px}
a{text-decoration:none;color:inherit}
.shell{max-width:1320px;margin:0 auto}
.hero,.panel,.card,.notice{background:rgba(255,255,255,.97);border:1px solid rgba(214,224,234,.95);box-shadow:var(--shadow);border-radius:28px}
.hero{padding:28px;margin-bottom:18px}
.hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap}
.hero h1{font-size:34px;letter-spacing:-.04em;margin-bottom:8px}
.hero p{color:var(--muted);line-height:1.8;max-width:860px}
.hero-actions{display:flex;gap:10px;flex-wrap:wrap}
.btn,.btn-alt,.btn-danger{border:none;border-radius:16px;padding:13px 16px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:10px}
.btn{background:linear-gradient(135deg,var(--brand),var(--brand-strong));color:#fff}
.btn-alt{background:#fff;border:1px solid var(--line);color:var(--brand)}
.btn-danger{background:var(--danger-soft);color:var(--danger)}
.notice{padding:14px 16px;margin-bottom:14px;font-weight:700}
.notice.success{background:var(--ok-soft);color:var(--ok)}
.notice.error{background:var(--danger-soft);color:var(--danger)}
.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}
.card{padding:18px}
.card span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.11em;color:var(--muted);margin-bottom:8px}
.card strong{display:block;font-size:24px;letter-spacing:-.05em}
.card p{margin-top:6px;color:var(--muted);font-size:12px;line-height:1.55}
.badge{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;font-size:12px;font-weight:800}
.badge.blue{background:var(--blue-soft);color:var(--brand)}
.badge.green{background:var(--ok-soft);color:var(--ok)}
.badge.red{background:var(--danger-soft);color:var(--danger)}
.layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(320px,.85fr);gap:18px}
.panel{padding:22px}
.section{padding:18px;border-radius:22px;background:var(--surface-soft);border:1px solid var(--line);margin-bottom:16px}
.section h2,.section h3{margin-bottom:12px}
.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.preview-box{padding:14px;border-radius:18px;background:#fff;border:1px solid var(--line)}
.preview-box p{line-height:1.75}
.small-note{font-size:12px;color:var(--muted);line-height:1.7}
.field{margin-bottom:16px}
.field label{display:block;margin-bottom:8px;font-size:13px;font-weight:700;color:#344658}
.field input,.field textarea{width:100%;padding:14px 15px;border-radius:16px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:14px}
.field textarea{min-height:140px;resize:vertical}
.helper{font-size:12px;color:var(--muted);line-height:1.6;margin-top:6px}
@media (max-width:1100px){.layout,.summary,.detail-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="hero-top">
            <div>
                <span class="badge <?= htmlspecialchars($visibilityClass) ?>"><?= htmlspecialchars($visibilityLabel) ?></span>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <p><?= htmlspecialchars($pageIntro) ?></p>
            </div>
            <div class="hero-actions">
                <a class="btn-alt" href="<?= htmlspecialchars($backHref) ?>"><i class="fa fa-arrow-left"></i> Back to Jobs</a>
                <a class="btn-alt" href="../jobs.php" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square"></i> Preview Public Jobs Page</a>
            </div>
        </div>
        <div class="summary">
            <article class="card"><span>Job Role</span><strong><?= htmlspecialchars($roleLabel) ?></strong><p><?= htmlspecialchars($companyLabel) ?></p></article>
            <article class="card"><span>Visibility</span><strong><?= htmlspecialchars($visibilityLabel) ?></strong><p>Control whether this role appears on the public jobs page.</p></article>
            <article class="card"><span>Applications</span><strong><?= (int) ($jobStats['total'] ?? 0) ?></strong><p><?= (int) ($jobStats['new'] ?? 0) ?> new applications currently waiting.</p></article>
            <article class="card"><span>Location</span><strong style="font-size:18px;"><?= htmlspecialchars($locationLabel) ?></strong><p>Update the role details below and save when ready.</p></article>
        </div>
    </section>

    <?php if (isset($_GET['saved'])): ?><div class="notice success">Job opening settings saved successfully.</div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div class="notice error">We could not open that job editor page.</div><?php endif; ?>

    <div class="layout">
        <section class="panel">
            <div class="section">
                <h2><?= htmlspecialchars($pageTitle) ?></h2>
                <form method="POST">
                    <input type="hidden" name="job_id" value="<?= (int) ($job['id'] ?? 0) ?>">
                    <?php if ($openingFilter !== 'all'): ?><input type="hidden" name="opening_filter" value="<?= htmlspecialchars($openingFilter) ?>"><?php endif; ?>
                    <div class="detail-grid">
                        <div class="field"><label for="slug">Job Slug</label><input id="slug" type="text" name="slug" value="<?= htmlspecialchars((string) ($job['slug'] ?? '')) ?>" placeholder="operations-coordinator"></div>
                        <div class="field"><label for="sort_order">Sort Order</label><input id="sort_order" type="number" name="sort_order" value="<?= htmlspecialchars((string) ($job['sort_order'] ?? '1')) ?>"></div>
                        <div class="field"><label for="company_name">Company Name</label><input id="company_name" type="text" name="company_name" value="<?= htmlspecialchars((string) ($job['company_name'] ?? 'Sample Company')) ?>" required></div>
                        <div class="field"><label for="title">Job Title</label><input id="title" type="text" name="title" value="<?= htmlspecialchars((string) ($job['title'] ?? '')) ?>" required></div>
                        <div class="field"><label for="badge_text">Badge Text</label><input id="badge_text" type="text" name="badge_text" value="<?= htmlspecialchars((string) ($job['badge_text'] ?? 'Hiring Now')) ?>"></div>
                        <div class="field"><label for="employment_type">Employment Type</label><input id="employment_type" type="text" name="employment_type" value="<?= htmlspecialchars((string) ($job['employment_type'] ?? 'Full-time')) ?>"></div>
                    </div>
                    <div class="field"><label for="location">Location</label><input id="location" type="text" name="location" value="<?= htmlspecialchars((string) ($job['location'] ?? '')) ?>" required></div>
                    <div class="field"><label for="summary">Summary</label><textarea id="summary" name="summary"><?= htmlspecialchars((string) ($job['summary'] ?? '')) ?></textarea></div>
                    <div class="field"><label for="description">Role Description</label><textarea id="description" name="description"><?= htmlspecialchars((string) ($job['description'] ?? '')) ?></textarea></div>
                    <div class="field"><label for="requirements">Requirements</label><textarea id="requirements" name="requirements"><?= htmlspecialchars((string) ($job['requirements'] ?? '')) ?></textarea></div>
                    <div class="field"><label for="application_instructions">Application Instructions</label><textarea id="application_instructions" name="application_instructions"><?= htmlspecialchars((string) ($job['application_instructions'] ?? '')) ?></textarea></div>
                    <div class="field"><label><input type="checkbox" name="is_active" value="1"<?= !isset($job['is_active']) || !empty($job['is_active']) ? ' checked' : '' ?>> Show this job on the public job portal</label></div>
                    <div class="detail-grid">
                        <div class="field"><label for="interview_subject">Acceptance Subject</label><input id="interview_subject" type="text" name="interview_subject" value="<?= htmlspecialchars((string) ($job['interview_subject'] ?? '')) ?>"></div>
                        <div class="field"><label for="rejection_subject">Rejection Subject</label><input id="rejection_subject" type="text" name="rejection_subject" value="<?= htmlspecialchars((string) ($job['rejection_subject'] ?? '')) ?>"></div>
                    </div>
                    <div class="field"><label for="submission_subject">Application Receipt Subject</label><input id="submission_subject" type="text" name="submission_subject" value="<?= htmlspecialchars((string) ($job['submission_subject'] ?? '')) ?>"></div>
                    <div class="field"><label for="submission_message">Application Receipt Message</label><textarea id="submission_message" name="submission_message"><?= htmlspecialchars((string) ($job['submission_message'] ?? '')) ?></textarea><div class="helper">This message is sent automatically to the applicant after submission. Available placeholders: {{full_name}}, {{job_title}}, {{company_name}}, {{location}}, {{application_code}}, {{email}}, {{phone}}</div></div>
                    <div class="field"><label for="interview_message">Acceptance Message Template</label><textarea id="interview_message" name="interview_message"><?= htmlspecialchars((string) ($job['interview_message'] ?? '')) ?></textarea></div>
                    <div class="field"><label for="rejection_message">Rejection Message Template</label><textarea id="rejection_message" name="rejection_message"><?= htmlspecialchars((string) ($job['rejection_message'] ?? '')) ?></textarea><div class="helper">Available placeholders: {{full_name}}, {{job_title}}, {{company_name}}, {{location}}, {{application_code}}, {{email}}, {{phone}}</div></div>
                    <div class="hero-actions">
                        <button class="btn" type="submit" name="save_job_opening"><i class="fa fa-floppy-disk"></i> Save Job Opening</button>
                        <?php if ($job): ?>
                            <button class="btn-danger" type="submit" name="delete_job_opening" onclick="return confirm('Delete this job opening and every application under it?');"><i class="fa fa-trash"></i> Delete Job Opening</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <aside class="panel">
            <div class="section">
                <h3>What changed</h3>
                <p class="small-note">Job editing now lives on this separate page so the main jobs workspace can stay focused on openings, applicants, and review actions.</p>
            </div>
            <div class="section">
                <h3>Role Snapshot</h3>
                <div class="detail-grid">
                    <div class="preview-box"><span class="small-note">Status</span><p><?= htmlspecialchars($visibilityLabel) ?></p></div>
                    <div class="preview-box"><span class="small-note">Applications</span><p><?= (int) ($jobStats['total'] ?? 0) ?></p></div>
                    <div class="preview-box"><span class="small-note">Accepted</span><p><?= (int) ($jobStats['invited'] ?? 0) ?></p></div>
                    <div class="preview-box"><span class="small-note">Rejected</span><p><?= (int) ($jobStats['rejected'] ?? 0) ?></p></div>
                </div>
                <div class="preview-box" style="margin-top:14px;"><span class="small-note">Summary</span><p><?= nl2br(htmlspecialchars((string) ($job['summary'] ?? 'Add a summary to help the team understand this opening quickly.'))) ?></p></div>
            </div>
        </aside>
    </div>
</div>
</body>
</html>