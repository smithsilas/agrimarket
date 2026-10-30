<?php
require __DIR__ . '/functions.php';
$title = 'Farmer profiles';
$desc  = 'Meet the verified farmers who grow your food.';
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

$sql = "SELECT u.user_id, u.full_name, u.location, u.bio, u.created_at,
               COUNT(p.produce_id) AS listings
          FROM users u
          LEFT JOIN produce p ON p.farmer_id=u.user_id AND p.status='APPROVED' AND p.quantity>0
         WHERE u.role='FARMER' AND u.status='APPROVED'";
$args = [];
if ($q !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.location LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $args = [$like, $like];
}
$sql .= ' GROUP BY u.user_id ORDER BY listings DESC, u.full_name LIMIT 60';
$s = db()->prepare($sql);
$s->execute($args);
$farmers = $s->fetchAll();

require __DIR__ . '/header.php';
?>
<section class="page-hero slim">
    <div class="container">
        <h1>Farmer profiles</h1>
        <p class="lead">Every farmer here has been verified by our administrators.</p>
        <form class="searchbar" method="get" role="search">
            <label class="visually-hidden" for="fq">Search farmers</label>
            <input id="fq" name="q" type="search" placeholder="Search by name or location" value="<?= e($q) ?>">
            <button class="btn btn-accent">Search</button>
        </form>
    </div>
</section>
<section class="section container">
    <?php if (!$farmers): ?>
        <div class="empty"><h3>No farmers found</h3><p>Try another search.</p><a class="btn" href="<?= e(url('farmers.php')) ?>">Show all</a></div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($farmers as $f): ?>
                <a class="card farmer-card" href="<?= e(url('farmer_profile.php?id=' . (int)$f['user_id'])) ?>">
                    <span class="avatar"><?= e(initials($f['full_name'])) ?></span>
                    <div>
                        <h3><?= e($f['full_name']) ?></h3>
                        <p class="muted small">📍 <?= e($f['location'] ?: 'Location not set') ?> · <?= (int)$f['listings'] ?> live listing<?= (int)$f['listings'] === 1 ? '' : 's' ?></p>
                        <?php if ($f['bio']): ?><p class="small"><?= e(mb_strimwidth($f['bio'], 0, 120, '…')) ?></p><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
