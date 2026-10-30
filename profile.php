<?php
require __DIR__ . '/functions.php';
$u = login_required();
$title = 'My profile';
$errors = [];
$perr = [];
$f = ['name' => $u['full_name'], 'phone' => (string)$u['phone'], 'location' => (string)$u['location'], 'bio' => (string)$u['bio']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    if (post('action') === 'details') {
        $f = ['name' => post('full_name'), 'phone' => post('phone'), 'location' => post('location'), 'bio' => post('bio')];
        if (mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100) { $errors[] = 'Enter your full name (2–100 characters).'; }
        if ($f['phone'] !== '' && !valid_phone($f['phone'])) { $errors[] = 'Enter a valid phone number.'; }
        if ($u['role'] === 'FARMER' && ($f['phone'] === '' || mb_strlen($f['location']) < 2)) { $errors[] = 'Farmers must keep a phone number and location on their profile.'; }
        if (mb_strlen($f['location']) > 150) { $errors[] = 'Location is too long.'; }
        if (mb_strlen($f['bio']) > 600) { $errors[] = 'About text must be 600 characters or fewer.'; }
        if (!$errors) {
            db()->prepare('UPDATE users SET full_name=?, phone=?, location=?, bio=? WHERE user_id=?')
                ->execute([$f['name'], $f['phone'] !== '' ? norm_phone($f['phone']) : null, $f['location'] ?: null, $u['role'] === 'FARMER' ? ($f['bio'] ?: null) : $u['bio'], $u['user_id']]);
            flash('success', 'Profile updated.');
            go('profile.php');
        }
    } elseif (post('action') === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        $cf = (string)($_POST['confirm'] ?? '');
        $s = db()->prepare('SELECT password_hash FROM users WHERE user_id=?');
        $s->execute([$u['user_id']]);
        if (!password_verify($cur, (string)$s->fetchColumn())) { $perr[] = 'Your current password is incorrect.'; }
        array_push($perr, ...password_errors($new, $u['email']));
        if ($new !== $cf) { $perr[] = 'New passwords do not match.'; }
        if (!$perr) {
            db()->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['user_id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
            go('profile.php');
        }
    }
}
require __DIR__ . '/header.php';
?>
<section class="page-hero slim"><div class="container"><h1>My profile</h1><p class="lead"><?= e($u['email']) ?> · <?= e(ucfirst(strtolower($u['role']))) ?> account</p></div></section>
<section class="section container two-col">
    <form class="form-card" method="post" data-validate novalidate>
        <?= field() ?><input type="hidden" name="action" value="details">
        <h2>Account details</h2>
        <?php foreach ($errors as $x): ?><p class="alert err" role="alert"><?= e($x) ?></p><?php endforeach; ?>
        <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required minlength="2" maxlength="100" value="<?= e($f['name']) ?>"></div>
        <div class="two">
            <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" type="tel" pattern="\+?[0-9\s\-]{9,18}" value="<?= e($f['phone']) ?>"></div>
            <div class="field"><label for="location">Location</label><input id="location" name="location" maxlength="150" value="<?= e($f['location']) ?>"></div>
        </div>
        <?php if ($u['role'] === 'FARMER'): ?>
            <div class="field"><label for="bio">About your farm <span class="muted">(shown on your public profile)</span></label><textarea id="bio" name="bio" rows="4" maxlength="600"><?= e($f['bio']) ?></textarea></div>
        <?php endif; ?>
        <button class="btn">Save changes</button>
    </form>
    <form class="form-card" method="post" data-validate novalidate>
        <?= field() ?><input type="hidden" name="action" value="password">
        <h2>Change password</h2>
        <?php foreach ($perr as $x): ?><p class="alert err" role="alert"><?= e($x) ?></p><?php endforeach; ?>
        <div class="field"><label for="current">Current password</label><input id="current" type="password" name="current" required autocomplete="current-password"></div>
        <div class="field"><label for="password">New password</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" data-strength data-password></div>
        <div class="field"><label for="confirm">Confirm new password</label><input id="confirm" type="password" name="confirm" required minlength="8" autocomplete="new-password" data-match="password"></div>
        <button class="btn">Update password</button>
    </form>
</section>
<?php require __DIR__ . '/footer.php'; ?>
