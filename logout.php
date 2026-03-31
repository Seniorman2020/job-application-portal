<?php
require_once __DIR__ . '/includes/admin_auth.php';
portal_admin_logout();
app_redirect('admin/login.php');