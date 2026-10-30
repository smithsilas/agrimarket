<?php
/**
 * AgriMarket – application settings.
 * Override anything below with environment variables (Apache SetEnv, .env loader, Docker, etc.).
 */

define('SITE_NAME',    'AgriMarket');
define('SITE_TAGLINE', 'Fresh produce, straight from the farm');
define('SUPPORT_EMAIL', 'support@agrimarket.co.ke');
define('SITE_LOCATION', 'Nairobi, Kenya');

/* ---- Money --------------------------------------------------------- */
define('CURRENCY',      'KSh');
define('DELIVERY_FEE',  150.00);   // flat delivery estimate per order

/* ---- Paths & URLs -------------------------------------------------- */
// URL path where the app lives. '/agrimarket' for http://localhost/agrimarket, '' if it is the web root.
define('BASE_URL',   rtrim(getenv('AGRIMARKET_BASE_URL') !== false ? getenv('AGRIMARKET_BASE_URL') : '/agrimarket', '/'));
define('BASE_PATH',  dirname(__DIR__));
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOAD_DIR', BASE_PATH . '/assets/images/uploads/');
define('MAX_UPLOAD_BYTES', 3 * 1024 * 1024);   // 3 MB per produce photo

/* ---- Catalogue ----------------------------------------------------- */
define('PER_PAGE',        6);
define('PRICE_CAP',       5000);    // top of the "Max Price" slider
define('MAX_CART_QTY',    500);

/* ---- Database ------------------------------------------------------ */
define('DB_HOST',    getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT') ?: '3306');
define('DB_NAME',    getenv('DB_NAME') ?: 'agrimarket');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/* ---- M-Pesa (Safaricom Daraja) -------------------------------------
 * MPESA_SIMULATE = true  → no real request is sent; the STK push is faked
 *                          so you can demo the whole flow locally.
 * MPESA_SIMULATE = false → real Daraja call; fill in the keys below.
 */
define('MPESA_SIMULATE',        (getenv('MPESA_SIMULATE') ?: '1') === '1');
define('MPESA_ENV',             getenv('MPESA_ENV') ?: 'sandbox');   // sandbox | production
define('MPESA_CONSUMER_KEY',    getenv('MPESA_CONSUMER_KEY') ?: '');
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: '');
define('MPESA_SHORTCODE',       getenv('MPESA_SHORTCODE') ?: '174379');
define('MPESA_PASSKEY',         getenv('MPESA_PASSKEY') ?: '');
define('MPESA_CALLBACK_URL',    getenv('MPESA_CALLBACK_URL') ?: 'https://example.com/agrimarket/api/checkout.php?action=callback');

/* ---- Order lifecycle ----------------------------------------------- */
const ORDER_STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

date_default_timezone_set('Africa/Nairobi');
