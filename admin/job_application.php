<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin_auth();
require_once __DIR__ . '/../includes/jobs_portal.php';

$conn = app_db();
ensure_jobs_portal_schema($conn);

$actorUserId = trim((string) ($_SESSION['portal_admin_email'] ?? portal_admin_email()));
$jobId = (int) ($_POST['job_id'] ?? $_GET['job_id'] ?? 0);
$applicationId = (int) ($_POST['application_id'] ?? $_GET['application_id'] ?? 0);
$adminJobApplicationPath = 'job_application.php';
$adminJobsPath = 'jobs.php';

$job = fetch_job_opening($conn, $jobId);
$application = fetch_job_application($conn, $applicationId);

if (!$job || !$application || (int) ($application['job_id'] ?? 0) !== (int) ($job['id'] ?? 0)) {
    app_redirect($adminJobsPath . '?error=1');
}

if (($_GET['download'] ?? '') === 'csv') {
    $filename = job_application_export_filename($job, $application);
    $content = job_application_csv_content($job, $application);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $content;
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $baseRedirect = $adminJobApplicationPath . '?job_id=' . (int) $job['id'] . '&application_id=' . (int) $application['id'];

    if (isset($_POST['send_package_email'])) {
        $destinationEmail = trim((string) ($_POST['destination_email'] ?? ''));
        if (!filter_var($destinationEmail, FILTER_VALIDATE_EMAIL)) {
            app_redirect($baseRedirect . '&mail_invalid=1');
        }
        $sent = send_job_application_package_email($job, $application, $destinationEmail);
        app_redirect($baseRedirect . ($sent ? '&mailed=1' : '&mail_failed=1'));
    }

    if (isset($_POST['accept_application'])) {
        $subject = trim((string) ($_POST['accept_subject'] ?? ''));
        $message = trim((string) ($_POST['accept_message'] ?? ''));
        $sent = send_job_application_email($job, $application, $subject, $message);
        $status = 'invited';
        $stmt = $conn->prepare("UPDATE job_applications SET status=?, invite_subject=?, invite_message=?, reviewed_by=?, invited_at=NOW() WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('ssssi', $status, $subject, $message, $actorUserId, $applicationId);
            $stmt->execute();
        }
        app_redirect($baseRedirect . ($sent ? '&accepted=1' : '&mail_failed=1'));
    }

    if (isset($_POST['reject_application'])) {
        $subject = trim((string) ($_POST['reject_subject'] ?? ''));
        $message = trim((string) ($_POST['reject_message'] ?? ''));
        $sent = send_job_application_email($job, $application, $subject, $message);
        $status = 'rejected';
        $stmt = $conn->prepare("UPDATE job_applications SET status=?, rejection_subject=?, rejection_message=?, reviewed_by=?, rejected_at=NOW() WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('ssssi', $status, $subject, $message, $actorUserId, $applicationId);
            $stmt->execute();
        }
        app_redirect($baseRedirect . ($sent ? '&rejected=1' : '&mail_failed=1'));
    }

    if (isset($_POST['delete_application'])) {
        delete_job_application($conn, $applicationId);
        app_redirect($adminJobsPath . '?id=' . (int) $job['id'] . '&deleted_application=1');
    }

    if (isset($_POST['delete_job_opening'])) {
        delete_job_opening($conn, (int) $job['id']);
        app_redirect($adminJobsPath . '?deleted=1');
    }
}

$job = fetch_job_opening($conn, $jobId);
$application = fetch_job_application($conn, $applicationId);
if (!$job || !$application || (int) ($application['job_id'] ?? 0) !== (int) ($job['id'] ?? 0)) {
    app_redirect($adminJobsPath . '?error=1');
}

$acceptSubjectDefault = render_job_template((string) ($job['interview_subject'] ?? ''), $job, $application);
$acceptMessageDefault = render_job_template((string) ($job['interview_message'] ?? ''), $job, $application);
$rejectSubjectDefault = render_job_template((string) ($job['rejection_subject'] ?? ''), $job, $application);
$rejectMessageDefault = render_job_template((string) ($job['rejection_message'] ?? ''), $job, $application);
$applicationRows = job_application_export_rows($job, $application);
$passportUrl = job_file_url($application, 'passport_file', false);
$passportDownloadUrl = job_file_url($application, 'passport_file', true);
$cvUrl = job_file_url($application, 'cv_file', false);
$cvDownloadUrl = job_file_url($application, 'cv_file', true);
$passportAvailable = job_file_is_available($application, 'passport_file');
$cvAvailable = job_file_is_available($application, 'cv_file');
$passportRecorded = job_file_recorded($application, 'passport_file');
$cvRecorded = job_file_recorded($application, 'cv_file');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application Review | Job Application Portal</title>
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
.hero-actions,.action-row{display:flex;gap:10px;flex-wrap:wrap}
.btn,.btn-alt,.btn-danger{border:none;border-radius:16px;padding:13px 16px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:10px}
.btn{background:linear-gradient(135deg,var(--brand),var(--brand-strong));color:#fff}
.btn-alt{background:#fff;border:1px solid var(--line);color:var(--brand)}
.btn-danger{background:var(--danger-soft);color:var(--danger)}
.notice{padding:14px 16px;margin-bottom:14px;font-weight:700}
.notice.success{background:var(--ok-soft);color:var(--ok)}
.notice.error{background:var(--danger-soft);color:var(--danger)}
.layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(340px,.9fr);gap:18px}
.panel{padding:22px}
.section{padding:18px;border-radius:22px;background:var(--surface-soft);border:1px solid var(--line);margin-bottom:16px}
.section h2,.section h3{margin-bottom:12px}
.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}
.card{padding:18px}
.card span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.11em;color:var(--muted);margin-bottom:8px}
.card strong{display:block;font-size:26px;letter-spacing:-.05em}
.card p{margin-top:6px;color:var(--muted);font-size:12px;line-height:1.55}
.badge{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;font-size:12px;font-weight:800}
.badge.blue{background:var(--blue-soft);color:var(--brand)}
.badge.green{background:var(--ok-soft);color:var(--ok)}
.badge.red{background:var(--danger-soft);color:var(--danger)}
.data-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:20px;overflow:hidden}
.data-table th,.data-table td{padding:14px 16px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}
.data-table th{width:240px;background:#f8fbff;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#38516b}
.data-table tr:last-child th,.data-table tr:last-child td{border-bottom:none}
.field{margin-bottom:14px}
.field label{display:block;margin-bottom:8px;font-size:13px;font-weight:700;color:#344658}
.field input,.field textarea{width:100%;padding:14px 15px;border-radius:16px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:14px}
.field textarea{min-height:150px;resize:vertical}
.helper{font-size:12px;color:var(--muted);line-height:1.6}
.doc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.preview-box{padding:14px;border-radius:18px;background:#fff;border:1px solid var(--line)}
.preview-box img{width:100%;max-height:340px;object-fit:cover;border-radius:14px;background:#f3f6f9}
.preview-box iframe{width:100%;height:420px;border:none;border-radius:14px;background:#f3f6f9}
.doc-warning{margin-top:12px;padding:14px 15px;border-radius:16px;background:#fff7ec;border:1px solid #f2ddae;color:#8a611c;font-size:13px;line-height:1.75}
.mini-list{display:grid;gap:12px}
.mini-item{padding:14px 16px;border-radius:18px;background:#fff;border:1px solid var(--line)}
.mini-item strong{display:block;margin-bottom:4px}
.danger-zone{border-color:#f2c7c2;background:#fff7f7}
@media (max-width:1100px){.layout,.summary,.doc-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="hero-top">
            <div>
                <span class="badge <?= htmlspecialchars(job_status_badge_class((string) ($application['status'] ?? 'new'))) ?>"><?= htmlspecialchars(job_status_label((string) ($application['status'] ?? 'new'))) ?></span>
                <h1><?= htmlspecialchars((string) ($application['full_name'] ?? 'Applicant')) ?></h1>
                <p>Review the complete application for <?= htmlspecialchars((string) ($job['title'] ?? 'this role')) ?>, inspect uploaded documents, export the application table, forward the package by email, and decide whether to accept or reject the applicant from this page.</p>
            </div>
            <div class="hero-actions">
                <a class="btn-alt" href="jobs.php?id=<?= (int) $job['id'] ?>"><i class="fa fa-arrow-left"></i> Back to Jobs</a>
                <a class="btn-alt" href="job_application.php?job_id=<?= (int) $job['id'] ?>&application_id=<?= (int) $application['id'] ?>&download=csv"><i class="fa fa-file-arrow-down"></i> Download Application CSV</a>
            </div>
        </div>
        <div class="summary">
            <article class="card"><span>Application Code</span><strong><?= htmlspecialchars((string) ($application['application_code'] ?? '')) ?></strong><p>Share or save this number for future follow-up.</p></article>
            <article class="card"><span>Job Role</span><strong><?= htmlspecialchars((string) ($job['title'] ?? '')) ?></strong><p><?= htmlspecialchars((string) ($job['company_name'] ?? '')) ?></p></article>
            <article class="card"><span>Email Address</span><strong style="font-size:20px;"><?= htmlspecialchars((string) ($application['email'] ?? '')) ?></strong><p><?= htmlspecialchars((string) ($application['phone'] ?? '')) ?></p></article>
            <article class="card"><span>Submitted</span><strong style="font-size:20px;"><?= htmlspecialchars(job_datetime_label((string) ($application['created_at'] ?? ''))) ?></strong><p>Current status: <?= htmlspecialchars(job_status_label((string) ($application['status'] ?? 'new'))) ?></p></article>
        </div>
    </section>

    <?php if (isset($_GET['accepted'])): ?><div class="notice success">Applicant status updated to accepted and the message was sent successfully.</div><?php endif; ?>
    <?php if (isset($_GET['rejected'])): ?><div class="notice success">Applicant status updated to not selected and the message was sent successfully.</div><?php endif; ?>
    <?php if (isset($_GET['mailed'])): ?><div class="notice success">The application package was sent successfully to the requested email address.</div><?php endif; ?>
    <?php if (isset($_GET['mail_invalid'])): ?><div class="notice error">Enter a valid destination email address before sending the application package.</div><?php endif; ?>
    <?php if (isset($_GET['mail_failed'])): ?><div class="notice error">The application record was updated, but the email could not be sent.</div><?php endif; ?>

    <div class="layout">
        <div>
            <section class="panel">
                <div class="section">
                    <h2>Application Details</h2>
                    <table class="data-table">
                        <tbody>
                        <?php foreach ($applicationRows as $row): ?>
                            <tr>
                                <th><?= htmlspecialchars((string) $row[0]) ?></th>
                                <td><?= nl2br(htmlspecialchars((string) $row[1])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="section">
                    <h3>Applicant Documents</h3>
                    <div class="doc-grid">
                        <div class="preview-box">
                            <strong>Profile Photo</strong>
                            <?php if ($passportUrl !== '' && $passportAvailable): ?>
                                <img src="<?= htmlspecialchars($passportUrl) ?>" alt="Applicant passport photograph">
                                <div class="action-row" style="margin-top:12px;">
                                    <a class="btn-alt" href="<?= htmlspecialchars($passportUrl) ?>" target="_blank" rel="noopener"><i class="fa fa-image"></i> View Photo</a>
                                    <a class="btn-alt" href="<?= htmlspecialchars($passportDownloadUrl) ?>" target="_blank" rel="noopener"><i class="fa fa-download"></i> Download Photo</a>
                                </div>
                            <?php elseif ($passportRecorded): ?>
                                <div class="doc-warning">A profile photo file was recorded for this applicant, but the file is currently missing from storage.</div>
                            <?php else: ?>
                                <p class="helper">No profile photo is available for this application.</p>
                            <?php endif; ?>
                        </div>
                        <div class="preview-box">
                            <strong>Resume</strong>
                            <?php if ($cvUrl !== '' && $cvAvailable): ?>
                                <iframe src="<?= htmlspecialchars($cvUrl) ?>"></iframe>
                                <div class="action-row" style="margin-top:12px;">
                                    <a class="btn-alt" href="<?= htmlspecialchars($cvUrl) ?>" target="_blank" rel="noopener"><i class="fa fa-eye"></i> View Resume</a>
                                    <a class="btn-alt" href="<?= htmlspecialchars($cvDownloadUrl) ?>" target="_blank" rel="noopener"><i class="fa fa-file-arrow-down"></i> Download Resume</a>
                                </div>
                            <?php elseif ($cvRecorded): ?>
                                <div class="doc-warning">A resume file was recorded for this applicant, but the file is currently missing from storage.</div>
                            <?php else: ?>
                                <p class="helper">No resume is available for this application.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div>
            <section class="panel">
                <div class="section">
                    <h3>Application Package</h3>
                    <div class="mini-list">
                        <div class="mini-item">
                            <strong>Download Complete Application</strong>
                            <p class="helper">This downloads the Excel-ready application table for this applicant.</p>
                            <div class="action-row" style="margin-top:12px;">
                                <a class="btn-alt" href="job_application.php?job_id=<?= (int) $job['id'] ?>&application_id=<?= (int) $application['id'] ?>&download=csv"><i class="fa fa-download"></i> Download CSV</a>
                            </div>
                        </div>
                        <div class="mini-item">
                            <strong>Forward Application By Email</strong>
                            <p class="helper">The email package includes the application table, profile photo, and resume where available.</p>
                            <form method="POST" style="margin-top:12px;">
                                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                                <div class="field"><label for="destination_email">Destination Email</label><input id="destination_email" type="email" name="destination_email" placeholder="Enter the email address to receive this application" required></div>
                                <button class="btn" type="submit" name="send_package_email"><i class="fa fa-paper-plane"></i> Send Application Package</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h3>Screen and Accept Applicant</h3>
                    <form method="POST">
                        <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                        <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                        <div class="field"><label for="accept_subject">Acceptance Subject</label><input id="accept_subject" type="text" name="accept_subject" value="<?= htmlspecialchars($acceptSubjectDefault) ?>"></div>
                        <div class="field"><label for="accept_message">Acceptance Message</label><textarea id="accept_message" name="accept_message"><?= htmlspecialchars($acceptMessageDefault) ?></textarea></div>
                        <button class="btn" type="submit" name="accept_application"><i class="fa fa-circle-check"></i> Accept Applicant</button>
                    </form>
                </div>

                <div class="section">
                    <h3>Reject Applicant</h3>
                    <form method="POST">
                        <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                        <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                        <div class="field"><label for="reject_subject">Rejection Subject</label><input id="reject_subject" type="text" name="reject_subject" value="<?= htmlspecialchars($rejectSubjectDefault) ?>"></div>
                        <div class="field"><label for="reject_message">Rejection Message</label><textarea id="reject_message" name="reject_message"><?= htmlspecialchars($rejectMessageDefault) ?></textarea></div>
                        <button class="btn-danger" type="submit" name="reject_application"><i class="fa fa-envelope-circle-xmark"></i> Reject Applicant</button>
                    </form>
                </div>

                <div class="section danger-zone">
                    <h3>Delete Actions</h3>
                    <div class="mini-list">
                        <div class="mini-item">
                            <strong>Delete Application Record</strong>
                            <p class="helper">This permanently removes the applicant record and uploaded files from the database and storage.</p>
                            <form method="POST" style="margin-top:12px;">
                                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                                <button class="btn-danger" type="submit" name="delete_application" onclick="return confirm('Delete this applicant record and all uploaded files permanently?');"><i class="fa fa-trash"></i> Delete Application</button>
                            </form>
                        </div>
                        <div class="mini-item">
                            <strong>Delete Job Opening</strong>
                            <p class="helper">This permanently removes the job opening and every application linked to it.</p>
                            <form method="POST" style="margin-top:12px;">
                                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                                <button class="btn-danger" type="submit" name="delete_job_opening" onclick="return confirm('Delete this entire job opening and every related application permanently?');"><i class="fa fa-trash-can"></i> Delete Job Opening</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
</body>
</html>
