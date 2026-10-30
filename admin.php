<?php
require __DIR__ . '/functions.php';
$admin = login_required('ADMIN');
$title = 'Administrator dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $act = post('action');
    $id  = (int)post('id');
    $msg = 'Done.';

    switch ($act) {
        case 'approve_farmer':
            db()->prepare("UPDATE users SET status='APPROVED' WHERE user_id=? AND role='FARMER'")->execute([$id]);
            $msg = 'Farmer account approved.';
            break;
        case 'reject_farmer':
            db()->prepare("UPDATE users SET status='SUSPENDED' WHERE user_id=? AND role='FARMER' AND status='PENDING'")->execute([$id]);
            $msg = 'Farmer application rejected.';
            break;
        case 'approve_product':
            db()->prepare("UPDATE produce SET status='APPROVED' WHERE produce_id=? AND status='PENDING'")->execute([$id]);
            $msg = 'Listing approved and now visible in the catalogue.';
            break;
        case 'reject_product':
            db()->prepare("UPDATE produce SET status='REJECTED' WHERE produce_id=? AND status='PENDING'")->execute([$id]);
            $msg = 'Listing rejected.';
            break;
        case 'advance_order':
            $new = advance_order($id);
            $msg = $new ? 'Order moved to ' . ucfirst(strtolower($new)) . '.' : 'That order cannot move forward.';
            break;
        case 'cancel_order':
            $msg = cancel_order($id, ['PLACED', 'CONFIRMED']) ? 'Order cancelled and stock returned.' : 'Only Placed or Confirmed orders can be cancelled.';
            break;
        case 'suspend_user':
            db()->prepare("UPDATE users SET status='SUSPENDED' WHERE user_id=? AND role<>'ADMIN'")->execute([$id]);
            $msg = 'User suspended.';
            break;
        case 'reactivate_user':
            db()->prepare("UPDATE users SET status='APPROVED' WHERE user_id=? AND role<>'ADMIN'")->execute([$id]);
            $msg = 'User reactivated.';
            break;
        case 'mark_read':
            db()->prepare('UPDATE contact_messages SET is_read=1 WHERE message_id=?')->execute([$id]);
            $msg = 'Message marked as read.';
            break;
        default:
            $msg = 'Unknown action.';
    }
    flash('success', $msg);
    go('admin.php');
}

$pdo = db();
$stats = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM users WHERE role='BUYER') AS buyers,
            (SELECT COUNT(*) FROM users WHERE role='FARMER' AND status='APPROVED') AS farmers,
            (SELECT COUNT(*) FROM users WHERE role='FARMER' AND status='PENDING') AS pending_farmers,
            (SELECT COUNT(*) FROM produce WHERE status='PENDING') AS pending_products,
            (SELECT COUNT(*) FROM orders) AS orders,
            (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status<>'CANCELLED') AS revenue,
            (SELECT COUNT(*) FROM contact_messages WHERE is_read=0) AS unread"
)->fetch();

$farmers  = $pdo->query("SELECT user_id, full_name, email, phone, location, created_at FROM users WHERE role='FARMER' AND status='PENDING' ORDER BY created_at")->fetchAll();
$products = $pdo->query("SELECT p.*, u.full_name AS farmer, c.category_name FROM produce p JOIN users u ON u.user_id=p.farmer_id JOIN categories c ON c.category_id=p.category_id WHERE p.status='PENDING' ORDER BY p.created_at")->fetchAll();
$orders   = $pdo->query("SELECT o.*, u.full_name AS buyer FROM orders o JOIN users u ON u.user_id=o.buyer_id ORDER BY o.order_date DESC LIMIT 40")->fetchAll();
$users    = $pdo->query("SELECT user_id, full_name, email, role, status, created_at FROM users WHERE role<>'ADMIN' ORDER BY created_at DESC LIMIT 40")->fetchAll();
$messages = $pdo->query('SELECT * FROM contact_messages ORDER BY is_read, created_at DESC LIMIT 15')->fetchAll();

/** Renders a tiny POST button. */
function act_btn(string $action, int $id, string $label, string $class = 'btn-sm', string $confirm = ''): void
{
    echo '<form method="post"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn ' . e($class) . '">' . e($label) . '</button></form>';
}

require __DIR__ . '/header.php';
?>
<section class="page-hero slim"><div class="container"><h1>Administrator dashboard</h1><p class="lead">Approve farmers and listings, move orders through their lifecycle, and manage users.</p></div></section>
<section class="section container">
    <div class="stats cards">
        <div class="stat"><strong><?= (int)$stats['farmers'] ?></strong><span>Approved farmers</span></div>
        <div class="stat"><strong><?= (int)$stats['buyers'] ?></strong><span>Buyers</span></div>
        <div class="stat <?= $stats['pending_farmers'] + $stats['pending_products'] ? 'alert-stat' : '' ?>"><strong><?= (int)$stats['pending_farmers'] + (int)$stats['pending_products'] ?></strong><span>Awaiting approval</span></div>
        <div class="stat"><strong><?= (int)$stats['orders'] ?></strong><span>Orders</span></div>
        <div class="stat"><strong><?= e(cash($stats['revenue'])) ?></strong><span>Order value (excl. cancelled)</span></div>
    </div>

    <nav class="dash-nav" aria-label="Dashboard sections">
        <a href="#farmers">Farmers (<?= (int)$stats['pending_farmers'] ?>)</a>
        <a href="#listings">Listings (<?= (int)$stats['pending_products'] ?>)</a>
        <a href="#orders">Orders</a>
        <a href="#users">Users</a>
        <a href="#messages">Messages (<?= (int)$stats['unread'] ?>)</a>
    </nav>

    <div class="panel" id="farmers">
        <h2>Pending farmer accounts</h2>
        <?php if (!$farmers): ?><p class="muted">No farmer applications waiting.</p><?php else: ?>
        <div class="tablewrap flat"><table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Location</th><th>Applied</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($farmers as $f): ?>
                <tr>
                    <td data-label="Name"><?= e($f['full_name']) ?></td><td data-label="Email"><?= e($f['email']) ?></td>
                    <td data-label="Phone"><?= e($f['phone']) ?></td><td data-label="Location"><?= e($f['location']) ?></td>
                    <td data-label="Applied"><?= e(fmt_date($f['created_at'])) ?></td>
                    <td class="actions"><?php act_btn('approve_farmer', (int)$f['user_id'], 'Approve'); act_btn('reject_farmer', (int)$f['user_id'], 'Reject', 'btn-danger btn-sm', 'Reject this application?'); ?></td>
                </tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div>

    <div class="panel" id="listings">
        <h2>Pending produce listings</h2>
        <?php if (!$products): ?><p class="muted">No listings waiting for review.</p><?php else: ?>
        <div class="tablewrap flat"><table class="table">
            <thead><tr><th>Produce</th><th>Farmer</th><th>Price</th><th>Stock</th><th>Location</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td data-label="Produce"><strong><?= e($p['name']) ?></strong><br><span class="muted small"><?= e($p['category_name']) ?></span></td>
                    <td data-label="Farmer"><?= e($p['farmer']) ?></td>
                    <td data-label="Price"><?= e(cash($p['price'])) ?>/<?= e($p['unit']) ?></td>
                    <td data-label="Stock"><?= e(fmt_qty($p['quantity'])) ?></td>
                    <td data-label="Location"><?= e($p['location']) ?></td>
                    <td class="actions"><?php act_btn('approve_product', (int)$p['produce_id'], 'Approve'); act_btn('reject_product', (int)$p['produce_id'], 'Reject', 'btn-danger btn-sm', 'Reject this listing?'); ?></td>
                </tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div>

    <div class="panel" id="orders">
        <h2>Recent orders</h2>
        <?php if (!$orders): ?><p class="muted">No orders yet.</p><?php else: ?>
        <div class="tablewrap flat"><table class="table">
            <thead><tr><th>Order</th><th>Buyer</th><th>Date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($orders as $o): $next = next_status($o['status']); ?>
                <tr>
                    <td data-label="Order"><a href="<?= e(url('receipt.php?id=' . (int)$o['order_id'])) ?>"><?= e(receipt_no((int)$o['order_id'])) ?></a></td>
                    <td data-label="Buyer"><?= e($o['buyer']) ?></td>
                    <td data-label="Date"><?= e(fmt_date($o['order_date'])) ?></td>
                    <td data-label="Total"><?= e(cash($o['total_amount'])) ?></td>
                    <td data-label="Status"><?= status_badge($o['status']) ?></td>
                    <td class="actions">
                        <?php if ($next) { act_btn('advance_order', (int)$o['order_id'], 'Mark ' . ucfirst(strtolower($next))); } ?>
                        <?php if (in_array($o['status'], ['PLACED', 'CONFIRMED'], true)) { act_btn('cancel_order', (int)$o['order_id'], 'Cancel', 'btn-danger btn-sm', 'Cancel this order and return the stock?'); } ?>
                        <?php if (!$next && $o['status'] !== 'CANCELLED') { echo '<span class="muted small">Completed</span>'; } ?>
                    </td>
                </tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div>

    <div class="panel" id="users">
        <h2>Users</h2>
        <div class="tablewrap flat"><table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($users as $x): ?>
                <tr>
                    <td data-label="Name"><?= e($x['full_name']) ?></td><td data-label="Email"><?= e($x['email']) ?></td>
                    <td data-label="Role"><?= e(ucfirst(strtolower($x['role']))) ?></td>
                    <td data-label="Status"><span class="badge pr-<?= e(strtolower($x['status'])) ?>"><?= e(ucfirst(strtolower($x['status']))) ?></span></td>
                    <td data-label="Joined"><?= e(fmt_date($x['created_at'])) ?></td>
                    <td class="actions"><?php
                        if ($x['status'] === 'SUSPENDED') { act_btn('reactivate_user', (int)$x['user_id'], 'Reactivate', 'btn-outline btn-sm'); }
                        else { act_btn('suspend_user', (int)$x['user_id'], 'Suspend', 'btn-danger btn-sm', 'Suspend this user?'); }
                    ?></td>
                </tr>
            <?php endforeach; ?></tbody></table></div>
    </div>

    <div class="panel" id="messages">
        <h2>Contact messages</h2>
        <?php if (!$messages): ?><p class="muted">No messages yet.</p><?php else: ?>
            <?php foreach ($messages as $m): ?>
                <article class="msg <?= $m['is_read'] ? '' : 'unread' ?>">
                    <header><strong><?= e($m['subject']) ?></strong><span class="muted small"><?= e($m['name']) ?> &lt;<?= e($m['email']) ?>&gt; · <?= e(fmt_date($m['created_at'])) ?></span></header>
                    <p><?= nl2br(e($m['message'])) ?></p>
                    <?php if (!$m['is_read']) { act_btn('mark_read', (int)$m['message_id'], 'Mark as read', 'btn-outline btn-sm'); } ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
