</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand light" href="<?= e(url('index.php')) ?>"><img src="<?= e(url('assets/logo.svg')) ?>" alt="" width="30" height="30"><span>Agri<b>Market</b></span></a>
            <p>Connecting smallholder farmers directly with households, hotels, schools and exporters.</p>
        </div>
        <div>
            <h4>Marketplace</h4>
            <a href="<?= e(url('catalogue.php')) ?>">Browse produce</a>
            <a href="<?= e(url('farmers.php')) ?>">Meet the farmers</a>
            <a href="<?= e(url('register.php?role=FARMER')) ?>">Sell your produce</a>
        </div>
        <div>
            <h4>Company</h4>
            <a href="<?= e(url('about.php')) ?>">About us</a>
            <a href="<?= e(url('contact.php')) ?>">Contact us</a>
            <a href="<?= e(url('login.php')) ?>">Log in</a>
        </div>
        <small>© <?= date('Y') ?> AgriMarket · Built as a TVET CDACC practical assessment project</small>
    </div>
</footer>
</body>
</html>
