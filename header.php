<?php
require_once __DIR__ . '/functions.php';
$u      = me();
$title  = $title ?? APP_NAME;
$desc   = $desc ?? 'AgriMarket connects smallholder farmers directly with households, hotels, schools and exporters.';
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$alias  = ['farmer_profile.php' => 'farmers.php', 'product.php' => 'catalogue.php'];
$active = $alias[$script] ?? $script;
$links  = [
    'index.php'     => 'Home',
    'catalogue.php' => 'Produce',
    'farmers.php'   => 'Farmers',
    'about.php'     => 'About us',
    'contact.php'   => 'Contact',
];
$cartCount = ($u && $u['role'] === 'BUYER') ? cart_count() : 0;
$flashes = ['success' => flash('success'), 'error' => flash('error'), 'info' => flash('info')];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title === APP_NAME ? APP_NAME : $title . ' | ' . APP_NAME) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="theme-color" content="#1f6b3a">
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <script defer src="<?= e(url('assets/app.js')) ?>"></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(url('assets/logo.svg')) ?>" alt="" width="34" height="34"><span>Agri<b>Market</b></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <nav id="nav" class="site-nav" aria-label="Main">
            <ul class="nav-links">
                <?php foreach ($links as $file => $label): ?>
                    <li><a href="<?= e(url($file)) ?>" <?= $active === $file ? 'class="active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="nav-actions">
                <?php if ($u): ?>
                    <?php if ($u['role'] === 'BUYER'): ?>
                        <a class="cart-link" href="<?= e(url('cart.php')) ?>">🛒 Cart<?= $cartCount ? '<span class="count">' . $cartCount . '</span>' : '' ?></a>
                        <a href="<?= e(url('orders.php')) ?>" <?= $script === 'orders.php' ? 'class="active"' : '' ?>>My orders</a>
                    <?php elseif ($u['role'] === 'FARMER'): ?>
                        <a href="<?= e(url('farmer.php')) ?>" <?= $script === 'farmer.php' ? 'class="active"' : '' ?>>Dashboard</a>
                    <?php elseif ($u['role'] === 'ADMIN'): ?>
                        <a href="<?= e(url('admin.php')) ?>" <?= $script === 'admin.php' ? 'class="active"' : '' ?>>Admin</a>
                    <?php endif; ?>
                    <a class="user-chip" href="<?= e(url('profile.php')) ?>" title="My profile"><span class="avatar sm"><?= e(initials($u['full_name'])) ?></span><?= e(strtok($u['full_name'], ' ')) ?></a>
                    <form method="post" action="<?= e(url('logout.php')) ?>">
                        <?= field() ?><button class="btn btn-ghost btn-sm">Log out</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>" <?= $script === 'login.php' ? 'class="active"' : '' ?>>Log in</a>
                    <a class="btn btn-sm" href="<?= e(url('register.php')) ?>">Join free</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
<main id="main">
<?php foreach (['success' => 'ok', 'error' => 'err', 'info' => 'info'] as $k => $cls): ?>
    <?php if ($flashes[$k]): ?>
        <div class="container"><div class="alert <?= $cls ?>" role="<?= $k === 'error' ? 'alert' : 'status' ?>"><span><?= e($flashes[$k]) ?></span><button type="button" class="close" aria-label="Dismiss">×</button></div></div>
    <?php endif; ?>
<?php endforeach; ?>
