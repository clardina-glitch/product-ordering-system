<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('owner');

$pdo = db();

$productCount   = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$availableCount = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_available = 1 AND stock > 0')->fetchColumn();
$orderCount     = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingCount   = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();
$customerCount  = (int) $pdo->query('SELECT COUNT(DISTINCT customer_name) FROM orders')->fetchColumn();
$revenue        = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status <> 'Cancelled'")->fetchColumn();

$recentOrders = $pdo->query(
    'SELECT id, customer_name, product_name, quantity, unit_price, total_amount, status, created_at
     FROM orders ORDER BY created_at DESC, id DESC LIMIT 6'
)->fetchAll();

$topProducts = $pdo->query(
    "SELECT product_name, SUM(quantity) AS units, SUM(total_amount) AS revenue
     FROM orders
     WHERE status <> 'Cancelled'
     GROUP BY product_name
     ORDER BY revenue DESC
     LIMIT 5"
)->fetchAll();

$lowStock = $pdo->query(
    'SELECT id, name, stock, unit_price FROM products
     WHERE is_available = 1 AND stock <= 5
     ORDER BY stock ASC, name ASC LIMIT 5'
)->fetchAll();

$maxRevenue = 0.0;
foreach ($topProducts as $product) {
    $maxRevenue = max($maxRevenue, (float) $product['revenue']);
}

$user = current_user();
$pageTitle    = 'Dashboard';
$pageSubtitle = 'A quick look at your store today.';
$activePage   = 'dashboard';

require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">Store overview</span>
        <h2>Good day, <?= e(explode(' ', $user['name'])[0]) ?>.</h2>
        <p>You have <strong><?= $pendingCount ?></strong> pending <?= $pendingCount === 1 ? 'order' : 'orders' ?> to review and <strong><?= $availableCount ?></strong> products ready to sell.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="orders.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>
            </svg>
            View customer orders
        </a>
        <a class="btn btn-ghost" href="products.php">Manage products</a>
    </div>
</section>

<section class="stats-grid" aria-label="Store statistics">
    <article class="stat-card">
        <span class="stat-icon stat-icon-blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
        </span>
        <div class="stat-meta">
            <span>Total orders</span>
            <strong><?= number_format($orderCount) ?></strong>
            <small><?= $pendingCount ?> pending</small>
        </div>
    </article>

    <article class="stat-card">
        <span class="stat-icon stat-icon-green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </span>
        <div class="stat-meta">
            <span>Total revenue</span>
            <strong><?= format_money($revenue) ?></strong>
            <small>Excluding cancelled orders</small>
        </div>
    </article>

    <article class="stat-card">
        <span class="stat-icon stat-icon-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/></svg>
        </span>
        <div class="stat-meta">
            <span>Products</span>
            <strong><?= number_format($productCount) ?></strong>
            <small><?= $availableCount ?> available now</small>
        </div>
    </article>

    <article class="stat-card">
        <span class="stat-icon stat-icon-purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 11a4 4 0 0 1 0 8"/></svg>
        </span>
        <div class="stat-meta">
            <span>Customers</span>
            <strong><?= number_format($customerCount) ?></strong>
            <small>Unique customer names</small>
        </div>
    </article>
</section>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Recent orders</h3>
                <p>Latest customer activity across your store.</p>
            </div>
            <a class="text-link" href="orders.php">View all</a>
        </div>

        <?php if (!$recentOrders): ?>
            <div class="empty-state">
                <p>No orders yet. Customer orders will appear here as soon as they are placed.</p>
                <a class="btn btn-primary btn-sm" href="products.php">Manage products</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th class="num">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td data-label="Order"><a class="order-link" href="edit_order.php?id=<?= (int) $order['id'] ?>"><?= e(order_code($order['id'])) ?></a></td>
                                <td data-label="Customer"><?= e($order['customer_name']) ?></td>
                                <td data-label="Product">
                                    <?= e($order['product_name']) ?>
                                    <span class="muted">× <?= (int) $order['quantity'] ?></span>
                                </td>
                                <td data-label="Total" class="num"><?= format_money($order['total_amount']) ?></td>
                                <td data-label="Status"><span class="badge <?= status_class($order['status']) ?>"><?= e($order['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="dashboard-side">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Top products</h3>
                    <p>By revenue, excluding cancellations.</p>
                </div>
            </div>

            <?php if (!$topProducts): ?>
                <div class="empty-state"><p>No sales data yet.</p></div>
            <?php else: ?>
                <ul class="rank-list">
                    <?php foreach ($topProducts as $index => $product): ?>
                        <?php $width = $maxRevenue > 0 ? ((float) $product['revenue'] / $maxRevenue) * 100 : 0; ?>
                        <li>
                            <div class="rank-head">
                                <span class="rank-name"><span class="rank-number"><?= $index + 1 ?></span><?= e($product['product_name']) ?></span>
                                <strong><?= format_money($product['revenue']) ?></strong>
                            </div>
                            <div class="rank-bar"><span style="width: <?= number_format($width, 1, '.', '') ?>%"></span></div>
                            <small><?= (int) $product['units'] ?> <?= (int) $product['units'] === 1 ? 'unit' : 'units' ?> sold</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Low stock</h3>
                    <p>Products with 5 units or fewer.</p>
                </div>
                <a class="text-link" href="products.php">Manage</a>
            </div>

            <?php if (!$lowStock): ?>
                <div class="empty-state"><p>All products are well stocked.</p></div>
            <?php else: ?>
                <ul class="stock-list">
                    <?php foreach ($lowStock as $product): ?>
                        <li>
                            <span class="stock-dot <?= (int) $product['stock'] === 0 ? 'is-out' : '' ?>"></span>
                            <span class="stock-name"><?= e($product['name']) ?></span>
                            <span class="stock-count <?= (int) $product['stock'] === 0 ? 'is-out' : '' ?>">
                                <?= (int) $product['stock'] === 0 ? 'Out of stock' : (int) $product['stock'] . ' left' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
