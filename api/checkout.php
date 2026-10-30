<?php
/**
 * Checkout + M-Pesa (Safaricom Daraja STK Push) handler.
 *
 *  POST ?action=create    (default) validate cart → create order → send STK push
 *  GET  ?action=status&order=ID     poll payment status for the signed-in buyer
 *  POST ?action=callback            Daraja calls this when the customer completes / cancels
 *
 * With MPESA_SIMULATE = true (config/constants.php) no network call is made; the order is
 * marked paid automatically a few seconds after the push so the whole flow can be demoed.
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

$action = $_GET['action'] ?? 'create';

/* ================================================================== */
/* Daraja helpers                                                      */
/* ================================================================== */
function mpesa_base(): string
{
    return MPESA_ENV === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function mpesa_http(string $url, array $headers, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($raw === false) { throw new RuntimeException('M-Pesa network error: ' . $err); }
    $json = json_decode($raw, true);
    if (!is_array($json) || $code >= 400) {
        throw new RuntimeException('M-Pesa error (' . $code . '): ' . substr((string) $raw, 0, 200));
    }
    return $json;
}

/** @return array{checkout_request_id:string, merchant_request_id:string} */
function mpesa_stk_push(string $msisdn, int $amount, string $reference): array
{
    if (MPESA_SIMULATE) {
        return ['checkout_request_id' => 'ws_CO_SIM_' . bin2hex(random_bytes(6)), 'merchant_request_id' => 'SIM-' . bin2hex(random_bytes(4))];
    }
    $auth  = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    $token = mpesa_http(mpesa_base() . '/oauth/v1/generate?grant_type=client_credentials', ['Authorization: Basic ' . $auth])['access_token'] ?? '';
    $ts    = date('YmdHis');
    $res   = mpesa_http(mpesa_base() . '/mpesa/stkpush/v1/processrequest', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password'          => base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $ts),
        'Timestamp'         => $ts,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => $amount,
        'PartyA'            => $msisdn,
        'PartyB'            => MPESA_SHORTCODE,
        'PhoneNumber'       => $msisdn,
        'CallBackURL'       => MPESA_CALLBACK_URL,
        'AccountReference'  => $reference,
        'TransactionDesc'   => 'AgriMarket order ' . $reference,
    ]);
    if (($res['ResponseCode'] ?? '1') !== '0') {
        throw new RuntimeException($res['errorMessage'] ?? $res['ResponseDescription'] ?? 'STK push was rejected.');
    }
    return ['checkout_request_id' => $res['CheckoutRequestID'], 'merchant_request_id' => $res['MerchantRequestID'] ?? ''];
}

/* ================================================================== */
/* Daraja callback (no session / CSRF – called by Safaricom)           */
/* ================================================================== */
if ($action === 'callback') {
    $payload  = json_decode(file_get_contents('php://input'), true);
    $cb       = $payload['Body']['stkCallback'] ?? null;
    if ($cb && !empty($cb['CheckoutRequestID'])) {
        if ((int) ($cb['ResultCode'] ?? 1) === 0) {
            $receipt = '';
            foreach ($cb['CallbackMetadata']['Item'] ?? [] as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') { $receipt = (string) $item['Value']; }
            }
            db()->prepare("UPDATE orders SET payment_status = 'paid', mpesa_receipt = ?, paid_at = NOW() WHERE mpesa_checkout_id = ? AND payment_status <> 'paid'")
                ->execute([$receipt, $cb['CheckoutRequestID']]);
        } else {
            db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE mpesa_checkout_id = ? AND payment_status = 'pending'")
                ->execute([$cb['CheckoutRequestID']]);
        }
    }
    json_response(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
}

/* ================================================================== */
/* Poll payment status                                                 */
/* ================================================================== */
if ($action === 'status') {
    $user = require_login(true);
    $st   = db()->prepare('SELECT id, payment_status, mpesa_receipt, UNIX_TIMESTAMP(created_at) AS ts FROM orders WHERE id = ? AND user_id = ?');
    $st->execute([(int) ($_GET['order'] ?? 0), $user['id']]);
    $o = $st->fetch();
    if (!$o) { json_response(['success' => false, 'message' => 'Order not found.'], 404); }

    // Demo mode: pretend the customer entered their PIN ~6 seconds after the push.
    if (MPESA_SIMULATE && $o['payment_status'] === 'pending' && time() - (int) $o['ts'] >= 6) {
        $receipt = 'SIM' . strtoupper(bin2hex(random_bytes(4)));
        db()->prepare("UPDATE orders SET payment_status = 'paid', mpesa_receipt = ?, paid_at = NOW() WHERE id = ?")->execute([$receipt, $o['id']]);
        $o['payment_status'] = 'paid';
        $o['mpesa_receipt']  = $receipt;
    }
    json_response(['success' => true, 'payment_status' => $o['payment_status'], 'receipt' => $o['mpesa_receipt'], 'redirect' => url('checkout.php?order=' . (int) $o['id'])]);
}

/* ================================================================== */
/* Create order + STK push                                             */
/* ================================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
$user = require_login(true);
if (!verify_csrf()) {
    json_response(['success' => false, 'message' => 'Your session expired. Please refresh the page.'], 419);
}

$name    = trim((string) ($_POST['delivery_name'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$county  = trim((string) ($_POST['county'] ?? ''));
$notes   = trim((string) ($_POST['notes'] ?? ''));
$msisdn  = normalize_msisdn((string) ($_POST['mpesa_phone'] ?? ''));

$errors = [];
if ($name === '' || mb_strlen($name) > 120)       { $errors['delivery_name'] = 'Enter the name of the person receiving the order.'; }
if ($address === '' || mb_strlen($address) > 255) { $errors['address'] = 'Enter a delivery address or landmark.'; }
if ($county === '' || mb_strlen($county) > 80)    { $errors['county'] = 'Choose a delivery county.'; }
if (!$msisdn)                                      { $errors['mpesa_phone'] = 'Enter a valid Safaricom number, e.g. 0712345678.'; }
if ($errors) { json_response(['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422); }

$pdo = db();
try {
    $pdo->beginTransaction();

    // Re-read prices and stock from the database – never trust the browser.
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) { throw new DomainException('Your cart is empty.'); }
    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT id, name, price, stock, farmer_id FROM products WHERE id IN ($in) AND status = 'active' FOR UPDATE");
    $st->execute($ids);
    $rows = $st->fetchAll();
    if (count($rows) !== count($ids)) { throw new DomainException('Some items in your cart are no longer available. Please review your cart.'); }

    $subtotal = 0.0; $lines = [];
    foreach ($rows as $r) {
        $qty = (int) $cart[$r['id']];
        if ($qty < 1 || $qty > (int) $r['stock']) { throw new DomainException('Not enough stock for ' . $r['name'] . '.'); }
        $subtotal += $qty * (float) $r['price'];
        $lines[]   = [$r, $qty];
    }
    $delivery = DELIVERY_FEE;
    $total    = $subtotal + $delivery;

    $pdo->prepare('INSERT INTO orders (user_id, subtotal, delivery_fee, total, mpesa_phone, delivery_name, address, county, notes)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$user['id'], $subtotal, $delivery, $total, $msisdn, $name, $address, $county, $notes]);
    $orderId = (int) $pdo->lastInsertId();

    $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, farmer_id, name, price, qty) VALUES (?, ?, ?, ?, ?, ?)');
    $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
    foreach ($lines as [$r, $qty]) {
        $ins->execute([$orderId, $r['id'], $r['farmer_id'], $r['name'], $r['price'], $qty]);
        $dec->execute([$qty, $r['id']]);
    }

    $push = mpesa_stk_push($msisdn, (int) ceil($total), 'AM' . $orderId);
    $pdo->prepare('UPDATE orders SET mpesa_checkout_id = ?, mpesa_merchant_id = ? WHERE id = ?')
        ->execute([$push['checkout_request_id'], $push['merchant_request_id'], $orderId]);

    $pdo->commit();
    $_SESSION['cart'] = [];

    json_response([
        'success'  => true,
        'order_id' => $orderId,
        'message'  => 'Payment prompt sent to ' . substr($msisdn, 0, 6) . '***' . substr($msisdn, -3) . '. Enter your M-Pesa PIN to complete the order.',
        'status_url' => url('api/checkout.php?action=status&order=' . $orderId),
        'redirect'   => url('checkout.php?order=' . $orderId),
        'simulated'  => MPESA_SIMULATE,
    ]);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    json_response(['success' => false, 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[AgriMarket] checkout failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'We could not start the M-Pesa payment. Your cart is unchanged — please try again.'], 502);
}
