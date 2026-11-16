<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('customer');

$pdo  = db();
$user = current_user();

$q      = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['Pending', 'Completed', 'Cancelled'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$where  = ['user_id = :user_id'];
$params = [':user_id' => $user['id']];

if ($q !== '') {
    if (preg_match('/^(?:ORD-?)?0*([1-9]\d*)$/i', $q, $matches)) {
        $params[':qid'] = (int) $matches[1];
    }

    $where[] = '(customer_name LIKE :q1 OR product_name LIKE :q2 OR CAST(id AS CHAR) LIKE :q3'
        . (isset($params[':qid']) ? ' OR id = :qid' : '') . ')';

    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}

if ($status !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$summaryStatement = $pdo->prepare(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total_amount ELSE 0 END), 0) AS total_spent,
            COALESCE(SUM(status = 'Pending'), 0) AS pending_orders
     FROM orders" . $whereSql
);
$summaryStatement->execute($params);
$summary = $summaryStatement->fetch();

$listStatement = $pdo->prepare(
    'SELECT id, product_name, quantity, unit_price, total_amount, payment_method, status, created_at
     FROM orders' . $whereSql . '
     ORDER BY created_at DESC, id DESC
     LIMIT 200'
);
$listStatement->execute($params);
$orders = $listStatement->fetchAll();

$isFiltered = $q !== '' || $status !== '';

$pageTitle    = 'My Orders';
$pageSubtitle = 'Track every order you have placed with the store.';
$activePage   = 'my_orders';

require __DIR__ . '/../includes/header.php';
?>

<section class="stats-grid stats-grid-3">
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>My orders</span>
            <strong><?= number_format((int) $summary['total_orders']) ?></strong>
            <small><?= $isFiltered ? 'Based on current filters' : 'Orders you have placed' ?></small>
        </div>
    </article>
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>Total spent</span>
            <strong><?= format_money($summary['total_spent']) ?></strong>
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
            <h3>Order history</h3>
            <p>Showing <?= count($orders) ?> of <?= (int) $summary['total_orders'] ?> matching orders.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="order.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Place another order
        </a>
    </div>

    <div class="toolbar toolbar-inside">
        <form class="filter-form" method="get" action="my_orders.php" role="search">
            <div class="search-form">
                <span class="search-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                </span>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by order ID or product…" aria-label="Search my orders">
            </div>
            <select name="status" class="select" aria-label="Filter by status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Apply filters</button>
            <?php if ($isFiltered): ?>
                <a class="text-link" href="my_orders.php">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$orders): ?>
        <div class="empty-state empty-state-lg">
            <span class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
            </span>
            <h4><?= $isFiltered ? 'No matching orders' : 'No orders yet' ?></h4>
            <p><?= $isFiltered ? 'Try a different search term or status filter.' : 'Your orders will appear here as soon as you place one.' ?></p>
            <a class="btn btn-primary btn-sm" href="<?= $isFiltered ? 'my_orders.php' : 'order.php' ?>">
                <?= $isFiltered ? 'Clear filters' : 'Place your first order' ?>
            </a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th class="num">Qty</th>
                        <th class="num">Unit Price</th>
                        <th class="num">Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="Order ID"><strong><?= e(order_code($order['id'])) ?></strong></td>
                            <td data-label="Product"><?= e($order['product_name']) ?></td>
                            <td data-label="Qty" class="num"><?= (int) $order['quantity'] ?></td>
                            <td data-label="Unit Price" class="num"><?= format_money($order['unit_price']) ?></td>
                            <td data-label="Total" class="num"><strong><?= format_money($order['total_amount']) ?></strong></td>
                            <td data-label="Payment"><span class="badge <?= $order['payment_method'] === 'GCash' ? 'badge-info' : 'badge-muted' ?>"><?= e($order['payment_method']) ?></span></td>
                            <td data-label="Status"><span class="badge <?= status_class($order['status']) ?>"><?= e($order['status']) ?></span></td>
                            <td data-label="Date"><span class="date-text"><?= e(format_date($order['created_at'], 'M j, Y')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-note">Need to change or cancel an order? Contact the store and quote the order ID.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
