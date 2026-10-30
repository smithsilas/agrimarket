<?php
require __DIR__ . '/functions.php';
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare("SELECT user_id, full_name, location, bio, created_at FROM users WHERE user_id=? AND role='FARMER' AND status='APPROVED'");
$s->execute([$id]);
$f = $s->fetch();
if (!$f) {
    abort_page(404, 'Farmer not found', 'This farmer profile does not exist or is not available.');
}
$l = db()->prepare(
    "SELECT p.*, c.category_name FROM produce p
       JOIN categories c ON c.category_id=p.category_id
      WHERE p.farmer_id=? AND p.status='APPROVED' AND p.quantity>0
      ORDER BY p.created_at DESC"
);
$l->execute([$id]);
$items = $l->fetchAll();
$title = $f['full_name'];
$desc  = 'Produce listed by ' . $f['full_name'] . ' on AgriMarket.';
require __DIR__ . '/header.php';
?>
<section class="page-hero slim">
    <div class="container profile-head">
        <span class="avatar xl"><?= e(initials($f['full_name'])) ?></span>
        <div>
            <span class="eyebrow">Verified farmer</span>
            <h1><?= e($f['full_name']) ?></h1>
            <p class="lead">📍 <?= e($f['location'] ?: 'Kenya') ?> · Member since <?= e(date('M Y', strtotime($f['created_at']))) ?></p>
        </div>
    </div>
</section>
<section class="section container">
    <?php if ($f['bio']): ?><div class="panel"><h2>About the farm</h2><p><?= nl2br(e($f['bio'])) ?></p></div><?php endif; ?>
    <div class="section-head spaced"><div><h2>Available produce (<?= count($items) ?>)</h2></div><a class="more" href="<?= e(url('farmers.php')) ?>">← All farmers</a></div>
    <?php if (!$items): ?>
        <div class="empty"><p>No produce is currently available from this farmer.</p></div>
    <?php else: ?>
        <div class="grid grid-3"><?php foreach ($items as $p) { render_card($p); } ?></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
