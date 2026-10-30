<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/* ------------------------------------------------------------------ *
 *  Database
 * ------------------------------------------------------------------ */
function db(): PDO
{
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements (SQL-injection defence)
            ]
        );
    }
    return $pdo;
}

/* ------------------------------------------------------------------ *
 *  Output / routing helpers
 * ------------------------------------------------------------------ */
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $p = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($p, '/');
}

function go(string $p): never
{
    header('Location: ' . url($p));
    exit;
}

function cash($n): string
{
    $n = (float)$n;
    return 'KSh ' . number_format($n, fmod($n, 1.0) === 0.0 ? 0 : 2);
}

function fmt_qty($q): string
{
    $s = rtrim(rtrim(number_format((float)$q, 2, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
}

function fmt_date(?string $d): string
{
    return $d ? date('d M Y, H:i', strtotime($d)) : '';
}

function initials(string $name): string
{
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $w) {
        $out .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $out ?: '?';
}

function receipt_no(int $id): string
{
    return 'AGM-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

function post(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

function flash(string $k, ?string $v = null): ?string
{
    if ($v !== null) {
        $_SESSION['_flash'][$k] = $v;
        return null;
    }
    $x = $_SESSION['_flash'][$k] ?? null;
    unset($_SESSION['_flash'][$k]);
    return $x;
}

function abort_page(int $code, string $title, string $msg): never
{
    http_response_code($code);
    require __DIR__ . '/header.php';
    echo '<section class="container section"><div class="empty"><h1>' . e($title) . '</h1><p>' . e($msg)
        . '</p><a class="btn" href="' . e(url('index.php')) . '">Back to home</a></div></section>';
    require __DIR__ . '/footer.php';
    exit;
}

/* ------------------------------------------------------------------ *
 *  CSRF
 * ------------------------------------------------------------------ */
function token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(token()) . '">';
}

function check_token(): void
{
    if (!isset($_POST['_csrf']) || !hash_equals(token(), (string)$_POST['_csrf'])) {
        http_response_code(419);
        exit('Session expired. Go back, refresh the page and try again.');
    }
}

/* ------------------------------------------------------------------ *
 *  Authentication / authorisation
 * ------------------------------------------------------------------ */
function me(): ?array
{
    static $loaded = false, $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = (int)($_SESSION['uid'] ?? 0);
    if (!$id) {
        return null;
    }
    $s = db()->prepare('SELECT user_id,full_name,email,role,status,phone,location,bio,created_at FROM users WHERE user_id=?');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row || $row['status'] === 'SUSPENDED') {   // suspended users lose access immediately
        unset($_SESSION['uid']);
        return null;
    }
    return $user = $row;
}

function login_required(string ...$roles): array
{
    $u = me();
    if (!$u) {
        flash('error', 'Please sign in first.');
        go('login.php');
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        abort_page(403, 'Access denied', 'Your account type does not have permission to open this page.');
    }
    return $u;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Brute-force protection: 5 failures per email or 40 per IP in 15 minutes. */
function login_locked(string $email): bool
{
    $a = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email=? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $a->execute([$email]);
    if ((int)$a->fetchColumn() >= 5) {
        return true;
    }
    $b = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $b->execute([client_ip()]);
    return (int)$b->fetchColumn() >= 40;
}

function record_failed_login(string $email): void
{
    db()->prepare('INSERT INTO login_attempts(email,ip) VALUES(?,?)')->execute([substr($email, 0, 190), client_ip()]);
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function clear_failed_logins(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE email=?')->execute([$email]);
}

/* ------------------------------------------------------------------ *
 *  Validation
 * ------------------------------------------------------------------ */
function norm_phone(string $p): string
{
    return (string)preg_replace('/[\s-]/', '', $p);
}

function valid_phone(string $p): bool
{
    return (bool)preg_match('/^\+?[0-9]{9,15}$/', norm_phone($p));
}

/** Returns a list of problems with the password (empty list = acceptable). */
function password_errors(string $pw, string $email = ''): array
{
    $err = [];
    if (mb_strlen($pw) < 8) {
        $err[] = 'Password must have at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        $err[] = 'Password must contain at least one letter and one number.';
    }
    $weak = ['password', 'password1', '12345678', '123456789', 'qwerty123', 'abc12345', 'iloveyou1', 'agrimarket1'];
    if (in_array(strtolower($pw), $weak, true) || ($email !== '' && strtolower($pw) === strtolower($email))) {
        $err[] = 'That password is too easy to guess. Choose something less common.';
    }
    return $err;
}

/**
 * Validates and stores an uploaded produce photo.
 * Returns a relative path (uploads/xxxx.jpg), or null when no file was sent.
 */
function save_upload(array $f): ?string
{
    $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($code !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed. Try a smaller image.');
    }
    if (($f['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Photo must be 2 MB or smaller.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || !@getimagesize($f['tmp_name'])) {
        throw new RuntimeException('Only JPG, PNG or WebP images are allowed.');
    }
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Could not store the photo on the server.');
    }
    $rel = 'uploads/' . bin2hex(random_bytes(12)) . '.' . $ext;   // random name: never trust client file names
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/' . $rel)) {
        throw new RuntimeException('Could not store the photo on the server.');
    }
    return $rel;
}

/* ------------------------------------------------------------------ *
 *  Produce presentation
 * ------------------------------------------------------------------ */
function photo_src(?string $s): ?string
{
    if (!$s) {
        return null;
    }
    if (str_starts_with($s, 'uploads/') && is_file(__DIR__ . '/' . $s)) {
        return url($s);
    }
    if (filter_var($s, FILTER_VALIDATE_URL) && in_array(parse_url($s, PHP_URL_SCHEME), ['https', 'http'], true)) {
        return $s;
    }
    return null;
}

function category_icon(string $name): string
{
    return [
        'Grains' => '🌾', 'Vegetables' => '🥬', 'Fruits' => '🍎', 'Dairy' => '🥛',
        'Tubers' => '🥔', 'Poultry & Eggs' => '🥚', 'Other' => '🧺',
    ][$name] ?? '🌱';
}

function render_card(array $p): void
{
    $img  = photo_src($p['photo_url'] ?? null);
    $href = url('product.php?id=' . (int)$p['produce_id']);
    $low  = (float)$p['quantity'] > 0 && (float)$p['quantity'] <= 10;
    ?>
    <article class="card produce-card">
        <a class="card-media" href="<?= e($href) ?>" tabindex="-1">
            <?php if ($img): ?>
                <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            <?php else: ?>
                <span class="ph" role="img" aria-label="<?= e($p['name']) ?>"><?= e(category_icon((string)($p['category_name'] ?? ''))) ?></span>
            <?php endif; ?>
            <?php if ($low): ?><span class="tag tag-warn">Only <?= e(fmt_qty($p['quantity'])) ?> left</span><?php endif; ?>
        </a>
        <div class="card-body">
            <span class="eyebrow"><?= e($p['category_name'] ?? '') ?></span>
            <h3><a href="<?= e($href) ?>"><?= e($p['name']) ?></a></h3>
            <p class="muted small">📍 <?= e($p['location']) ?></p>
            <div class="card-foot">
                <p class="price"><?= e(cash($p['price'])) ?> <small>/ <?= e($p['unit'] ?? 'unit') ?></small></p>
                <a class="btn btn-sm" href="<?= e($href) ?>">View</a>
            </div>
        </div>
    </article>
    <?php
}

function pager(int $page, int $pages, array $query = []): void
{
    if ($pages < 2) {
        return;
    }
    $link = static function (int $n) use ($query): string {
        return e('?' . http_build_query(array_filter($query + ['page' => $n], static fn($v) => $v !== '' && $v !== 0 && $v !== null)));
    };
    echo '<nav class="pager" aria-label="Pagination">';
    if ($page > 1) {
        echo '<a href="' . $link($page - 1) . '">← Previous</a>';
    }
    for ($i = 1; $i <= $pages; $i++) {
        echo $i === $page
            ? '<span class="current" aria-current="page">' . $i . '</span>'
            : '<a href="' . $link($i) . '">' . $i . '</a>';
    }
    if ($page < $pages) {
        echo '<a href="' . $link($page + 1) . '">Next →</a>';
    }
    echo '</nav>';
}

/* ------------------------------------------------------------------ *
 *  Cart
 * ------------------------------------------------------------------ */
function cart_count(): int
{
    return (int)array_sum($_SESSION['cart'] ?? []);
}

/** Loads cart lines with fresh prices and stock; drops items that are no longer available. */
function load_cart(): array
{
    $cart = $_SESSION['cart'] ?? [];
    $items = [];
    $total = 0.0;
    $problems = 0;
    if ($cart) {
        $ids = array_map('intval', array_keys($cart));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $s = db()->prepare(
            "SELECT p.*, c.category_name, u.full_name AS farmer
               FROM produce p
               JOIN categories c ON c.category_id = p.category_id
               JOIN users u ON u.user_id = p.farmer_id
              WHERE p.produce_id IN ($marks) AND p.status = 'APPROVED' AND u.status = 'APPROVED' AND p.quantity > 0"
        );
        $s->execute($ids);
        $found = [];
        foreach ($s->fetchAll() as $p) {
            $id = (int)$p['produce_id'];
            $qty = max(1, (int)$cart[$id]);
            $p['qty'] = $qty;
            $p['sub'] = $qty * (float)$p['price'];
            $p['over'] = $qty > (float)$p['quantity'];
            $problems += $p['over'] ? 1 : 0;
            $total += $p['sub'];
            $items[] = $p;
            $found[] = $id;
        }
        foreach ($ids as $id) {
            if (!in_array($id, $found, true)) {
                unset($_SESSION['cart'][$id]);
            }
        }
    }
    return ['items' => $items, 'total' => $total, 'problems' => $problems];
}

/* ------------------------------------------------------------------ *
 *  Orders  (Placed → Confirmed → Dispatched → Delivered)
 * ------------------------------------------------------------------ */
function order_flow(): array
{
    return ['PLACED', 'CONFIRMED', 'DISPATCHED', 'DELIVERED'];
}

function next_status(string $s): ?string
{
    $flow = order_flow();
    $i = array_search($s, $flow, true);
    return ($i !== false && isset($flow[$i + 1])) ? $flow[$i + 1] : null;
}

function status_badge(string $s): string
{
    return '<span class="badge st-' . e(strtolower($s)) . '">' . e(ucfirst(strtolower($s))) . '</span>';
}

function timeline(string $status): string
{
    if ($status === 'CANCELLED') {
        return '<p class="notice err-note">This order was cancelled.</p>';
    }
    $flow = order_flow();
    $at = (int)array_search($status, $flow, true);
    $h = '<ol class="timeline">';
    foreach ($flow as $i => $step) {
        $cls = $i < $at ? 'done' : ($i === $at ? 'current' : '');
        $h .= '<li class="' . $cls . '"><span class="dot">' . ($i <= $at ? '✓' : (string)($i + 1)) . '</span>' . e(ucfirst(strtolower($step))) . '</li>';
    }
    return $h . '</ol>';
}

/** Moves an order one step forward. Returns the new status or null if not possible. */
function advance_order(int $id): ?string
{
    $s = db()->prepare('SELECT status FROM orders WHERE order_id=?');
    $s->execute([$id]);
    $st = $s->fetchColumn();
    if (!$st) {
        return null;
    }
    $next = next_status((string)$st);
    if (!$next) {
        return null;
    }
    $u = db()->prepare('UPDATE orders SET status=? WHERE order_id=? AND status=?');
    $u->execute([$next, $id, $st]);
    return $u->rowCount() ? $next : null;
}

/** Cancels an order (only from allowed statuses) and returns the stock. */
function cancel_order(int $id, array $allowed, ?int $buyerId = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT status FROM orders WHERE order_id=?';
        $args = [$id];
        if ($buyerId !== null) {
            $sql .= ' AND buyer_id=?';
            $args[] = $buyerId;
        }
        $s = $pdo->prepare($sql . ' FOR UPDATE');
        $s->execute($args);
        $st = $s->fetchColumn();
        if ($st === false || !in_array($st, $allowed, true)) {
            $pdo->rollBack();
            return false;
        }
        $it = $pdo->prepare('SELECT produce_id, quantity FROM order_items WHERE order_id=?');
        $it->execute([$id]);
        $up = $pdo->prepare('UPDATE produce SET quantity = quantity + ? WHERE produce_id = ?');
        foreach ($it->fetchAll() as $r) {
            $up->execute([$r['quantity'], $r['produce_id']]);
        }
        $pdo->prepare("UPDATE orders SET status='CANCELLED' WHERE order_id=?")->execute([$id]);
        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
