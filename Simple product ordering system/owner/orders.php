<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('owner');

$pdo = db();

$q      = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['Pending', 'Completed', 'Cancelled'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$where  = [];
$params = [];

$orderIdSearch = null;

if ($q !== '') {
    
    if (preg_match('/^(?:ORD-?)?0*([1-9]\d*)$/i', $q, $matches)) {
        $orderIdSearch = (int) $matches[1];
    }

    $where[] = '(customer_name LIKE :q1 OR product_name LIKE :q2 OR CAST(id AS CHAR) LIKE :q3 OR customer_email LIKE :q4 OR customer_phone LIKE :q5'
        . ($orderIdSearch !== null ? ' OR id = :qid' : '') . ')';

    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
    $params[':q5'] = $like;

    if ($orderIdSearch !== null) {
        $params[':qid'] = $orderIdSearch;
    }
}

if ($status !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$summaryStatement = $pdo->prepare(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total_amount ELSE 0 END), 0) AS total_value,
            COALESCE(SUM(status = 'Pending'), 0) AS pending_orders
     FROM orders" . $whereSql
);
$summaryStatement->execute($params);
$summary = $summaryStatement->fetch();

$listStatement = $pdo->prepare(
    'SELECT id, customer_name, customer_email, customer_phone, customer_address, payment_method,
            product_name, quantity, unit_price, total_amount, status, created_at
     FROM orders' . $whereSql . '
     ORDER BY created_at DESC, id DESC
     LIMIT 200'
);
$listStatement->execute($params);
$orders = $listStatement->fetchAll();

$isFiltered = $q !== '' || $status !== '';

$pageTitle    = 'Customer Orders';
$pageSubtitle = 'Search, review and manage every order placed in your store.';
$activePage   = 'orders';

require __DIR__ . '/../includes/header.php';
?>

<section class="stats-grid stats-grid-3">
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>Matching orders</span>
            <strong><?= number_format((int) $summary['total_orders']) ?></strong>
            <small><?= $isFiltered ? 'Based on current filters' : 'All orders on record' ?></small>
        </div>
    </article>
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>Order value</span>
            <strong><?= format_money($summary['total_value']) ?></strong>
            <small>Excluding cancelled orders</small>
        </div>
    </article>
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>Pending</span>
            <strong><?= number_format((int) $summary['pending_orders']) ?></strong>
            <small>Awaiting completion</small>
        </div>
    </article>
</section>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3>Order list</h3>
            <p>Showing <?= count($orders) ?> of <?= (int) $summary['total_orders'] ?> matching orders.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="products.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
            Manage products
        </a>
    </div>

    <div class="toolbar toolbar-inside">
        <form class="filter-form" method="get" action="orders.php" role="search">
            <div class="search-form">
                <span class="search-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                </span>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by order ID, customer, email, phone or product…" aria-label="Search orders">
            </div>
            <select name="status" class="select" aria-label="Filter by status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Apply filters</button>
            <?php if ($isFiltered): ?>
                <a class="text-link" href="orders.php">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$orders): ?>
        <div class="empty-state empty-state-lg">
            <span class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
            </span>
            <h4><?= $isFiltered ? 'No matching orders' : 'No orders yet' ?></h4>
            <p><?= $isFiltered ? 'Try a different search term or status filter.' : 'Customer orders will appear here as soon as they are submitted.' ?></p>
            <a class="btn btn-primary btn-sm" href="<?= $isFiltered ? 'orders.php' : 'products.php' ?>">
                <?= $isFiltered ? 'Clear filters' : 'Manage products' ?>
            </a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-orders">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th class="num">Qty</th>
                        <th class="num">Unit Price</th>
                        <th class="num">Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="Order ID"><a class="order-link" href="edit_order.php?id=<?= (int) $order['id'] ?>"><?= e(order_code($order['id'])) ?></a></td>
                            <td data-label="Customer">
                                <span class="customer-cell">
                                    <strong class="cell-strong"><?= e($order['customer_name']) ?></strong>
                                    <?php if ($order['customer_email'] !== ''): ?>
                                        <span class="contact-line"><?= e($order['customer_email']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($order['customer_phone'] !== ''): ?>
                                        <span class="contact-line"><?= e($order['customer_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($order['customer_address'] !== ''): ?>
                                        <span class="contact-line contact-address"><?= e($order['customer_address']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Product"><?= e($order['product_name']) ?></td>
                            <td data-label="Qty" class="num"><?= (int) $order['quantity'] ?></td>
                            <td data-label="Unit Price" class="num"><?= format_money($order['unit_price']) ?></td>
                            <td data-label="Total" class="num"><strong><?= format_money($order['total_amount']) ?></strong></td>
                            <td data-label="Payment"><span class="badge <?= $order['payment_method'] === 'GCash' ? 'badge-info' : 'badge-muted' ?>"><?= e($order['payment_method']) ?></span></td>
                            <td data-label="Status"><span class="badge <?= status_class($order['status']) ?>"><?= e($order['status']) ?></span></td>
                            <td data-label="Date"><span class="date-text"><?= e(format_date($order['created_at'], 'M j, Y')) ?></span></td>
                            <td data-label="Actions" class="actions-col">
                                <div class="row-actions">
                                    <a class="icon-btn" href="edit_order.php?id=<?= (int) $order['id'] ?>" title="Edit order" aria-label="Edit order <?= e(order_code($order['id'])) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                    </a>
                                    <form method="post" action="delete_order.php" data-confirm="Delete <?= e(order_code($order['id'])) ?> for <?= e($order['customer_name']) ?>? Stock will be returned to inventory.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                        <button class="icon-btn icon-btn-danger" type="submit" title="Delete order" aria-label="Delete order <?= e(order_code($order['id'])) ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ((int) $summary['total_orders'] > 200): ?>
            <p class="table-note">Only the 200 most recent matching orders are shown. Use search or filters to narrow the list.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
