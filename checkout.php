<?php
require_once __DIR__ . '/includes/auth_middleware.php';

$user = require_login();
$page_title = 'Checkout';
$active     = 'cart';

/* ---------- Order confirmation / payment-status view: checkout.php?order=ID ---------- */
if (isset($_GET['order'])) {
    $st = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
    $st->execute([(int) $_GET['order'], $user['id']]);
    $order = $st->fetch();
    if (!$order) { set_flash('error', 'Order not found.'); redirect('index.php'); }

    $lines = db()->prepare('SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
    $lines->execute([$order['id']]);
    $lines = $lines->fetchAll();

    require __DIR__ . '/includes/header.php';
    $paid = $order['payment_status'] === 'paid';
    ?>
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6" id="order-view" data-order-id="<?= (int) $order['id'] ?>" data-payment="<?= e($order['payment_status']) ?>">
        <div class="bg-white border border-gray-200 rounded-2xl p-8 text-center space-y-3">
            <?php if ($paid): ?>
                <div class="w-16 h-16 mx-auto rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-3xl"><i class="fa-solid fa-check"></i></div>
                <h1 class="text-2xl font-extrabold text-gray-900">Payment received – thank you!</h1>
                <p class="text-sm text-gray-600">M-Pesa receipt <span class="font-bold"><?= e($order['mpesa_receipt']) ?></span>. Your farmers have been notified and will prepare your order.</p>
            <?php elseif ($order['payment_status'] === 'failed'): ?>
                <div class="w-16 h-16 mx-auto rounded-full bg-red-100 text-red-600 flex items-center justify-center text-3xl"><i class="fa-solid fa-xmark"></i></div>
                <h1 class="text-2xl font-extrabold text-gray-900">Payment was not completed</h1>
                <p class="text-sm text-gray-600">The M-Pesa request was cancelled or timed out. No money was taken. You can place the order again from your cart.</p>
            <?php else: ?>
                <div class="w-16 h-16 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-2xl"><span class="spinner spinner-dark" style="width:1.6rem;height:1.6rem"></span></div>
                <h1 class="text-2xl font-extrabold text-gray-900">Waiting for M-Pesa…</h1>
                <p class="text-sm text-gray-600">Enter your M-Pesa PIN on <?= e(substr($order['mpesa_phone'], 0, 6)) ?>***<?= e(substr($order['mpesa_phone'], -3)) ?> to pay <strong><?= e(format_currency($order['total'], 2)) ?></strong>. This page updates automatically.</p>
            <?php endif; ?>
            <p class="text-xs text-gray-500">Order #<?= (int) $order['id'] ?> &bull; <?= e(date('j M Y, g:i a', strtotime($order['created_at']))) ?></p>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-4">
            <h2 class="font-bold text-gray-900">Items</h2>
            <?php foreach ($lines as $l): ?>
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center space-x-3"><img src="<?= e(product_image_url($l['image'] ?? null)) ?>" alt="" class="w-12 h-12 rounded-lg object-cover"><span><?= e($l['name']) ?> <span class="text-gray-500">× <?= (int) $l['qty'] ?></span></span></div>
                    <span class="font-semibold"><?= e(format_currency($l['price'] * $l['qty'])) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="border-t pt-3 text-sm space-y-1">
                <div class="flex justify-between text-gray-600"><span>Delivery</span><span><?= e(format_currency($order['delivery_fee'], 2)) ?></span></div>
                <div class="flex justify-between font-bold text-base"><span>Total</span><span class="text-brand-700"><?= e(format_currency($order['total'], 2)) ?></span></div>
            </div>
            <p class="text-xs text-gray-500">Delivering to <?= e($order['delivery_name']) ?>, <?= e($order['address']) ?>, <?= e($order['county']) ?>.</p>
        </div>
        <div class="text-center"><a href="<?= e(url('catalogue.php')) ?>" class="btn btn-primary">Keep shopping</a></div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Delivery & payment form ---------- */
$items = cart_items();
if (!$items) { set_flash('info', 'Your cart is empty – add some produce first.'); redirect('catalogue.php'); }
$totals   = cart_totals($items);
$counties = ['Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa','Homa Bay','Isiolo','Kajiado','Kakamega','Kericho','Kiambu','Kilifi','Kirinyaga','Kisii','Kisumu','Kitui','Kwale','Laikipia','Lamu','Machakos','Makueni','Mandera','Marsabit','Meru','Migori','Mombasa','Murang\'a','Nairobi','Nakuru','Nandi','Narok','Nyamira','Nyandarua','Nyeri','Samburu','Siaya','Taita-Taveta','Tana River','Tharaka-Nithi','Trans Nzoia','Turkana','Uasin Gishu','Vihiga','Wajir','West Pokot'];
$extra_scripts = [];

require __DIR__ . '/includes/header.php';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Delivery &amp; Checkout</h1>

    <form id="checkout-form" class="grid grid-cols-1 lg:grid-cols-3 gap-8" novalidate>
        <?= csrf_field() ?>
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
                <h2 class="font-bold text-gray-900">Delivery details</h2>
                <div>
                    <label class="label" for="delivery_name">Receiver's full name</label>
                    <input class="input" id="delivery_name" name="delivery_name" value="<?= e($user['first_name'] . ' ' . $user['last_name']) ?>" required>
                    <p class="field-error text-xs text-red-600 mt-1" data-error-for="delivery_name"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="county">County</label>
                        <select class="input" id="county" name="county" required>
                            <option value="">Choose county…</option>
                            <?php foreach ($counties as $c): ?><option <?= ($user['county'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                        </select>
                        <p class="field-error text-xs text-red-600 mt-1" data-error-for="county"></p>
                    </div>
                    <div>
                        <label class="label" for="address">Address / landmark</label>
                        <input class="input" id="address" name="address" placeholder="e.g. Kilimani, next to Yaya Centre" required>
                        <p class="field-error text-xs text-red-600 mt-1" data-error-for="address"></p>
                    </div>
                </div>
                <div>
                    <label class="label" for="notes">Delivery notes <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea class="input" id="notes" name="notes" rows="2" maxlength="500" placeholder="Gate code, preferred time…"></textarea>
                </div>
            </section>

            <section class="bg-white border border-gray-200 rounded-xl p-6 space-y-3">
                <h2 class="font-bold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-mobile-screen text-brand-600"></i> Pay with M-Pesa</h2>
                <label class="label" for="mpesa_phone">M-Pesa phone number</label>
                <input class="input" id="mpesa_phone" name="mpesa_phone" type="tel" inputmode="tel" placeholder="0712345678" value="<?= e($user['phone'] ? preg_replace('/^254/', '0', $user['phone']) : '') ?>" required>
                <p class="field-error text-xs text-red-600" data-error-for="mpesa_phone"></p>
                <p class="text-xs text-gray-500">We'll send an STK push to this number. Enter your M-Pesa PIN to approve the payment.<?= MPESA_SIMULATE ? ' <span class="font-semibold text-amber-700">Demo mode: no real money is charged.</span>' : '' ?></p>
            </section>
        </div>

        <aside class="bg-white border border-gray-200 rounded-xl p-6 h-fit space-y-5">
            <h2 class="font-bold text-gray-900 border-b border-gray-200 pb-3">Order Summary</h2>
            <ul class="space-y-3 text-sm">
                <?php foreach ($items as $i): ?>
                    <li class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-3 min-w-0"><img src="<?= e(product_image_url($i['image'])) ?>" alt="" class="w-10 h-10 rounded-lg object-cover"><span class="truncate"><?= e($i['name']) ?> <span class="text-gray-500">× <?= (int) $i['qty'] ?></span></span></span>
                        <span class="font-semibold"><?= e(format_currency($i['line_total'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="space-y-2 text-sm border-t border-gray-200 pt-3">
                <div class="flex justify-between text-gray-600"><span>Subtotal</span><span class="font-semibold text-gray-900"><?= e(format_currency($totals['subtotal'], 2)) ?></span></div>
                <div class="flex justify-between text-gray-600"><span>Estimated Delivery</span><span class="font-semibold text-gray-900"><?= e(format_currency($totals['delivery'], 2)) ?></span></div>
                <div class="flex justify-between font-bold text-base text-gray-900 pt-2 border-t border-gray-200"><span>Total</span><span class="text-brand-700"><?= e(format_currency($totals['total'], 2)) ?></span></div>
            </div>
            <p id="checkout-error" class="hidden text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3"></p>
            <button type="submit" id="checkout-submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-lg transition text-sm">Pay <?= e(format_currency($totals['total'], 2)) ?> with M-Pesa</button>
            <a href="<?= e(url('cart.php')) ?>" class="block text-center text-xs text-gray-500 hover:text-brand-700">Back to cart</a>
        </aside>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
