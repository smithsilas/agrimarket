<?php
/**
 * Session shopping-cart API.
 *
 *  GET  /api/cart.php                       → current summary
 *  POST /api/cart.php  action=add     product_id, qty
 *  POST /api/cart.php  action=update  product_id, qty      (sets absolute quantity)
 *  POST /api/cart.php  action=remove  product_id
 *  POST /api/cart.php  action=clear
 *
 * POST requests must send the CSRF token (field csrf_token or header X-CSRF-Token).
 */
require_once __DIR__ . '/../includes/functions.php';

function cart_payload(string $message = '', array $extra = []): array
{
    $items  = cart_items();
    $totals = cart_totals($items);
    return array_merge([
        'success'       => true,
        'message'       => $message,
        'count'         => $totals['count'],
        'subtotal'      => $totals['subtotal'],
        'delivery'      => $totals['delivery'],
        'total'         => $totals['total'],
        'subtotal_fmt'  => format_currency($totals['subtotal'], 2),
        'delivery_fmt'  => format_currency($totals['delivery'], 2),
        'total_fmt'     => format_currency($totals['total'], 2),
        'lines'         => array_map(fn($i) => ['id' => (int) $i['id'], 'qty' => $i['qty'], 'line_total_fmt' => format_currency($i['line_total'])], $items),
    ], $extra);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(cart_payload());
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
if (!verify_csrf()) {
    json_response(['success' => false, 'message' => 'Your session expired. Please refresh the page.'], 419);
}

$action = (string) ($_POST['action'] ?? '');
$pid    = (int) ($_POST['product_id'] ?? 0);
$qty    = (int) ($_POST['qty'] ?? 1);
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

switch ($action) {
    case 'add':
    case 'update':
        $product = $pid > 0 ? fetch_product($pid) : null;
        if (!$product) {
            json_response(['success' => false, 'message' => 'That product is no longer available.'], 404);
        }
        $stock = (int) $product['stock'];
        if ($stock < 1) {
            json_response(['success' => false, 'message' => $product['name'] . ' is out of stock.'], 409);
        }
        if ($action === 'add') {
            $new = ($_SESSION['cart'][$pid] ?? 0) + max(1, $qty);
        } else {
            $new = $qty;
            if ($new < 1) {   // setting qty to 0 removes the line
                unset($_SESSION['cart'][$pid]);
                json_response(cart_payload('Item removed.'));
            }
        }
        $capped = min($new, $stock, MAX_CART_QTY);
        $_SESSION['cart'][$pid] = $capped;
        $msg = $action === 'add' ? 'Added ' . $product['name'] . ' to your cart.' : 'Cart updated.';
        if ($capped < $new) { $msg = 'Only ' . $capped . ' available for ' . $product['name'] . '.'; }
        json_response(cart_payload($msg));

    case 'remove':
        unset($_SESSION['cart'][$pid]);
        json_response(cart_payload('Item removed.'));

    case 'clear':
        $_SESSION['cart'] = [];
        json_response(cart_payload('Cart cleared.'));

    default:
        json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}
