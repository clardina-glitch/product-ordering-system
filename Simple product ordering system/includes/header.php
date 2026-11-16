<?php
$pageTitle    = $pageTitle    ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$activePage   = $activePage   ?? '';
$user         = current_user();
$flashes      = get_flashes();
$initial      = strtoupper(mb_substr($user['name'], 0, 1));
$isOwner      = $user['role'] === 'owner';
$roleLabel    = $isOwner ? 'Owner' : 'Customer';
$homeLink     = app_url($isOwner ? 'owner/dashboard.php' : 'customer/order.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · OrderDesk</title>
    <link rel="icon" href="<?= e(app_url('assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= e($homeLink) ?>">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </span>
            <span class="brand-text">
                <strong>OrderDesk</strong>
                <small><?= $isOwner ? 'Owner workspace' : 'Customer portal' ?></small>
            </span>
        </a>

        <nav class="nav" aria-label="Main navigation">
            <?php if ($isOwner): ?>
                <span class="nav-label">Owner</span>
                <a class="nav-link <?= $activePage === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(app_url('owner/dashboard.php')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/>
                        <rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link <?= $activePage === 'products' ? 'is-active' : '' ?>" href="<?= e(app_url('owner/products.php')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>
                    </svg>
                    <span>Products</span>
                </a>
                <a class="nav-link <?= $activePage === 'orders' ? 'is-active' : '' ?>" href="<?= e(app_url('owner/orders.php')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>
                    </svg>
                    <span>Customer Orders</span>
                </a>
            <?php else: ?>
                <span class="nav-label">Shop</span>
                <a class="nav-link <?= $activePage === 'order' ? 'is-active' : '' ?>" href="<?= e(app_url('customer/order.php')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>
                        <path d="M2 3h3l2.4 12.2a2 2 0 0 0 2 1.6h8.5a2 2 0 0 0 2-1.6L21 7H6"/>
                    </svg>
                    <span>Place Order</span>
                </a>
                <a class="nav-link <?= $activePage === 'my_orders' ? 'is-active' : '' ?>" href="<?= e(app_url('customer/my_orders.php')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>
                    </svg>
                    <span>My Orders</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="user-chip">
                <span class="avatar"><?= e($initial) ?></span>
                <span class="user-meta">
                    <strong><?= e($user['name']) ?></strong>
                    <small>@<?= e($user['username']) ?> · <?= e($roleLabel) ?></small>
                </span>
            </div>
            <form method="post" action="<?= e(app_url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="signout" type="submit" title="Sign out">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                    </svg>
                    <span>Sign out</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

    <div class="main">
        <header class="topbar">
            <button class="menu-btn" id="menuBtn" type="button" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M3 6h18M3 12h18M3 18h18"/>
                </svg>
            </button>
            <div class="topbar-title">
                <h1><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle !== ''): ?>
                    <p><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar-right">
                <span class="badge <?= $isOwner ? 'badge-info' : 'badge-success' ?>"><?= e($roleLabel) ?></span>
                <span class="date-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>
                    </svg>
                    <?= e(date('D, M j')) ?>
                </span>
            </div>
        </header>

        <main class="content">
            <?php if ($flashes): ?>
                <div class="flash-stack">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash flash-<?= e($flash['type']) ?>" role="status">
                            <span class="flash-icon">
                                <?php if ($flash['type'] === 'success'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 13 4 4L19 7"/></svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                                <?php endif; ?>
                            </span>
                            <span><?= e($flash['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
