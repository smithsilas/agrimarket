<?php
require_once __DIR__ . '/includes/auth_middleware.php';

if (is_logged_in()) { redirect('index.php'); }

$page_title = 'Login';
$active     = 'login';
$error      = '';
$email      = '';
$next       = safe_next($_GET['next'] ?? $_POST['next'] ?? null, '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $now   = time();
    $_SESSION['login_fail'] = array_values(array_filter($_SESSION['login_fail'] ?? [], fn($t) => $now - $t < 300));

    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (count($_SESSION['login_fail']) >= 5) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $st = db()->prepare('SELECT id, role, password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && password_verify((string) ($_POST['password'] ?? ''), $row['password_hash'])) {
            $cart = $_SESSION['cart'] ?? [];
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['id'];
            $_SESSION['role']    = $row['role'];
            $_SESSION['cart']    = $cart;               // keep the guest cart
            $_SESSION['login_fail'] = [];
            set_flash('success', 'Welcome back!');
            redirect($next !== '' ? $next : home_for_role($row['role']));
        }
        $_SESSION['login_fail'][] = $now;
        $error = 'Incorrect email or password.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="py-12 px-4">
    <div class="max-w-4xl mx-auto grid md:grid-cols-2 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="hidden md:block relative">
            <img src="<?= e(asset('images/hero.svg')) ?>" alt="" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-brand-900/80 to-transparent flex items-end p-8">
                <p class="text-white font-bold text-xl leading-snug">Good food starts with good farmers. Welcome back.</p>
            </div>
        </div>
        <div class="p-8">
            <h1 class="text-2xl font-bold text-gray-900 text-center mb-6">Login to <?= e(SITE_NAME) ?></h1>
            <?php if ($error): ?><div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="space-y-4" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div>
                    <label class="label" for="email">Email Address</label>
                    <input class="input" id="email" type="email" name="email" required autocomplete="email" placeholder="john@example.com" value="<?= e($email) ?>">
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input class="input" id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">Demo: buyer@example.com / Password123!</span>
                    <a href="mailto:<?= e(SUPPORT_EMAIL) ?>?subject=Password%20reset" class="text-brand-600 font-medium hover:underline">Forgot Password?</a>
                </div>
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-lg transition text-sm">Login</button>
            </form>
            <p class="text-xs text-center text-gray-500 mt-6">Don't have an account? <a href="<?= e(url('register.php')) ?>" class="text-brand-600 font-bold hover:underline">Create an account</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
