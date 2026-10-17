<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($uri === '/install.php') {
    require __DIR__ . '/install.php';
    exit;
}

if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: /install.php');
    exit;
}
require_once __DIR__ . '/config.php';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Rate limiting guard
if (function_exists('db')) {
    try {
        $rl_enabled = '0';
        $rl_requests = '60';
        
        $settings_rows = db()->fetchAll("SELECT `key`, value FROM settings WHERE `key` IN ('security_rate_limit_enabled', 'security_rate_limit_requests')");
        foreach ($settings_rows as $row) {
            if ($row['key'] === 'security_rate_limit_enabled') $rl_enabled = $row['value'];
            if ($row['key'] === 'security_rate_limit_requests') $rl_requests = $row['value'];
        }
        
        if ($rl_enabled === '1' && !(function_exists('isAdmin') && isAdmin())) {
            $now = time();
            if (!isset($_SESSION['request_times'])) {
                $_SESSION['request_times'] = [];
            }
            $_SESSION['request_times'] = array_filter($_SESSION['request_times'], fn($t) => $t > $now - 60);
            
            if (count($_SESSION['request_times']) >= (int)$rl_requests) {
                http_response_code(429);
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>429 Too Many Requests - HelloAni</title>
                    <script src="https://cdn.tailwindcss.com"></script>
                    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
                    <style>
                        body {
                            font-family: 'Outfit', sans-serif;
                        }
                    </style>
                </head>
                <body class="bg-slate-950 text-white min-h-screen flex items-center justify-center relative overflow-hidden">
                    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl"></div>
                    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>

                    <div class="relative z-10 text-center max-w-lg px-6">
                        <div class="w-24 h-24 bg-gradient-to-tr from-yellow-500 to-red-600 rounded-2xl flex items-center justify-center mx-auto mb-8 shadow-lg shadow-red-500/20 animate-pulse">
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h1 class="text-7xl font-extrabold tracking-tight bg-gradient-to-r from-yellow-400 via-red-400 to-pink-400 bg-clip-text text-transparent mb-4">429</h1>
                        <h2 class="text-2xl font-bold mb-4 text-slate-200">Too Many Requests</h2>
                        <p class="text-slate-400 mb-8 text-sm sm:text-base leading-relaxed">
                            You have exceeded the request rate limit. Please wait a minute and try again.
                        </p>
                    </div>
                </body>
                </html>
                <?php
                exit;
            }
            $_SESSION['request_times'][] = $now;
        }
    } catch (Exception $e) {}
}

// Sitemap + Manifest
if ($uri === '/sitemap.xml') { require __DIR__ . '/sitemap.php'; exit; }
if ($uri === '/manifest.json') { require __DIR__ . '/manifest.php'; exit; }

// API Routes
if (str_starts_with($uri, '/api/')) {
    header('Content-Type: application/json');
    $apiRoute = substr($uri, 5);
    $endpoint = explode('/', $apiRoute)[0];
    if (str_ends_with($endpoint, '.php')) {
        $endpoint = substr($endpoint, 0, -4);
    }
    $apiFile = __DIR__ . '/api/' . $endpoint . '.php';
    if (file_exists($apiFile)) { require $apiFile; exit; }
    jsonResponse(['error' => 'API not found'], 404);
}

// Admin Routes
if (str_starts_with($uri, '/admin')) {
    $adminRoute = substr($uri, 6) ?: '/';
    $adminRoute = rtrim($adminRoute, '/') ?: '/';
    $adminMap = [
        '/' => 'admin/index.php', '/login' => 'admin/login.php',
        '/anime' => 'admin/anime.php', '/episodes' => 'admin/episodes.php',
        '/users' => 'admin/users.php', '/comments' => 'admin/comments.php',
        '/notifications' => 'admin/notifications.php', '/reports' => 'admin/reports.php',
        '/messages' => 'admin/messages.php', '/requests' => 'admin/requests.php',
        '/settings' => 'admin/settings.php', '/ads' => 'admin/ads.php',
        '/analytics' => 'admin/analytics.php', '/api-keys' => 'admin/api-keys.php',
        '/languages' => 'admin/languages.php', '/networks' => 'admin/networks.php',
        '/banner' => 'admin/banner.php', '/promos' => 'admin/promos.php',
        '/security' => 'admin/security.php', '/roles' => 'admin/roles.php',
    ];
    if (isset($adminMap[$adminRoute])) { require __DIR__ . '/' . $adminMap[$adminRoute]; exit; }
    http_response_code(404); echo '404 - Admin page not found'; exit;
}

// Auth Routes
$authMap = [
    '/login' => 'auth/login.php', '/register' => 'auth/register.php',
    '/forgot-password' => 'auth/forgot.php', '/verify' => 'auth/verify.php',
    '/logout' => 'auth/logout.php',
];
if (isset($authMap[$uri])) { require __DIR__ . '/' . $authMap[$uri]; exit; }

// Page Routes
$pageMap = [
    '/' => 'pages/home.php', '/browse' => 'pages/browse.php',
    '/search' => 'pages/search.php', '/schedule' => 'pages/schedule.php',
    '/profile' => 'pages/profile.php', '/watchlist' => 'pages/watchlist.php',
    '/about' => 'pages/about.php', '/contact' => 'pages/contact.php',
    '/dmca' => 'pages/dmca.php', '/privacy' => 'pages/privacy.php',
    '/terms' => 'pages/terms.php', '/faq' => 'pages/faq.php',
    '/request' => 'pages/request.php',
];
if (isset($pageMap[$uri])) { require __DIR__ . '/' . $pageMap[$uri]; exit; }

// Dynamic: /anime/slug
if (preg_match('#^/anime/([a-z0-9\-]+)$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/pages/anime.php'; exit;
}

// Dynamic: /watch/slug/ep
if (preg_match('#^/watch/([a-z0-9\-]+)/(\d+)$#', $uri, $m)) {
    $_GET['slug'] = $m[1]; $_GET['ep'] = $m[2];
    require __DIR__ . '/pages/watch.php'; exit;
}

// Dynamic: /watch/slug
if (preg_match('#^/watch/([a-z0-9\-]+)$#', $uri, $m)) {
    $_GET['slug'] = $m[1]; $_GET['ep'] = 1;
    require __DIR__ . '/pages/watch.php'; exit;
}

http_response_code(404);
$pageTitle = '404 - Not Found';
require __DIR__ . '/templates/header.php';
echo '<div class="min-h-screen flex items-center justify-center"><div class="text-center"><h1 class="text-8xl font-black text-purple-500">404</h1><p class="text-2xl text-gray-400 mt-4">Page not found</p><a href="/" class="mt-6 inline-block bg-purple-600 hover:bg-purple-700 px-8 py-3 rounded-xl text-white transition">Go Home</a></div></div>';
require __DIR__ . '/templates/footer.php';
