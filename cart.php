<?php
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Your Cart';
$active     = 'cart';
$items      = cart_items();
$totals     = cart_totals($items);

require __DIR__ . '/includes/header.php';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Your Cart (<span data-cart-heading-count><?= (int) $totals['count'] ?></span> Items)</h1>

    <div id="cart-empty" class="<?= $items ? 'hidden' : '' ?> text-center bg-white border border-dashed border-gray-300 rounded-2xl py-16">
        <img src="<?= e(asset('images/products/mangoes.svg')) ?>" alt="" class="w-48 mx-auto rounded-2xl mb-4">
        <p class="font-semibold text-gray-900">Your cart is empty</p>
        <p class="text-sm text-gray-500 mt-1 mb-6">Fresh produce from Kenyan farms is a click away.</p>
        <a href="<?= e(url('catalogue.php')) ?>" class="btn btn-primary">Browse produce</a>
    </div>

    <div id="cart-layout" class="<?= $items ? '' : 'hidden' ?> grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4" id="cart-lines">
            <?php foreach ($items as $i): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4" data-cart-line="<?= (int) $i['id'] ?>" data-name="<?= e($i['name']) ?>">
                    <div class="flex items-center space-x-4">
                        <a href="<?= e(url('product.php?id=' . (int) $i['id'])) ?>"><img src="<?= e(product_image_url($i['image'])) ?>" alt="" class="w-16 h-16 rounded-lg object-cover bg-gray-100"></a>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm"><?= e($i['name']) ?></h3>
                            <p class="text-xs text-gray-500"><?= e($i['farm_name']) ?></p>
                            <span class="text-xs font-bold text-brand-700"><?= e(format_currency($i['price'])) ?> / <?= e($i['unit']) ?></span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-5">
                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden text-sm">
                            <button type="button" data-line-step="-1" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 font-bold" aria-label="Decrease quantity">-</button>
                            <span data-line-qty class="px-3 py-1 font-bold min-w-[2.5rem] text-center"><?= (int) $i['qty'] ?></span>
                            <button type="button" data-line-step="1" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 font-bold" aria-label="Increase quantity">+</button>
                        </div>
                        <span data-line-total class="font-bold text-gray-900 text-sm w-24 text-right"><?= e(format_currency($i['line_total'])) ?></span>
                        <button type="button" data-remove-line class="text-red-500 hover:text-red-700 text-sm p-1" aria-label="Remove <?= e($i['name']) ?>"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="bg-white border border-gray-200 rounded-xl p-6 h-fit space-y-6">
            <h2 class="font-bold text-gray-900 border-b border-gray-200 pb-3 text-base">Order Summary</h2>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between text-gray-600"><span>Subtotal</span><span id="sum-subtotal" class="font-semibold text-gray-900"><?= e(format_currency($totals['subtotal'], 2)) ?></span></div>
                <div class="flex justify-between text-gray-600"><span>Estimated Delivery</span><span id="sum-delivery" class="font-semibold text-gray-900"><?= e(format_currency($totals['delivery'], 2)) ?></span></div>
                <div class="border-t border-gray-200 pt-3 flex justify-between font-bold text-base text-gray-900"><span>Total</span><span id="sum-total" class="text-brand-700"><?= e(format_currency($totals['total'], 2)) ?></span></div>
            </div>
            <a href="<?= e(url('checkout.php')) ?>" class="block text-center w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-lg transition text-sm"><i class="fa-solid fa-mobile-screen mr-1"></i> Proceed to Checkout</a>
            <button type="button" data-clear-cart class="w-full text-xs text-gray-500 hover:text-red-600">Clear cart</button>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
