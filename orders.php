<?php
require __DIR__ . '/functions.php';
$u = login_required('BUYER');
$s = db()->prepare(
    'SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id=o.order_id) AS lines
       FROM orders o WHERE o.buyer_id=? ORDER BY o.order_date DESC'
);
$s->execute([$u['user_id']]);
$rows = $s->fetchAll();
$title = 'My orders';
require __DIR__ . '/header.php';
?>
<section class="page-hero slim"><div class="container"><h1>My orders</h1><p class="lead">Track every order from placed to delivered.</p></div></section>
<section class="section container">
<?php if (!$rows): ?>
    <div class="empty"><h3>No orders yet</h3><p>When you place an order it will show up here.</p><a class="btn" href="<?= e(url('catalogue.php')) ?>">Browse produce</a></div>
<?php else: ?>
    <div class="tablewrap"><table class="table">
        <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $o): ?>
            <tr>
                <td data-label="Order"><strong><?= e(receipt_no((int)$o['order_id'])) ?></strong></td>
                <td data-label="Date"><?= e(fmt_date($o['order_date'])) ?></td>
                <td data-label="Items"><?= (int)$o['lines'] ?></td>
                <td data-label="Total"><?= e(cash($o['total_amount'])) ?></td>
                <td data-label="Status"><?= status_badge($o['status']) ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= e(url('receipt.php?id=' . (int)$o['order_id'])) ?>">View receipt</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
