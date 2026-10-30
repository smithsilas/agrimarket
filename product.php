<?php
require_once __DIR__ . '/includes/functions.php';

$product = fetch_product((int) ($_GET['id'] ?? 0));
if (!$product) {
    http_response_code(404);
    set_flash('error', 'That product could not be found.');
    redirect('catalogue.php');
}

$page_title = $product['name'];
$active     = 'catalogue';
$img        = product_image_url($product['image']);
$inStock    = (int) $product['stock'] > 0;

$related = fetch_products(['cat' => [$product['category_slug']], 'per_page' => 5, 'sort' => 'popularity'])['items'];
$related = array_slice(array_filter($related, fn($r) => (int) $r['id'] !== (int) $product['id']), 0, 4);

require __DIR__ . '/includes/header.php';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-12">
    <nav class="text-xs text-gray-500" aria-label="Breadcrumb">
        <a href="<?= e(url('catalogue.php')) ?>" class="hover:text-brand-700">Catalogue</a> /
        <a href="<?= e(url('catalogue.php?cat[]=' . $product['category_slug'])) ?>" class="hover:text-brand-700"><?= e($product['category_name']) ?></a> /
        <span class="text-gray-800"><?= e($product['name']) ?></span>
    </nav>

    <div class="bg-white border border-gray-200 rounded-2xl p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Gallery -->
        <div class="space-y-4" data-gallery>
            <div class="gallery-main bg-gray-100 h-80 md:h-96 rounded-xl border border-gray-200" data-gallery-main>
                <img src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-cover">
            </div>
            <div class="grid grid-cols-4 gap-2">
                <?php foreach ([[1, '50% 50%'], [1.8, '25% 30%'], [1.8, '75% 35%'], [2.4, '50% 75%']] as $i => [$scale, $origin]): ?>
                    <button type="button" class="gallery-thumb <?= $i === 0 ? 'is-active' : '' ?> h-20 rounded-lg overflow-hidden bg-gray-100" data-scale="<?= $scale ?>" data-origin="<?= $origin ?>" aria-label="View detail <?= $i + 1 ?>">
                        <img src="<?= e($img) ?>" alt="" class="w-full h-full object-cover" style="transform:scale(<?= $scale ?>);transform-origin:<?= $origin ?>" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Info -->
        <div class="space-y-6">
            <div>
                <span class="<?= $inStock ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?> text-xs font-semibold px-2.5 py-1 rounded"><?= $inStock ? 'In Stock' : 'Out of Stock' ?></span>
                <h1 class="text-3xl font-extrabold text-gray-900 mt-2"><?= e($product['name']) ?></h1>
                <p class="text-xs text-gray-500 mt-1">Sold by: <span class="font-semibold text-gray-800"><?= e($product['farm_name']) ?></span> (<?= e($product['county']) ?> County)</p>
                <div class="mt-2"><?= render_stars((float) $product['rating'], (int) $product['reviews_count']) ?></div>
            </div>

            <div class="text-2xl font-black text-brand-700">
                <?= e(format_currency($product['price'], 2)) ?> <span class="text-sm font-normal text-gray-500">/ <?= e($product['unit']) ?></span>
            </div>

            <p class="text-sm text-gray-600 leading-relaxed"><?= e($product['description']) ?></p>

            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-gray-500">Category</dt><dd class="font-semibold"><?= e($product['category_name']) ?></dd></div>
                <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-gray-500">Origin</dt><dd class="font-semibold"><?= e($product['county']) ?> County</dd></div>
                <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-gray-500">Sold per</dt><dd class="font-semibold"><?= e($product['unit']) ?></dd></div>
                <div class="bg-gray-50 rounded-lg p-3"><dt class="text-xs text-gray-500">Available</dt><dd class="font-semibold"><?= number_format((int) $product['stock']) ?></dd></div>
            </dl>

            <?php if ($inStock): ?>
            <div class="flex items-center space-x-4">
                <span class="text-xs font-bold text-gray-700 uppercase">Quantity:</span>
                <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    <button type="button" data-qty-step="-1" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold" aria-label="Decrease">-</button>
                    <span id="product-qty" data-max="<?= min((int) $product['stock'], MAX_CART_QTY) ?>" class="px-4 py-1 text-sm font-bold min-w-[3rem] text-center">1</span>
                    <button type="button" data-qty-step="1" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold" aria-label="Increase">+</button>
                </div>
            </div>
            <div class="flex space-x-4 pt-4">
                <button type="button" data-add-to-cart data-product-id="<?= (int) $product['id'] ?>" data-name="<?= e($product['name']) ?>" data-qty-source="#product-qty" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-lg shadow transition text-sm">Add to Cart</button>
                <button type="button" data-add-to-cart data-buy-now data-product-id="<?= (int) $product['id'] ?>" data-name="<?= e($product['name']) ?>" data-qty-source="#product-qty" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-6 py-3 rounded-lg transition text-sm">Buy Now</button>
            </div>
            <?php else: ?>
                <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3">This item is currently out of stock. Check back soon or browse similar produce below.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($related): ?>
    <section>
        <h2 class="text-xl font-bold text-gray-900 mb-6">More from <?= e($product['category_name']) ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6"><?php foreach ($related as $r) { echo render_product_card($r, 'h-40'); } ?></div>
    </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
