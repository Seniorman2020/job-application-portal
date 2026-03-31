<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin_auth();
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/jobs_portal.php';

$conn = app_db();
ensure_jobs_portal_schema($conn);

$selectedJobId = (int) ($_GET['id'] ?? 0);
$applicationStatusFilter = strtolower(trim((string) ($_GET['application_status'] ?? 'all')));
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $selectedJobId > 0) {
    export_job_applications_csv($conn, $selectedJobId);
}

$openings = fetch_job_openings($conn, true);
if ($selectedJobId === 0 && !empty($openings)) {
    $selectedJobId = (int) $openings[0]['id'];
}

$selectedJob = $selectedJobId > 0 ? fetch_job_opening($conn, $selectedJobId) : null;
$jobStats = $selectedJob ? job_opening_stat_counts($conn, (int) $selectedJob['id']) : ['total' => 0, 'new' => 0, 'invited' => 0, 'rejected' => 0];
$applications = $selectedJob ? fetch_job_applications($conn, (int) $selectedJob['id'], $applicationStatusFilter) : [];
$openingCount = count($openings);
$activeOpenings = 0;
foreach ($openings as $opening) {
    if (!empty($opening['is_active'])) {
        $activeOpenings++;
    }
}
$inactiveOpenings = max(0, $openingCount - $activeOpenings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Jobs | Job Application Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#eef4f8;--surface:#fff;--surface-soft:#f7fafc;--line:#d9e4ed;--ink:#122235;--muted:#607185;--brand:#0f4c81;--brand-strong:#0b365c;--success:#177245;--success-soft:#dff5e9;--danger:#b42318;--danger-soft:#fde8e5;--blue-soft:#e2efff;--shadow:0 24px 56px rgba(15,23,42,.10)}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif}body{min-height:100vh;padding:24px;background:radial-gradient(circle at top left, rgba(15,76,129,.12), transparent 22%),linear-gradient(180deg,#f9fbfd 0%,var(--bg) 100%);color:var(--ink)}a{text-decoration:none;color:inherit}.shell{max-width:1320px;margin:0 auto}.hero,.panel,.card,.notice{background:rgba(255,255,255,.97);border:1px solid rgba(214,224,234,.95);box-shadow:var(--shadow);border-radius:28px}.hero{padding:26px 28px;margin-bottom:18px;background:linear-gradient(135deg,#ffffff 0%,#f5f9fc 100%)}.hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap}.hero h1{font-size:34px;letter-spacing:-.04em;margin-bottom:8px}.hero p{max-width:860px;color:var(--muted);line-height:1.8}.hero-actions{display:flex;gap:10px;flex-wrap:wrap}.btn,.btn-alt{border:none;border-radius:16px;padding:13px 16px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:10px}.btn{background:linear-gradient(135deg,var(--brand),var(--brand-strong));color:#fff}.btn-alt{background:#fff;border:1px solid var(--line);color:var(--brand)}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.card{padding:18px}.card span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.11em;color:var(--muted);margin-bottom:8px}.card strong{display:block;font-size:30px;letter-spacing:-.05em}.card p{margin-top:6px;color:var(--muted);font-size:12px;line-height:1.55}.layout{display:grid;grid-template-columns:1.05fr .95fr;gap:18px}.panel{padding:22px}.panel h2{font-size:22px;margin-bottom:10px}.panel-intro{margin-bottom:16px;color:var(--muted);line-height:1.8}.table-wrap{overflow:auto;border-radius:20px;border:1px solid var(--line);background:#fff}.table{width:100%;border-collapse:collapse;min-width:760px}.table th,.table td{padding:14px 12px;border-bottom:1px solid #edf2f7;text-align:left;font-size:13px;vertical-align:top}.table th{background:#f8fbff;color:#38516b;font-size:12px;text-transform:uppercase;letter-spacing:.08em}.table tr:last-child td{border-bottom:none}.badge{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;font-size:12px;font-weight:800}.badge.green{background:var(--success-soft);color:var(--success)}.badge.red{background:var(--danger-soft);color:var(--danger)}.badge.blue{background:var(--blue-soft);color:var(--brand)}.mini-grid{display:grid;gap:12px}.mini-item{padding:16px;border-radius:22px;background:var(--surface-soft);border:1px solid var(--line)}.mini-item strong{display:block;font-size:16px;margin-bottom:6px}.mini-item p{color:var(--muted);line-height:1.7}.mini-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px}.toolbar form{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.preview-box{padding:14px;border-radius:18px;background:#fff;border:1px solid var(--line)}.preview-box p{line-height:1.75}.empty{padding:18px;color:var(--muted)}.small-note{font-size:12px;color:var(--muted);line-height:1.7}@media (max-width:1080px){.summary,.layout,.detail-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="hero-top">
            <div>
                <h1>Job Management</h1>
                <p>Manage openings, review applicants, and move candidates through your hiring workflow from one focused admin workspace.</p>
            </div>
            <div class="hero-actions">
                <a class="btn" href="job_opening.php"><i class="fa fa-plus"></i> New Job Opening</a>
                <a class="btn-alt" href="../jobs.php"><i class="fa fa-arrow-up-right-from-square"></i> Public Jobs Page</a>
                <a class="btn-alt" href="../logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </section>

    <?php if (isset($_GET['deleted'])): ?><div class="notice" style="padding:14px 16px;margin-bottom:14px;background:#dff5e9;color:#177245;border-radius:18px;font-weight:700;">Job opening removed successfully.</div><?php endif; ?>
    <?php if (isset($_GET['deleted_application'])): ?><div class="notice" style="padding:14px 16px;margin-bottom:14px;background:#dff5e9;color:#177245;border-radius:18px;font-weight:700;">Application removed successfully.</div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div class="notice" style="padding:14px 16px;margin-bottom:14px;background:#fde8e5;color:#b42318;border-radius:18px;font-weight:700;">The requested job or application could not be found.</div><?php endif; ?>

    <section class="summary">
        <article class="card"><span>Total Openings</span><strong><?= $openingCount ?></strong><p>All job entries currently saved in the portal.</p></article>
        <article class="card"><span>Active Openings</span><strong><?= $activeOpenings ?></strong><p>Openings currently visible on the public jobs page.</p></article>
        <article class="card"><span>Inactive Openings</span><strong><?= $inactiveOpenings ?></strong><p>Roles that are saved but hidden from public view.</p></article>
        <article class="card"><span>Applications</span><strong><?= (int) ($jobStats['total'] ?? 0) ?></strong><p>Applications recorded for the selected role.</p></article>
    </section>

    <div class="layout">
        <section class="panel">
            <h2>Job Openings</h2>
            <p class="panel-intro">Select a role to review its applicants, export application data, or open the dedicated editor page.</p>
            <div class="mini-grid">
                <?php if (!empty($openings)): ?>
                    <?php foreach ($openings as $opening): ?>
                        <?php $counts = job_opening_stat_counts($conn, (int) $opening['id']); ?>
                        <div class="mini-item">
                            <strong><?= htmlspecialchars((string) ($opening['title'] ?? 'Untitled role')) ?></strong>
                            <p><?= htmlspecialchars((string) ($opening['company_name'] ?? 'Sample Company')) ?><?= !empty($opening['location']) ? ' | ' . htmlspecialchars((string) $opening['location']) : '' ?></p>
                            <div class="mini-actions"><span class="badge <?= !empty($opening['is_active']) ? 'green' : 'red' ?>"><?= !empty($opening['is_active']) ? 'Active' : 'Inactive' ?></span><span class="badge blue"><?= (int) ($counts['total'] ?? 0) ?> applications</span></div>
                            <div class="mini-actions"><a class="btn-alt" href="jobs.php?id=<?= (int) $opening['id'] ?>"><i class="fa fa-list"></i> View Applications</a><a class="btn-alt" href="job_opening.php?job_id=<?= (int) $opening['id'] ?>"><i class="fa fa-pen-to-square"></i> Edit Job</a></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">No job opening has been created yet.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="toolbar">
                <div>
                    <h2><?= $selectedJob ? 'Applications for ' . htmlspecialchars((string) $selectedJob['title']) : 'Applications' ?></h2>
                    <p class="panel-intro" style="margin:6px 0 0;">Use the review page to inspect documents, send status updates, and download a CSV export for each applicant.</p>
                </div>
                <?php if ($selectedJob): ?>
                    <form method="GET">
                        <input type="hidden" name="id" value="<?= (int) $selectedJob['id'] ?>">
                        <select name="application_status"><option value="all"<?= $applicationStatusFilter === 'all' ? ' selected' : '' ?>>All applications</option><option value="new"<?= $applicationStatusFilter === 'new' ? ' selected' : '' ?>>New</option><option value="invited"<?= $applicationStatusFilter === 'invited' ? ' selected' : '' ?>>Accepted</option><option value="rejected"<?= $applicationStatusFilter === 'rejected' ? ' selected' : '' ?>>Not selected</option><option value="archived"<?= $applicationStatusFilter === 'archived' ? ' selected' : '' ?>>Archived</option></select>
                        <button class="btn-alt" type="submit"><i class="fa fa-filter"></i> Filter</button>
                        <a class="btn-alt" href="jobs.php?id=<?= (int) $selectedJob['id'] ?>&export=csv"><i class="fa fa-file-export"></i> Export CSV</a>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($selectedJob): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Applicant</th><th>Contact</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if (!empty($applications)): ?>
                            <?php foreach ($applications as $application): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string) ($application['full_name'] ?? '')) ?></strong><br><span class="small-note"><?= htmlspecialchars((string) ($application['application_code'] ?? '')) ?></span></td>
                                    <td><?= htmlspecialchars((string) ($application['email'] ?? '')) ?><br><span class="small-note"><?= htmlspecialchars((string) ($application['phone'] ?? '')) ?></span></td>
                                    <td><span class="badge <?= htmlspecialchars(job_status_badge_class((string) ($application['status'] ?? 'new'))) ?>"><?= htmlspecialchars(job_status_label((string) ($application['status'] ?? 'new'))) ?></span></td>
                                    <td><?= htmlspecialchars(job_datetime_label((string) ($application['created_at'] ?? ''))) ?></td>
                                    <td><a class="btn-alt" href="job_application.php?job_id=<?= (int) $selectedJob['id'] ?>&application_id=<?= (int) $application['id'] ?>"><i class="fa fa-eye"></i> Review</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty">No applications recorded for this role yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="detail-grid" style="margin-top:16px;"><div class="preview-box"><span class="small-note">Selected Role</span><p><strong><?= htmlspecialchars((string) ($selectedJob['title'] ?? '')) ?></strong></p></div><div class="preview-box"><span class="small-note">Company</span><p><?= htmlspecialchars((string) ($selectedJob['company_name'] ?? '')) ?></p></div><div class="preview-box"><span class="small-note">Location</span><p><?= htmlspecialchars((string) ($selectedJob['location'] ?? 'Not specified')) ?></p></div><div class="preview-box"><span class="small-note">Summary</span><p><?= nl2br(htmlspecialchars((string) ($selectedJob['summary'] ?? 'No summary added yet.'))) ?></p></div></div>
            <?php else: ?>
                <div class="empty">Create your first job opening to start receiving applications.</div>
            <?php endif; ?>
        </section>
    </div>
</div>
</body>
</html>