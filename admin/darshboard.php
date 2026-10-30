<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

$user = require_role(['farmer', 'admin']);
$isAdmin = $user['role'] === 'admin';
$page_title = 'Dashboard';
$active     = 'dashboard';
$pdo = db();

/* ---------- Product actions (POST): hide/show, delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Your session expired. Please try again.');
    } else {
        $pid = (int) ($_POST['product_id'] ?? 0);
        $own = $isAdmin ? '' : ' AND farmer_id = ' . (int) $user['id'];   // farmers can only touch their own listings
        switch ($_POST['do'] ?? '') {
            case 'toggle':
                $pdo->prepare("UPDATE products SET status = IF(status = 'active', 'hidden', 'active') WHERE id = ?$own")->execute([$pid]);
                set_flash('success', 'Listing visibility updated.');
                break;
            case 'delete':
                $st = $pdo->prepare("SELECT image FROM products WHERE id = ?$own");
                $st->execute([$pid]);
                if ($img = $st->fetchColumn()) {
                    $pdo->prepare("DELETE FROM products WHERE id = ?$own")->execute([$pid]);
                    if (strpos((string) $img, 'uploads/') === 0 && is_file(BASE_PATH . '/assets/images/' . $img)) { @unlink(BASE_PATH . '/assets/images/' . $img); }
                    set_flash('success', 'Listing deleted.');
                }
                break;
            case 'stock':
                $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?$own")->execute([max(0, (int) ($_POST['stock'] ?? 0)), $pid]);
                set_flash('success', 'Stock updated.');
                break;
        }
    }
    redirect('admin/dashboard.php');
}

$scope  = $isAdmin ? '' : 'WHERE p.farmer_id = ' . (int) $user['id'];
$prods  = $pdo->query("SELECT p.*, c.name AS category_name, u.farm_name FROM products p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.farmer_id $scope ORDER BY p.created_at DESC, p.id DESC")->fetchAll();

$oiScope = $isAdmin ? '' : 'AND oi.farmer_id = ' . (int) $user['id'];
$stats = $pdo->query("SELECT COUNT(DISTINCT o.id) AS orders,
                             COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN oi.price * oi.qty END), 0) AS revenue,
                             COUNT(DISTINCT CASE WHEN o.status IN ('pending','processing') AND o.payment_status = 'paid' THEN o.id END) AS to_fulfil
                      FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE 1=1 $oiScope")->fetch();
$low = count(array_filter($prods, fn($p) => (int) $p['stock'] <= 20));

require __DIR__ . '/../includes/header.php';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= $isAdmin ? 'Marketplace dashboard' : e($user['farm_name'] ?: 'Your farm') . ' dashboard' ?></h1>
            <p class="text-sm text-gray-500">Manage your produce listings and see what needs delivering.</p>
        </div>
        <div class="flex gap-3">
            <a href="<?= e(url('admin/orders.php')) ?>" class="btn btn-secondary !py-2 text-sm">View orders</a>
            <a href="<?= e(url('admin/add_product.php')) ?>" class="btn btn-primary !py-2 text-sm"><i class="fa-solid fa-plus"></i> Add produce</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ([
            ['Listings', count($prods), 'fa-basket-shopping', 'text-brand-600 bg-brand-50'],
            ['Orders', (int) $stats['orders'], 'fa-receipt', 'text-sky-600 bg-sky-50'],
            ['To fulfil', (int) $stats['to_fulfil'], 'fa-truck-fast', 'text-amber-600 bg-amber-50'],
            ['Paid revenue', format_currency($stats['revenue']), 'fa-sack-dollar', 'text-emerald-600 bg-emerald-50'],
        ] as [$label, $val, $icon, $cls]): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4">
                <div class="w-11 h-11 rounded-full flex items-center justify-center <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div><div class="text-xl font-extrabold text-gray-900"><?= e((string) $val) ?></div><div class="text-xs text-gray-500"><?= e($label) ?></div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($low): ?><p class="text-sm bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3"><i class="fa-solid fa-triangle-exclamation mr-1"></i><?= $low ?> listing<?= $low > 1 ? 's are' : ' is' ?> running low on stock (20 or fewer).</p><?php endif; ?>

    <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 text-left"><tr>
                <th class="px-4 py-3">Produce</th><?php if ($isAdmin): ?><th class="px-4 py-3">Farm</th><?php endif; ?><th class="px-4 py-3">Price</th><th class="px-4 py-3">Stock</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($prods as $p): ?>
                <tr>
                    <td class="px-4 py-3"><div class="flex items-center gap-3"><img src="<?= e(product_image_url($p['image'])) ?>" alt="" class="w-12 h-12 rounded-lg object-cover"><div><a href="<?= e(url('product.php?id=' . (int) $p['id'])) ?>" class="font-semibold text-gray-900 hover:text-brand-600"><?= e($p['name']) ?></a><div class="text-xs text-gray-500"><?= e($p['category_name']) ?> • <?= e($p['county']) ?></div></div></div></td>
                    <?php if ($isAdmin): ?><td class="px-4 py-3 text-gray-600"><?= e($p['farm_name']) ?></td><?php endif; ?>
                    <td class="px-4 py-3 font-semibold"><?= e(format_currency($p['price'])) ?> <span class="text-xs font-normal text-gray-500">/ <?= e($p['unit']) ?></span></td>
                    <td class="px-4 py-3">
                        <form method="post" class="flex items-center gap-1"><?= csrf_field() ?><input type="hidden" name="do" value="stock"><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <input type="number" min="0" name="stock" value="<?= (int) $p['stock'] ?>" class="w-20 border border-gray-300 rounded-md px-2 py-1 text-sm <?= $p['stock'] <= 20 ? 'border-amber-400' : '' ?>" aria-label="Stock for <?= e($p['name']) ?>">
                            <button class="text-xs text-brand-700 font-semibold hover:underline">Save</button></form>
                    </td>
                    <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $p['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' ?>"><?= $p['status'] === 'active' ? 'Live' : 'Hidden' ?></span></td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-3 text-xs">
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><button class="text-gray-600 hover:text-brand-700 font-semibold"><?= $p['status'] === 'active' ? 'Hide' : 'Show' ?></button></form>
                            <form method="post" data-confirm="Delete “<?= e($p['name']) ?>”? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><button class="text-red-600 hover:text-red-800 font-semibold">Delete</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; if (!$prods): ?>
                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">No listings yet. <a class="text-brand-700 font-semibold hover:underline" href="<?= e(url('admin/add_product.php')) ?>">Add your first produce</a>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
