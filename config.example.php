<?php
session_start();
header("Referrer-Policy: no-referrer");
date_default_timezone_set('Asia/Kolkata');

// App Config
define('APP_NAME', 'HelloAni');
define('APP_TAGLINE', 'Your World of Anime');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'helloani');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security
define('CSRF_TOKEN_NAME', '_csrf_token');
define('BCRYPT_COST', 12);
define('SESSION_LIFETIME', 86400);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// Uploads
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Admin
define('ADMIN_EMAIL', 'admin@helloani.com');
define('ADMIN_PATH', '/admin');

// Pagination
define('ITEMS_PER_PAGE', 24);
define('COMMENTS_PER_PAGE', 20);

// Demo Mode
define('DEMO_MODE', true);
