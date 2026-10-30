<?php
require __DIR__ . '/functions.php';
$u  = login_required('BUYER', 'ADMIN');
$id = (int)($_GET['id'] ?? 0);

// Buyers may cancel their own order only while it is still "Placed".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'cancel' && $u['role'] === 'BUYER') {
    check_token();
    if (cancel_order($id, ['PLACED'], (int)$u['user_id'])) {
        flash('success', 'Your order was cancelled and the stock was returned.');
    } else {
        flash('error', 'This order can no longer be cancelled.');
    }
    go('receipt.php?id=' . $id);
}

$sql  = 'SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON u.user_id=o.buyer_id WHERE o.order_id=?';
$args = [$id];
if ($u['role'] !== 'ADMIN') {                       // buyers can only open their own receipts
    $sql .= ' AND o.buyer_id=?';
    $args[] = $u['user_id'];
}
$s = db()->prepare($sql);
$s->execute($args);
$o = $s->fetch();
if (!$o) {
    abort_page(404, 'Order not found', 'We could not find that order on your account.');
}
$s = db()->prepare('SELECT * FROM order_items WHERE order_id=?');
$s->execute([$id]);
$items = $s->fetchAll();

$title = 'Receipt ' . receipt_no($id);
require __DIR__ . '/header.php';
?>
<section class="section container narrow">
    <div class="no-print receipt-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url($u['role'] === 'ADMIN' ? 'admin.php' : 'orders.php')) ?>">← Back</a>
        <div>
            <?php if ($u['role'] === 'BUYER' && $o['status'] === 'PLACED'): ?>
                <form method="post" data-confirm="Cancel this order?" class="inline">
                    <?= field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-danger btn-sm">Cancel order</button>
                </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm" data-print>🖨 Print / Save as PDF</button>
        </div>
    </div>

    <article class="receipt panel">
        <header class="receipt-head">
            <div><div class="brand dark"><img src="<?= e(url('assets/logo.svg')) ?>" alt="" width="34" height="34"><span>Agri<b>Market</b></span></div><p class="muted small">Order summary / receipt</p></div>
            <div class="right"><h1><?= e(receipt_no($id)) ?></h1><p class="muted small"><?= e(fmt_date($o['order_date'])) ?></p><?= status_badge($o['status']) ?></div>
        </header>

        <div class="receipt-meta">
            <div><h4>Billed to</h4><p><strong><?= e($o['full_name']) ?></strong><br><?= e($o['email']) ?><br><?= e($o['contact_phone']) ?></p></div>
            <div><h4>Deliver to</h4><p><?= nl2br(e($o['delivery_address'])) ?></p></div>
            <?php if ($o['notes']): ?><div><h4>Notes</h4><p><?= nl2br(e($o['notes'])) ?></p></div><?php endif; ?>
        </div>

        <div class="no-print"><?= timeline($o['status']) ?></div>

        <div class="tablewrap flat"><table class="table">
            <thead><tr><th>Produce</th><th>Quantity</th><th>Unit price</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td data-label="Produce"><?= e($i['produce_name']) ?></td>
                    <td data-label="Quantity"><?= e(fmt_qty($i['quantity'])) ?> <?= e($i['unit']) ?></td>
                    <td data-label="Unit price"><?= e(cash($i['unit_price'])) ?></td>
                    <td data-label="Subtotal" class="num"><?= e(cash($i['subtotal'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong><?= e(cash($o['total_amount'])) ?></strong></td></tr></tfoot>
        </table></div>

        <p class="notice">This document confirms that an order was recorded. It is not proof of payment — arrange payment and delivery with the farmer.</p>
    </article>
</section>
<?php require __DIR__ . '/footer.php'; ?>
