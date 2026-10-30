<?php
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Produce Catalogue';
$active     = 'catalogue';

$sel = $_GET['cat'] ?? [];
$sel = is_array($sel) ? array_values(array_filter(array_map('strval', $sel), fn($s) => preg_match('/^[a-z0-9\-]+$/', $s))) : [];
$filters = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'cat'       => $sel,
    'county'    => trim((string) ($_GET['county'] ?? '')),
    'max_price' => $_GET['max_price'] ?? PRICE_CAP,
    'sort'      => (string) ($_GET['sort'] ?? 'popularity'),
    'page'      => (int) ($_GET['page'] ?? 1),
];
$result     = fetch_products($filters);
$categories = fetch_categories();
$counties   = fetch_counties();
$maxPrice   = min(PRICE_CAP, max(50, (int) $filters['max_price']));
$from       = $result['total'] ? ($result['page'] - 1) * $result['per_page'] + 1 : 0;
$to         = $result['total'] ? $from + count($result['items']) - 1 : 0;
$extra_scripts = ['catalogue.js'];

require __DIR__ . '/includes/header.php';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Produce Catalogue</h1>

    <form id="filter-form" method="get" action="<?= e(url('catalogue.php')) ?>" class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Filters -->
        <aside class="bg-white p-6 rounded-xl border border-gray-200 h-fit space-y-6">
            <h2 class="font-bold text-gray-900 border-b border-gray-200 pb-3 text-base">Filters</h2>

            <div class="space-y-2">
                <label class="text-xs font-bold uppercase text-gray-500 tracking-wider">Search</label>
                <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. tomatoes" class="input">
            </div>

            <fieldset class="space-y-2">
                <legend class="text-xs font-bold uppercase text-gray-500 tracking-wider">Category</legend>
                <div class="space-y-1.5 text-sm">
                    <label class="flex items-center space-x-2 text-gray-700"><input type="checkbox" data-cat-all class="rounded text-brand-600" <?= $sel ? '' : 'checked' ?>> <span>All Categories</span></label>
                    <?php foreach ($categories as $c): ?>
                        <label class="flex items-center space-x-2 text-gray-700"><input type="checkbox" name="cat[]" value="<?= e($c['slug']) ?>" class="rounded text-brand-600" <?= in_array($c['slug'], $sel, true) ? 'checked' : '' ?>> <span><?= e($c['name']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="space-y-2">
                <label for="county" class="text-xs font-bold uppercase text-gray-500 tracking-wider">County / Origin</label>
                <select id="county" name="county" class="input">
                    <option value="">All Counties</option>
                    <?php foreach ($counties as $c): ?><option <?= $filters['county'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label for="max_price" class="text-xs font-bold uppercase text-gray-500 tracking-wider">Max Price: <span id="price-label" class="text-brand-700"><?= e(format_currency($maxPrice)) ?></span></label>
                <input id="max_price" type="range" name="max_price" min="50" max="<?= PRICE_CAP ?>" step="50" value="<?= $maxPrice ?>" class="w-full accent-brand-600">
                <div class="flex justify-between text-xs text-gray-500"><span><?= e(format_currency(50)) ?></span><span><?= e(format_currency(PRICE_CAP)) ?></span></div>
            </div>

            <button type="submit" class="w-full bg-brand-600 text-white font-semibold py-2 rounded-lg text-sm hover:bg-brand-700 transition">Apply Filters</button>
            <a href="<?= e(url('catalogue.php')) ?>" data-reset-filters class="block text-center text-xs text-gray-500 hover:text-brand-700">Reset filters</a>
        </aside>

        <!-- Results -->
        <section class="lg:col-span-3 space-y-6" aria-live="polite">
            <div class="flex flex-wrap gap-3 justify-between items-center bg-white p-4 rounded-xl border border-gray-200">
                <span class="text-sm text-gray-600" id="result-summary">Showing <span class="font-bold text-gray-900"><?= $from ?>–<?= $to ?></span> of <?= (int) $result['total'] ?> results</span>
                <div class="flex items-center space-x-2">
                    <label for="sort" class="text-sm text-gray-500">Sort by:</label>
                    <select id="sort" name="sort" class="border border-gray-300 rounded-lg p-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php foreach (['popularity' => 'Popularity', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low', 'newest' => 'Newest Arrivals'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $filters['sort'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="catalogue-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <?php if ($result['items']): foreach ($result['items'] as $p) { echo render_product_card($p, 'h-40'); }
                else: ?>
                    <div class="col-span-full text-center py-16 bg-white border border-dashed border-gray-300 rounded-xl">
                        <img src="<?= e(asset('images/placeholder.svg')) ?>" alt="" class="w-40 mx-auto rounded-xl mb-4 opacity-80">
                        <p class="font-semibold text-gray-800">No produce matches those filters</p>
                        <p class="text-sm text-gray-500 mt-1">Try a different category, county or a higher max price.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="catalogue-pagination"><?= render_pagination($result['page'], $result['pages']) ?></div>
        </section>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
