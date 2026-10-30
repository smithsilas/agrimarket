<?php
require __DIR__ . '/functions.php';
$title = 'Contact us';
$desc  = 'Questions about AgriMarket? Send us a message.';
$errors = [];
$f = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$u = me();
if ($u) {
    $f['name'] = $u['full_name'];
    $f['email'] = $u['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_token();
    $f = ['name' => post('name'), 'email' => strtolower(post('email')), 'subject' => post('subject'), 'message' => post('message')];

    if (post('website') !== '') {                       // honeypot: bots fill hidden fields
        go('contact.php');
    }
    $_SESSION['contact_log'] = array_values(array_filter($_SESSION['contact_log'] ?? [], static fn($t) => $t > time() - 3600));
    if (count($_SESSION['contact_log']) >= 5) {
        $errors[] = 'You have sent several messages recently. Please try again later.';
    }
    if (mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100) { $errors[] = 'Enter your name (2–100 characters).'; }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 190) { $errors[] = 'Enter a valid email address.'; }
    if (mb_strlen($f['subject']) < 3 || mb_strlen($f['subject']) > 150) { $errors[] = 'Enter a subject (3–150 characters).'; }
    if (mb_strlen($f['message']) < 10 || mb_strlen($f['message']) > 2000) { $errors[] = 'Your message must be 10–2000 characters.'; }

    if (!$errors) {
        db()->prepare('INSERT INTO contact_messages(name,email,subject,message) VALUES(?,?,?,?)')
            ->execute([$f['name'], $f['email'], $f['subject'], $f['message']]);
        $_SESSION['contact_log'][] = time();
        flash('success', 'Thank you! Your message has been received and we will reply by email.');
        go('contact.php');
    }
}
require __DIR__ . '/header.php';
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Contact</span>
        <h1>We'd love to hear from you</h1>
        <p class="lead">Questions about ordering, selling or partnerships? Send a message and we'll get back to you.</p>
    </div>
</section>
<section class="section container contact-grid">
    <form class="form-card" method="post" data-validate novalidate>
        <?= field() ?>
        <h2>Send a message</h2>
        <?php foreach ($errors as $x): ?><p class="alert err" role="alert"><?= e($x) ?></p><?php endforeach; ?>
        <div class="two">
            <div class="field"><label for="name">Your name</label><input id="name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="<?= e($f['name']) ?>"></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($f['email']) ?>"></div>
        </div>
        <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" required minlength="3" maxlength="150" value="<?= e($f['subject']) ?>"></div>
        <div class="field"><label for="message">Message</label><textarea id="message" name="message" rows="6" required minlength="10" maxlength="2000"><?= e($f['message']) ?></textarea></div>
        <div class="hp" aria-hidden="true"><label>Leave blank<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn btn-lg">Send message</button>
    </form>
    <aside class="panel">
        <h3>Other ways to reach us</h3>
        <p><strong>📧 Email</strong><br><span class="muted">support@agrimarket.example</span></p>
        <p><strong>📞 Phone</strong><br><span class="muted">+254 700 000 000</span></p>
        <p><strong>🕒 Hours</strong><br><span class="muted">Mon–Fri, 8:00am – 5:00pm (EAT)</span></p>
        <p class="muted small">Replace the details above with your organisation's real contact information.</p>
    </aside>
</section>
<?php require __DIR__ . '/footer.php'; ?>
