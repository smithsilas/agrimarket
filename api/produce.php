<?php
/**
 * GET /api/produce.php
 * JSON endpoint for catalogue searching / filtering / pagination.
 *
 * Params: q, cat[] (slugs), county, max_price, sort (popularity|price_asc|price_desc|newest), page
 */
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$cats = $_GET['cat'] ?? [];
$cats = is_array($cats) ? array_filter(array_map('strval', $cats), fn($s) => preg_match('/^[a-z0-9\-]+$/', $s)) : [];

try {
    $result = fetch_products([
        'q'         => trim((string) ($_GET['q'] ?? '')),
        'cat'       => $cats,
        'county'    => trim((string) ($_GET['county'] ?? '')),
        'max_price' => $_GET['max_price'] ?? null,
        'sort'      => (string) ($_GET['sort'] ?? 'popularity'),
        'page'      => (int) ($_GET['page'] ?? 1),
    ]);
} catch (Throwable $e) {
    error_log('[AgriMarket] produce api: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Could not load produce right now.'], 500);
}

$html = '';
foreach ($result['items'] as $p) { $html .= render_product_card($p, 'h-40'); }
if ($html === '') {
    $html = '<div class="col-span-full text-center py-16 bg-white border border-dashed border-gray-300 rounded-xl">'
          . '<img src="' . e(asset('images/placeholder.svg')) . '" alt="" class="w-40 mx-auto rounded-xl mb-4 opacity-80">'
          . '<p class="font-semibold text-gray-800">No produce matches those filters</p>'
          . '<p class="text-sm text-gray-500 mt-1">Try a different category, county or a higher max price.</p></div>';
}

$from = $result['total'] ? (($result['page'] - 1) * $result['per_page'] + 1) : 0;
$to   = $result['total'] ? $from + count($result['items']) - 1 : 0;

json_response([
    'success'    => true,
    'total'      => $result['total'],
    'page'       => $result['page'],
    'pages'      => $result['pages'],
    'from'       => $from,
    'to'         => $to,
    'html'       => $html,
    'pagination' => render_pagination($result['page'], $result['pages']),
]);
