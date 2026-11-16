<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    redirect(app_url('login.php'));
}

// Send each account to the area that belongs to its role.
redirect(home_url());
