<?php
require_once __DIR__ . '/includes/auth_middleware.php';

if (is_logged_in()) { redirect('index.php'); }

$page_title = 'Create account';
$active     = 'register';
$errors     = [];
$old        = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'role' => (($_GET['role'] ?? '') === 'farmer') ? 'farmer' : 'buyer', 'farm_name' => '', 'county' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) { $old[$k] = trim((string) ($_POST[$k] ?? '')); }
    $old['email'] = strtolower($old['email']);
    $password = (string) ($_POST['password'] ?? '');
    $msisdn   = normalize_msisdn($old['phone']);

    if (!verify_csrf())                                               { $errors['form'] = 'Your session expired. Please try again.'; }
    if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 60) { $errors['first_name'] = 'Enter your first name.'; }
    if ($old['last_name'] === ''  || mb_strlen($old['last_name']) > 60)  { $errors['last_name']  = 'Enter your last name.'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))            { $errors['email'] = 'Enter a valid email address.'; }
    if (!$msisdn)                                                      { $errors['phone'] = 'Enter a valid Safaricom number, e.g. 0712345678.'; }
    if (!in_array($old['role'], ['buyer', 'farmer'], true))           { $old['role'] = 'buyer'; }   // admin can never be self-registered
    if (strlen($password) < 8)                                        { $errors['password'] = 'Use at least 8 characters.'; }
    if ($old['role'] === 'farmer' && $old['farm_name'] === '')        { $errors['farm_name'] = 'Enter your farm or business name.'; }

    if (!$errors) {
        $dup = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $dup->execute([$old['email']]);
        if ($dup->fetchColumn()) { $errors['email'] = 'An account with this email already exists. Try logging in.'; }
    }

    if (!$errors) {
        $isFarmer = $old['role'] === 'farmer';
        db()->prepare('INSERT INTO users (role, first_name, last_name, email, phone, password_hash, farm_name, county) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$old['role'], $old['first_name'], $old['last_name'], $old['email'], $msisdn, password_hash($password, PASSWORD_DEFAULT),
                       $isFarmer ? $old['farm_name'] : null, $isFarmer && $old['county'] !== '' ? $old['county'] : null]);
        $id   = (int) db()->lastInsertId();
        $cart = $_SESSION['cart'] ?? [];
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['role']    = $old['role'];
        $_SESSION['cart']    = $cart;
        set_flash('success', 'Account created – welcome to ' . SITE_NAME . '!');
        redirect(home_for_role($old['role']));
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<p class="text-xs text-red-600 mt-1">' . e($errors[$k]) . '</p>' : '';

require __DIR__ . '/includes/header.php';
?>
<div class="py-12 px-4">
    <div class="max-w-xl mx-auto bg-white border border-gray-200 rounded-2xl p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">Create your account</h1>
        <p class="text-xs text-gray-500 text-center mb-6">Join as a buyer or registered farmer supplier</p>
        <?php if (!empty($errors['form'])): ?><div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3"><?= e($errors['form']) ?></div><?php endif; ?>

        <form method="post" class="space-y-4" novalidate>
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label" for="first_name">First Name</label><input class="input" id="first_name" name="first_name" required placeholder="John" value="<?= e($old['first_name']) ?>"><?= $err('first_name') ?></div>
                <div><label class="label" for="last_name">Last Name</label><input class="input" id="last_name" name="last_name" required placeholder="Doe" value="<?= e($old['last_name']) ?>"><?= $err('last_name') ?></div>
            </div>
            <div><label class="label" for="email">Email Address</label><input class="input" id="email" type="email" name="email" required autocomplete="email" placeholder="john@example.com" value="<?= e($old['email']) ?>"><?= $err('email') ?></div>
            <div><label class="label" for="phone">Phone Number (M-Pesa)</label><input class="input" id="phone" type="tel" name="phone" required placeholder="0712345678" value="<?= e($old['phone']) ?>"><?= $err('phone') ?></div>
            <div>
                <label class="label" for="role">Account Type</label>
                <select class="input" id="role" name="role" data-role-select>
                    <option value="buyer"  <?= $old['role'] === 'buyer'  ? 'selected' : '' ?>>Buyer (Household / Business)</option>
                    <option value="farmer" <?= $old['role'] === 'farmer' ? 'selected' : '' ?>>Farmer / Agricultural Supplier</option>
                </select>
            </div>
            <div data-farmer-fields class="<?= $old['role'] === 'farmer' ? '' : 'hidden' ?> grid grid-cols-2 gap-4 bg-brand-50 border border-brand-100 rounded-xl p-4">
                <div><label class="label" for="farm_name">Farm / business name</label><input class="input" id="farm_name" name="farm_name" placeholder="Nyeri Organic Farm" value="<?= e($old['farm_name']) ?>"><?= $err('farm_name') ?></div>
                <div><label class="label" for="county">County</label><input class="input" id="county" name="county" placeholder="Nyeri" value="<?= e($old['county']) ?>"></div>
            </div>
            <div><label class="label" for="password">Password</label><input class="input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"><?= $err('password') ?></div>
            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-lg transition text-sm mt-4">Create Account</button>
        </form>
        <p class="text-xs text-center text-gray-500 mt-6">Already have an account? <a href="<?= e(url('login.php')) ?>" class="text-brand-600 font-bold hover:underline">Log in</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
