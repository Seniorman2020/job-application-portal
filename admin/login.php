<?php
require_once __DIR__ . '/../includes/admin_auth.php';

if (portal_admin_is_logged_in()) {
    app_redirect('jobs.php');
}

$errorMessage = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (portal_admin_login_attempt($email, $password)) {
        app_redirect('jobs.php');
    }
    $errorMessage = 'The admin email or password is incorrect.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | Job Application Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#eef4f8;--surface:#fff;--line:#dbe6ef;--ink:#102133;--muted:#607186;--brand:#0f4c81;--brand-strong:#0b365c;--danger:#b42318;--danger-soft:#fde8e5;--shadow:0 24px 56px rgba(15,23,42,.10)}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif}
body{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at top left, rgba(15,76,129,.12), transparent 24%),linear-gradient(180deg,#f9fbfd 0%,var(--bg) 100%);color:var(--ink)}
.shell{width:min(460px,100%)}.card{background:rgba(255,255,255,.97);border:1px solid rgba(214,224,234,.95);box-shadow:var(--shadow);border-radius:28px;padding:28px}h1{font-size:32px;letter-spacing:-.04em;margin-bottom:10px}p{color:var(--muted);line-height:1.8;margin-bottom:18px}.notice{padding:14px 16px;margin-bottom:14px;border-radius:18px;font-weight:700}.notice.error{background:var(--danger-soft);color:var(--danger)}.field{margin-bottom:14px}.field label{display:block;margin-bottom:8px;font-size:13px;font-weight:700;color:#344658}.field input{width:100%;padding:14px 15px;border-radius:16px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:14px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn,.btn-alt{border:none;border-radius:16px;padding:13px 16px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.btn{background:linear-gradient(135deg,var(--brand),var(--brand-strong));color:#fff}.btn-alt{background:#fff;border:1px solid var(--line);color:var(--brand)}.helper{font-size:12px;color:var(--muted);line-height:1.7;margin-top:16px}
</style>
</head>
<body>
<div class="shell">
    <div class="card">
        <h1>Admin Login</h1>
        <p>Sign in to manage job openings, review applications, and update applicant statuses.</p>
        <?php if ($errorMessage !== ''): ?><div class="notice error"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
        <form method="POST">
            <div class="field"><label for="email">Admin Email</label><input id="email" type="email" name="email" value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>" required></div>
            <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
            <div class="actions"><button class="btn" type="submit">Sign In</button><a class="btn-alt" href="../jobs.php">Back to Public Jobs</a></div>
        </form>
        <p class="helper">Set the admin credentials in <code>config.local.php</code> or environment variables before publishing or demoing the project.</p>
    </div>
</div>
</body>
</html>