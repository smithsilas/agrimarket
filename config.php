<?php
declare(strict_types=1);

/**
 * AgriMarket configuration.
 * Values come from environment variables or an optional local .env file
 * (never commit .env — see .gitignore and .env.example).
 */
(function (): void {
    $file = __DIR__ . '/.env';
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (getenv($k) === false) {
            putenv($k . '=' . trim($v, "\"'"));
        }
    }
})();

function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

define('APP_NAME', 'AgriMarket');
define('APP_ENV', env('APP_ENV', 'development'));          // development | production
define('BASE_URL', env('APP_BASE_URL', '/agrimarket'));    // '' when hosted at the domain root
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'agri_db'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));

error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli') {
    // Security headers (defence in depth against XSS, clickjacking, sniffing).
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https: http:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    if (IS_HTTPS) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        session_name('agrimarket_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => IS_HTTPS,
            'samesite' => 'Lax',
        ]);
        session_start();
        // Idle timeout: 2 hours.
        if (isset($_SESSION['_last']) && time() - (int)$_SESSION['_last'] > 7200) {
            $_SESSION = [];
        }
        $_SESSION['_last'] = time();
    }
}
