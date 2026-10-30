<?php
require __DIR__ . '/functions.php';
$u = login_required('FARMER');
$title = 'Farmer dashboard';

if ($u['status'] !== 'APPROVED') {
    require __DIR__ . '/header.php';
    echo '<section class="container section"><div class="empty"><h1>Account pending approval</h1>'
       . '<p>An administrator must approve your farmer account before you can list produce. You will be able to add listings as soon as you are approved.</p>'
       . '<a class="btn" href="' . e(url('profile.php')) . '">Review my profile</a></div></section>';
    require __DIR__ . '/footer.php';
    exit;
}

$units  = ['kg', 'bag', 'piece', 'litre', 'crate', 'bunch', 'tray'];
$cats   = db()->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
$catIds = array_map('intval', array_column($cats, 'category_id'));
$errors = [];
$blank  = ['id' => 0, 'name' => '', 'category_id' => 0, 'price' => '', 'quantity' => '', 'unit' => 'kg',
           'location' => (string)$u['location'], 'description' => '', 'photo_url' => ''];
$form   = $blank;

/* ---------- POST: delete / save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $act = post('action');

    if ($act === 'delete') {
        $id = (int)post('id');
        $own = db()->prepare('SELECT 1 FROM produce WHERE produce_id=? AND farmer_id=?');
        $own->execute([$id, $u['user_id']]);
        if ($own->fetch()) {
            $used = db()->prepare('SELECT COUNT(*) FROM order_items WHERE produce_id=?');
            $used->execute([$id]);
            if ((int)$used->fetchColumn() > 0) {         // keep order history intact
                db()->prepare("UPDATE produce SET status='SOLD_OUT', quantity=0 WHERE produce_id=? AND farmer_id=?")->execute([$id, $u['user_id']]);
                flash('success', 'This listing has past orders, so it was withdrawn from sale instead of deleted.');
            } else {
                db()->prepare('DELETE FROM produce WHERE produce_id=? AND farmer_id=?')->execute([$id, $u['user_id']]);
                flash('success', 'Listing deleted.');
            }
        } else {
            flash('error', 'Listing not found.');
        }
        go('farmer.php');
    }

    if ($act === 'save') {
        $form = [
            'id' => (int)post('id'), 'name' => post('name'), 'category_id' => (int)post('category_id'),
            'price' => post('price'), 'quantity' => post('quantity'), 'unit' => post('unit'),
            'location' => post('location'), 'description' => post('description'), 'photo_url' => post('photo_url'),
        ];
        $existing = null;
        if ($form['id']) {
            $q = db()->prepare('SELECT * FROM produce WHERE produce_id=? AND farmer_id=?');
            $q->execute([$form['id'], $u['user_id']]);
            $existing = $q->fetch() ?: null;
            if (!$existing) { $errors[] = 'Listing not found.'; }
        }
        if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 150) { $errors[] = 'Produce name must be 2–150 characters.'; }
        if (!in_array($form['category_id'], $catIds, true)) { $errors[] = 'Choose a category.'; }
        if (!is_numeric($form['price']) || (float)$form['price'] <= 0 || (float)$form['price'] > 1000000) { $errors[] = 'Price must be a number greater than 0.'; }
        if (!is_numeric($form['quantity']) || (float)$form['quantity'] < 0 || (float)$form['quantity'] > 1000000) { $errors[] = 'Quantity must be a number of 0 or more.'; }
        if (!$existing && is_numeric($form['quantity']) && (float)$form['quantity'] <= 0) { $errors[] = 'A new listing needs a quantity above 0.'; }
        if (!in_array($form['unit'], $units, true)) { $errors[] = 'Choose a valid unit.'; }
        if (mb_strlen($form['location']) < 2 || mb_strlen($form['location']) > 150) { $errors[] = 'Location must be 2–150 characters.'; }
        if (mb_strlen($form['description']) > 1000) { $errors[] = 'Description must be 1000 characters or fewer.'; }
        if ($form['photo_url'] !== '' && (!filter_var($form['photo_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $form['photo_url']) || mb_strlen($form['photo_url']) > 500)) {
            $errors[] = 'Photo link must be a valid http(s) address.';
        }
        $uploaded = null;
        if (!$errors) {
            try {
                $uploaded = save_upload($_FILES['photo'] ?? []);
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        if (!$errors) {
            $photo = $uploaded ?? ($form['photo_url'] !== '' ? $form['photo_url'] : ($existing['photo_url'] ?? null));
            if ($existing) {
                // Price/stock tweaks keep a live listing live; changes to what buyers see need re-approval.
                $changed = $existing['name'] !== $form['name'] || (int)$existing['category_id'] !== $form['category_id']
                    || (string)$existing['description'] !== $form['description'] || $existing['location'] !== $form['location']
                    || (string)$existing['photo_url'] !== (string)$photo;
                $status = ($existing['status'] === 'APPROVED' && !$changed) ? 'APPROVED' : 'PENDING';
                db()->prepare('UPDATE produce SET category_id=?, name=?, description=?, price=?, quantity=?, unit=?, location=?, photo_url=?, status=? WHERE produce_id=? AND farmer_id=?')
                    ->execute([$form['category_id'], $form['name'], $form['description'] ?: null, $form['price'], $form['quantity'], $form['unit'], $form['location'], $photo, $status, $form['id'], $u['user_id']]);
                flash('success', $status === 'PENDING' ? 'Listing updated and sent for administrator approval.' : 'Listing updated.');
            } else {
                db()->prepare("INSERT INTO produce(farmer_id,category_id,name,description,price,quantity,unit,location,photo_url,status) VALUES(?,?,?,?,?,?,?,?,?,'PENDING')")
                    ->execute([$u['user_id'], $form['category_id'], $form['name'], $form['description'] ?: null, $form['price'], $form['quantity'], $form['unit'], $form['location'], $photo]);
                flash('success', 'Listing submitted. It will appear in the catalogue once an administrator approves it.');
            }
            go('farmer.php');
        }
    }
}

/* ---------- Edit mode (GET ?edit=ID) ---------- */
$editing = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $q = db()->prepare('SELECT * FROM produce WHERE produce_id=? AND farmer_id=?');
    $q->execute([(int)$_GET['edit'], $u['user_id']]);
    if ($row = $q->fetch()) {
        $form = ['id' => (int)$row['produce_id'], 'name' => $row['name'], 'category_id' => (int)$row['category_id'],
                 'price' => $row['price'], 'quantity' => fmt_qty($row['quantity']), 'unit' => $row['unit'],
                 'location' => $row['location'], 'description' => (string)$row['description'],
                 'photo_url' => str_starts_with((string)$row['photo_url'], 'http') ? (string)$row['photo_url'] : ''];
    }
}
$editing = $form['id'] > 0;

/* ---------- Data for the page ---------- */
$st = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='APPROVED'),0) AS live, COALESCE(SUM(status='PENDING'),0) AS pending FROM produce WHERE farmer_id=?");
$st->execute([$u['user_id']]);
$stats = $st->fetch();
$rev = db()->prepare("SELECT COALESCE(SUM(oi.subtotal),0) FROM order_items oi JOIN produce p ON p.produce_id=oi.produce_id JOIN orders o ON o.order_id=oi.order_id WHERE p.farmer_id=? AND o.status<>'CANCELLED'");
$rev->execute([$u['user_id']]);
$revenue = (float)$rev->fetchColumn();

$l = db()->prepare('SELECT p.*, c.category_name FROM produce p JOIN categories c ON c.category_id=p.category_id WHERE p.farmer_id=? ORDER BY p.created_at DESC');
$l->execute([$u['user_id']]);
$items = $l->fetchAll();

$ord = db()->prepare(
    'SELECT o.order_id, o.order_date, o.status, b.full_name AS buyer, oi.produce_name, oi.quantity, oi.unit, oi.subtotal
       FROM order_items oi
       JOIN produce p ON p.produce_id=oi.produce_id
       JOIN orders o ON o.order_id=oi.order_id
       JOIN users b ON b.user_id=o.buyer_id
      WHERE p.farmer_id=? ORDER BY o.order_date DESC LIMIT 15'
);
$ord->execute([$u['user_id']]);
$incoming = $ord->fetchAll();

$currentPhoto = null;
if ($editing) {
    foreach ($items as $it) {
        if ((int)$it['produce_id'] === $form['id']) { $currentPhoto = photo_src($it['photo_url']); }
    }
}
require __DIR__ . '/header.php';
?>
<section class="page-hero slim"><div class="container"><h1>Farmer dashboard</h1><p class="lead">Welcome, <?= e(strtok($u['full_name'], ' ')) ?>. Manage your listings and see incoming orders.</p></div></section>
<section class="section container">
    <div class="stats cards">
        <div class="stat"><strong><?= (int)$stats['total'] ?></strong><span>Total listings</span></div>
        <div class="stat"><strong><?= (int)$stats['live'] ?></strong><span>Live in catalogue</span></div>
        <div class="stat"><strong><?= (int)$stats['pending'] ?></strong><span>Awaiting approval</span></div>
        <div class="stat"><strong><?= e(cash($revenue)) ?></strong><span>Orders value</span></div>
    </div>

    <div class="two-col dash">
        <form class="form-card" method="post" enctype="multipart/form-data" data-validate novalidate id="listing-form">
            <?= field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <h2><?= $editing ? 'Edit listing' : 'Add produce listing' ?></h2>
            <?php foreach ($errors as $x): ?><p class="alert err" role="alert"><?= e($x) ?></p><?php endforeach; ?>
            <div class="field"><label for="name">Produce name</label><input id="name" name="name" required minlength="2" maxlength="150" placeholder="e.g. Sukuma wiki" value="<?= e($form['name']) ?>"></div>
            <div class="two">
                <div class="field"><label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select…</option>
                        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= (int)$form['category_id'] === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="field"><label for="unit">Sold per</label>
                    <select id="unit" name="unit"><?php foreach ($units as $v): ?><option <?= $form['unit'] === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="two">
                <div class="field"><label for="price">Price (KSh)</label><input id="price" name="price" type="number" min="0.01" max="1000000" step="0.01" required inputmode="decimal" value="<?= e($form['price']) ?>"></div>
                <div class="field"><label for="quantity">Quantity available</label><input id="quantity" name="quantity" type="number" min="0" max="1000000" step="0.01" required inputmode="decimal" value="<?= e($form['quantity']) ?>"></div>
            </div>
            <div class="field"><label for="location">Location</label><input id="location" name="location" required minlength="2" maxlength="150" value="<?= e($form['location']) ?>"></div>
            <div class="field"><label for="description">Description <span class="muted">(optional)</span></label><textarea id="description" name="description" rows="3" maxlength="1000"><?= e($form['description']) ?></textarea></div>
            <div class="field">
                <label for="photo">Photo <span class="muted">(JPG, PNG or WebP · max 2 MB)</span></label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#preview">
                <img id="preview" class="preview" alt="Photo preview" <?= $currentPhoto ? 'src="' . e($currentPhoto) . '"' : 'hidden' ?>>
            </div>
            <div class="field"><label for="photo_url">…or paste a photo link <span class="muted">(optional)</span></label><input id="photo_url" name="photo_url" type="url" maxlength="500" placeholder="https://" value="<?= e($form['photo_url']) ?>"></div>
            <p class="muted small">New listings and changes to name, photo, category, location or description are reviewed by an administrator. Price and stock changes go live immediately.</p>
            <button class="btn btn-lg"><?= $editing ? 'Save changes' : 'Submit listing' ?></button>
            <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(url('farmer.php')) ?>">Cancel</a><?php endif; ?>
        </form>

        <div>
            <div class="panel">
                <h2>My listings</h2>
                <?php if (!$items): ?>
                    <p class="muted">You have no listings yet. Use the form to add your first one.</p>
                <?php else: ?>
                    <div class="tablewrap flat"><table class="table">
                        <thead><tr><th>Produce</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $p): ?>
                            <tr>
                                <td data-label="Produce"><strong><?= e($p['name']) ?></strong><br><span class="muted small"><?= e($p['category_name']) ?></span></td>
                                <td data-label="Price"><?= e(cash($p['price'])) ?>/<?= e($p['unit']) ?></td>
                                <td data-label="Stock"><?= e(fmt_qty($p['quantity'])) ?></td>
                                <td data-label="Status"><span class="badge pr-<?= e(strtolower($p['status'])) ?>"><?= e(ucfirst(strtolower(str_replace('_', ' ', $p['status'])))) ?></span></td>
                                <td class="actions">
                                    <a class="btn btn-outline btn-sm" href="<?= e(url('farmer.php?edit=' . (int)$p['produce_id'])) ?>#listing-form">Edit</a>
                                    <form method="post" data-confirm="Remove this listing?">
                                        <?= field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['produce_id'] ?>">
                                        <button class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Recent orders for my produce</h2>
                <?php if (!$incoming): ?>
                    <p class="muted">No orders yet.</p>
                <?php else: ?>
                    <div class="tablewrap flat"><table class="table">
                        <thead><tr><th>Order</th><th>Item</th><th>Buyer</th><th>Value</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($incoming as $o): ?>
                            <tr>
                                <td data-label="Order"><?= e(receipt_no((int)$o['order_id'])) ?><br><span class="muted small"><?= e(fmt_date($o['order_date'])) ?></span></td>
                                <td data-label="Item"><?= e($o['produce_name']) ?> × <?= e(fmt_qty($o['quantity'])) ?> <?= e($o['unit']) ?></td>
                                <td data-label="Buyer"><?= e($o['buyer']) ?></td>
                                <td data-label="Value"><?= e(cash($o['subtotal'])) ?></td>
                                <td data-label="Status"><?= status_badge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
