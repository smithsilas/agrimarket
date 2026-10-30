<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

$user = require_role(['farmer', 'admin']);
$isAdmin = $user['role'] === 'admin';
$page_title = 'Orders';
$active     = 'dashboard';
$pdo = db();

/* ---------- Status update (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oid    = (int) ($_POST['order_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if (!verify_csrf()) {
        set_flash('error', 'Your session expired. Please try again.');
    } elseif (!in_array($status, ORDER_STATUSES, true)) {
        set_flash('error', 'Unknown status.');
    } else {
        // Farmers may only update orders that contain at least one of their items.
        $ok = $isAdmin;
        if (!$ok) {
            $chk = $pdo->prepare('SELECT 1 FROM order_items WHERE order_id = ? AND farmer_id = ? LIMIT 1');
            $chk->execute([$oid, $user['id']]);
            $ok = (bool) $chk->fetchColumn();
        }
        if ($ok) {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $oid]);
            set_flash('success', 'Order #' . $oid . ' marked as ' . $status . '.');
        } else {
            set_flash('error', 'You can only update orders that include your produce.');
        }
    }
    redirect('admin/orders.php' . (!empty($_GET['status']) ? '?status=' . rawurlencode((string) $_GET['status']) : ''));
}

$filter = in_array($_GET['status'] ?? '', ORDER_STATUSES, true) ? $_GET['status'] : '';
$sql = 'SELECT DISTINCT o.*, u.first_name, u.last_name, u.phone FROM orders o JOIN users u ON u.id = o.user_id JOIN order_items oi ON oi.order_id = o.id WHERE 1=1';
$params = [];
if (!$isAdmin) { $sql .= ' AND oi.farmer_id = ?'; $params[] = $user['id']; }
if ($filter)   { $sql .= ' AND o.status = ?';     $params[] = $filter; }
$st = $pdo->prepare($sql . ' ORDER BY o.created_at DESC LIMIT 100');
$st->execute($params);
$orders = $st->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $q   = $pdo->prepare("SELECT oi.* FROM order_items oi WHERE oi.order_id IN ($in)" . ($isAdmin ? '' : ' AND oi.farmer_id = ' . (int) $user['id']));
    $q->execute($ids);
    foreach ($q->fetchAll() as $row) { $itemsByOrder[$row['order_id']][] = $row; }
}

$badge = ['pending' => 'bg-amber-100 text-amber-800', 'processing' => 'bg-sky-100 text-sky-800', 'shipped' => 'bg-indigo-100 text-indigo-800', 'delivered' => 'bg-emerald-100 text-emerald-800', 'cancelled' => 'bg-gray-200 text-gray-600'];
require __DIR__ . '/../includes/header.php';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <a href="<?= e(url('admin/dashboard.php')) ?>" class="text-xs text-gray-500 hover:text-brand-700">&larr; Dashboard</a>
            <h1 class="text-2xl font-bold text-gray-900">Order fulfilment</h1>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <a href="<?= e(url('admin/orders.php')) ?>" class="px-3 py-1.5 rounded-full <?= $filter === '' ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-700' ?>">All</a>
            <?php foreach (ORDER_STATUSES as $s): ?>
                <a href="<?= e(url('admin/orders.php?status=' . $s)) ?>" class="px-3 py-1.5 rounded-full capitalize <?= $filter === $s ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-700' ?>"><?= e($s) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$orders): ?>
        <div class="text-center bg-white border border-dashed border-gray-300 rounded-2xl py-16 text-sm text-gray-500">No orders<?= $filter ? ' with status “' . e($filter) . '”' : ' yet' ?>.</div>
    <?php endif; ?>

    <div class="space-y-4">
    <?php foreach ($orders as $o): $lines = $itemsByOrder[$o['id']] ?? []; $mine = array_sum(array_map(fn($l) => $l['price'] * $l['qty'], $lines)); ?>
        <article class="bg-white border border-gray-200 rounded-xl p-5 grid md:grid-cols-3 gap-4">
            <div class="space-y-1 text-sm">
                <div class="flex items-center gap-2"><span class="font-bold text-gray-900">Order #<?= (int) $o['id'] ?></span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize <?= $badge[$o['status']] ?>"><?= e($o['status']) ?></span></div>
                <div class="text-xs text-gray-500"><?= e(date('j M Y, g:i a', strtotime($o['created_at']))) ?></div>
                <div class="text-gray-700"><?= e($o['delivery_name']) ?> • <?= e($o['phone']) ?></div>
                <div class="text-xs text-gray-500"><?= e($o['address']) ?>, <?= e($o['county']) ?></div>
                <?php if ($o['notes']): ?><div class="text-xs italic text-gray-500">“<?= e($o['notes']) ?>”</div><?php endif; ?>
                <div class="text-xs"><span class="font-semibold <?= $o['payment_status'] === 'paid' ? 'text-emerald-700' : ($o['payment_status'] === 'failed' ? 'text-red-600' : 'text-amber-700') ?>">Payment: <?= e($o['payment_status']) ?></span><?= $o['mpesa_receipt'] ? ' • ' . e($o['mpesa_receipt']) : '' ?></div>
            </div>
            <ul class="text-sm space-y-1 md:border-x md:border-gray-100 md:px-4">
                <?php foreach ($lines as $l): ?><li class="flex justify-between gap-3"><span><?= e($l['name']) ?> <span class="text-gray-500">× <?= (int) $l['qty'] ?></span></span><span class="font-semibold"><?= e(format_currency($l['price'] * $l['qty'])) ?></span></li><?php endforeach; ?>
                <li class="flex justify-between border-t border-gray-100 pt-1 font-bold"><span><?= $isAdmin ? 'Items total' : 'Your items' ?></span><span class="text-brand-700"><?= e(format_currency($mine)) ?></span></li>
            </ul>
            <form method="post" action="<?= e(url('admin/orders.php' . ($filter ? '?status=' . $filter : ''))) ?>" class="flex md:flex-col gap-2 md:items-stretch items-end justify-end">
                <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                <label class="label sr-only md:not-sr-only" for="st<?= (int) $o['id'] ?>">Update status</label>
                <select class="input" id="st<?= (int) $o['id'] ?>" name="status">
                    <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary !py-2 text-sm">Save</button>
            </form>
        </article>
    <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
