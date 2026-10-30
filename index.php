<?php
require_once __DIR__ . '/includes/functions.php';

$page_title = null;          // uses the default "AgriMarket – tagline" title
$active     = 'home';

$categories = fetch_categories();
$featured   = db()->query("SELECT p.*, u.farm_name FROM products p JOIN users u ON u.id = p.farmer_id
                           WHERE p.status = 'active' AND p.featured = 1 ORDER BY p.reviews_count DESC LIMIT 4")->fetchAll();
$farmers    = fetch_farmers(3);

require __DIR__ . '/includes/header.php';
?>
<div class="space-y-12 pb-16">

    <!-- Hero -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div class="bg-gradient-to-br from-brand-50 to-amber-50 rounded-2xl p-8 md:p-12 grid grid-cols-1 md:grid-cols-2 gap-8 items-center border border-gray-200">
            <div class="space-y-6">
                <span class="inline-block bg-brand-100 text-brand-800 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Direct From Farmers</span>
                <h1 class="text-3xl md:text-5xl font-extrabold text-gray-900 leading-tight">Fresh produce, straight from the farm</h1>
                <p class="text-gray-600 text-base md:text-lg">Connect directly with trusted local farmers and fresh produce suppliers across Kenya for quality crops delivered fast.</p>
                <div class="flex flex-wrap gap-4">
                    <a href="<?= e(url('catalogue.php')) ?>" class="btn btn-primary">Shop Produce</a>
                    <a href="<?= e(url('register.php?role=farmer')) ?>" class="btn btn-secondary">Become a Supplier</a>
                </div>
            </div>

            <div class="hero-art h-72 md:h-96 border border-white/60 shadow-xl">
                <img class="hero-bg" src="<?= e(asset('images/hero.svg')) ?>" alt="Sunlit Kenyan farmland with a red barn and rows of crops">
                <div class="float-card" style="top:8%;left:5%"><img src="<?= e(asset('images/products/tomatoes.svg')) ?>" alt=""><span>Tomatoes<small>from Nyeri</small></span></div>
                <div class="float-card" style="top:38%;right:4%"><img src="<?= e(asset('images/products/maize.svg')) ?>" alt=""><span>Yellow maize<small>from Nakuru</small></span></div>
                <div class="float-card" style="bottom:7%;left:12%"><img src="<?= e(asset('images/products/avocados.svg')) ?>" alt=""><span>Hass avocados<small>Picked this week</small></span></div>
            </div>
        </div>
    </section>

    <!-- Shop by category -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Shop by category</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
            <?php foreach ($categories as $c): ?>
                <a href="<?= e(url('catalogue.php?cat[]=' . $c['slug'])) ?>" class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:border-brand-500 hover:shadow-md transition block">
                    <div class="w-12 h-12 bg-<?= e($c['color']) ?>-50 text-<?= e($c['color']) ?>-600 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid <?= e($c['icon']) ?> text-xl"></i>
                    </div>
                    <span class="text-sm font-semibold text-gray-800"><?= e($c['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Featured produce -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-900">Featured produce</h2>
            <a href="<?= e(url('catalogue.php')) ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-700">View all &rarr;</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ($featured as $p) { echo render_product_card($p); } ?>
        </div>
    </section>

    <!-- How it works -->
    <section id="how-it-works" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white py-10 rounded-2xl border border-gray-200 px-4">
            <h2 class="text-xl font-bold text-gray-900 mb-8 text-center">How it works</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ([
                    ['1', 'Browse & Select',       'Choose from verified local farmers and suppliers across Kenya.', 'products/kale.svg'],
                    ['2', 'Order & Pay Securely',  'Place your wholesale or retail order with instant M-Pesa integration.', 'products/mangoes.svg'],
                    ['3', 'Direct Delivery',       'Receive fresh produce delivered directly from the source to your door.', 'products/carrots.svg'],
                ] as [$n, $t, $d, $img]): ?>
                    <div class="text-center space-y-3 px-4">
                        <div class="relative w-28 h-28 mx-auto">
                            <img src="<?= e(asset('images/' . $img)) ?>" alt="" class="w-28 h-28 rounded-full object-cover ring-4 ring-brand-100" loading="lazy">
                            <span class="absolute -top-1 -left-1 w-9 h-9 bg-brand-600 text-white rounded-full flex items-center justify-center font-bold shadow"><?= $n ?></span>
                        </div>
                        <h3 class="font-bold text-gray-900"><?= e($t) ?></h3>
                        <p class="text-sm text-gray-600"><?= e($d) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Meet our farmers -->
    <section id="farmers" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-900">Meet our farmers</h2>
            <a href="<?= e(url('catalogue.php')) ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Browse all produce &rarr;</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <?php foreach ($farmers as $f): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3 text-center hover:shadow-md transition">
                    <?= avatar_html($f, 'w-20 h-20') ?>
                    <h3 class="font-bold text-gray-900"><?= e($f['farm_name']) ?></h3>
                    <p class="text-xs text-gray-500"><?= e($f['county']) ?> County &bull; Specializes in <?= e($f['specialty']) ?></p>
                    <a href="<?= e(url('catalogue.php?q=' . rawurlencode($f['farm_name']))) ?>" class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold py-2 rounded-lg transition">View produce</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Supplier CTA -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden bg-brand-700 text-white rounded-2xl p-8 md:p-10 flex flex-col md:flex-row justify-between items-center gap-6">
            <img src="<?= e(asset('images/hero.svg')) ?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-20 mix-blend-luminosity" aria-hidden="true">
            <div class="relative">
                <h3 class="text-2xl font-bold">Are you a farmer? Sell your produce today.</h3>
                <p class="text-brand-100 text-sm mt-1">Join hundreds of local suppliers reaching businesses and households across Kenya directly.</p>
            </div>
            <a href="<?= e(url('register.php?role=farmer')) ?>" class="relative bg-white text-brand-800 hover:bg-brand-50 font-bold px-6 py-3 rounded-lg shadow transition text-sm whitespace-nowrap">Learn More &amp; Register</a>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
