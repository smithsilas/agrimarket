<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

$user = require_role(['farmer', 'admin']);
$page_title = 'Add produce';
$active     = 'dashboard';

$categories = fetch_categories();
$errors = [];
$old = ['name' => '', 'category_id' => '', 'price' => '', 'unit' => 'kg', 'stock' => '', 'county' => $user['county'] ?? '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) { $old[$k] = trim((string) ($_POST[$k] ?? '')); }

    if (!verify_csrf())                                                           { $errors['form'] = 'Your session expired. Please try again.'; }
    if ($old['name'] === '' || mb_strlen($old['name']) > 120)                      { $errors['name'] = 'Enter a produce name (max 120 characters).'; }
    if (!in_array((int) $old['category_id'], array_map('intval', array_column($categories, 'id')), true)) { $errors['category_id'] = 'Choose a category.'; }
    if (!is_numeric($old['price']) || (float) $old['price'] <= 0 || (float) $old['price'] > 9999999) { $errors['price'] = 'Enter a price greater than 0.'; }
    if ($old['unit'] === '' || mb_strlen($old['unit']) > 40)                       { $errors['unit'] = 'Enter the selling unit, e.g. kg, bunch, 50kg bag.'; }
    if (!ctype_digit($old['stock']))                                               { $errors['stock'] = 'Enter the quantity available (whole number).'; }
    if ($old['county'] === '' || mb_strlen($old['county']) > 80)                   { $errors['county'] = 'Enter the county the produce comes from.'; }

    // Optional photo upload
    $imagePath = null;
    $file = $_FILES['image'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_UPLOAD_BYTES) {
            $errors['image'] = 'Upload failed – photos must be under ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
            if (!$ext || !@getimagesize($file['tmp_name'])) {
                $errors['image'] = 'Use a JPG, PNG or WebP photo.';
            } elseif (!$errors) {
                if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0755, true); }
                $fname = bin2hex(random_bytes(10)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fname)) { $imagePath = 'uploads/' . $fname; }
                else { $errors['image'] = 'Could not save the photo. Check folder permissions on assets/images/uploads/.'; }
            }
        }
    }

    if (!$errors) {
        // Fallback illustration by category if no photo supplied
        db()->prepare('INSERT INTO products (farmer_id, category_id, name, description, price, unit, stock, county, image) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$user['id'], (int) $old['category_id'], $old['name'], $old['description'], round((float) $old['price'], 2), $old['unit'], (int) $old['stock'], $old['county'], $imagePath]);
        set_flash('success', '“' . $old['name'] . '” is now live in the catalogue.');
        redirect('admin/dashboard.php');
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<p class="text-xs text-red-600 mt-1">' . e($errors[$k]) . '</p>' : '';
require __DIR__ . '/../includes/header.php';
?>
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <a href="<?= e(url('admin/dashboard.php')) ?>" class="text-xs text-gray-500 hover:text-brand-700">&larr; Back to dashboard</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-2 mb-6">Add new produce</h1>
    <?php if (!empty($errors['form'])): ?><div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3"><?= e($errors['form']) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="bg-white border border-gray-200 rounded-2xl p-6 md:p-8 space-y-5" novalidate>
        <?= csrf_field() ?>
        <div><label class="label" for="name">Produce name</label><input class="input" id="name" name="name" value="<?= e($old['name']) ?>" placeholder="e.g. Sweet Potatoes" required><?= $err('name') ?></div>
        <div class="grid sm:grid-cols-2 gap-5">
            <div><label class="label" for="category_id">Category</label>
                <select class="input" id="category_id" name="category_id" required><option value="">Choose…</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $old['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select><?= $err('category_id') ?></div>
            <div><label class="label" for="county">Origin county</label><input class="input" id="county" name="county" value="<?= e($old['county']) ?>" placeholder="Nyeri"><?= $err('county') ?></div>
        </div>
        <div class="grid sm:grid-cols-3 gap-5">
            <div><label class="label" for="price">Price (<?= e(CURRENCY) ?>)</label><input class="input" id="price" name="price" type="number" step="0.01" min="0" value="<?= e($old['price']) ?>" required><?= $err('price') ?></div>
            <div><label class="label" for="unit">Sold per</label><input class="input" id="unit" name="unit" value="<?= e($old['unit']) ?>" placeholder="kg, bunch, 50kg bag"><?= $err('unit') ?></div>
            <div><label class="label" for="stock">Quantity available</label><input class="input" id="stock" name="stock" type="number" min="0" value="<?= e($old['stock']) ?>" required><?= $err('stock') ?></div>
        </div>
        <div><label class="label" for="description">Description</label><textarea class="input" id="description" name="description" rows="4" placeholder="How it was grown, harvest date, taste, best uses…"><?= e($old['description']) ?></textarea></div>
        <div>
            <label class="label" for="image">Photo <span class="text-gray-400 font-normal">(JPG, PNG or WebP, up to 3 MB – optional)</span></label>
            <input class="input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold file:px-3 file:py-1" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-image-input><?= $err('image') ?>
            <img data-image-preview class="hidden mt-3 h-32 rounded-lg object-cover border border-gray-200" alt="Preview">
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="<?= e(url('admin/dashboard.php')) ?>" class="btn btn-secondary !py-2 text-sm">Cancel</a>
            <button class="btn btn-primary !py-2 text-sm">Publish listing</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
