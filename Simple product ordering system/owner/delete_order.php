<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders.php');
}

verify_csrf();

$orderId = parse_positive_int($_POST['id'] ?? null);

if ($orderId === null) {
    set_flash('error', 'Invalid order selected.');
    redirect('orders.php');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare('SELECT id, product_id, quantity, status FROM orders WHERE id = ? FOR UPDATE');
    $statement->execute([$orderId]);
    $order = $statement->fetch();

    if (!$order) {
        $pdo->rollBack();
        set_flash('error', 'That order no longer exists.');
        redirect('orders.php');
    }

    $delete = $pdo->prepare('DELETE FROM orders WHERE id = ?');
    $delete->execute([$orderId]);

    $restored = false;
    if ($order['product_id'] !== null && $order['status'] !== 'Cancelled') {
        $restore = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        $restore->execute([(int) $order['quantity'], (int) $order['product_id']]);
        $restored = true;
    }

    $pdo->commit();

    set_flash(
        'success',
        'Order ' . order_code($orderId) . ' was deleted' . ($restored ? ' and its stock was restored.' : '.')
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order deletion failed: ' . $e->getMessage());
    set_flash('error', 'Unable to delete that order. Please try again.');
}

redirect('orders.php');
