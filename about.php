<?php
require __DIR__ . '/functions.php';
$title = 'About us';
$desc  = 'Learn how AgriMarket links smallholder farmers directly to buyers.';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">About AgriMarket</span>
        <h1>Fair prices for farmers. Fresh food for everyone.</h1>
        <p class="lead">We are an agribusiness start-up that connects smallholder farmers directly with households, hotels, schools and exporters.</p>
    </div>
</section>
<section class="section container prose-grid">
    <div>
        <h2>Our mission</h2>
        <p>Too often, farmers lose a large share of their income to middlemen while buyers pay more for produce that has travelled further and sat longer. AgriMarket shortens that chain: farmers list what they grow, buyers order what they need, and an administrator verifies every farmer and listing before it goes live.</p>
        <h2>What makes us different</h2>
        <ul class="ticks">
            <li><strong>Verified sellers</strong> — farmer accounts and listings are approved by an administrator.</li>
            <li><strong>Transparent pricing</strong> — the farmer sets the price; you see it before you order.</li>
            <li><strong>Simple ordering</strong> — search, add to cart, check out and download a receipt.</li>
            <li><strong>Order tracking</strong> — Placed → Confirmed → Dispatched → Delivered.</li>
        </ul>
    </div>
    <aside class="panel">
        <h3>Who we serve</h3>
        <p>🏠 <strong>Households</strong> — weekly fresh produce.</p>
        <p>🏨 <strong>Hotels &amp; restaurants</strong> — reliable bulk supply.</p>
        <p>🏫 <strong>Schools</strong> — nutritious food for learners.</p>
        <p>🚢 <strong>Exporters</strong> — consistent, traceable sourcing.</p>
        <a class="btn btn-block" href="<?= e(url('register.php')) ?>">Create a free account</a>
    </aside>
</section>
<?php require __DIR__ . '/footer.php'; ?>
