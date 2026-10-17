<?php
// Prevent reinstalling
if (file_exists(__DIR__ . '/installed.lock')) {
    header("Location: /");
    exit;
}

// Env check helper
function checkEnv() {
    $requirements = [
        'php_version' => [
            'name' => 'PHP Version (>= 8.0)',
            'met' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'current' => PHP_VERSION
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL Extension',
            'met' => extension_loaded('pdo_mysql'),
            'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled'
        ],
        'gd' => [
            'name' => 'GD Library Extension',
            'met' => extension_loaded('gd'),
            'current' => extension_loaded('gd') ? 'Enabled' : 'Disabled'
        ],
        'openssl' => [
            'name' => 'OpenSSL Extension',
            'met' => extension_loaded('openssl'),
            'current' => extension_loaded('openssl') ? 'Enabled' : 'Disabled'
        ],
        'session' => [
            'name' => 'Session Support',
            'met' => extension_loaded('session'),
            'current' => extension_loaded('session') ? 'Enabled' : 'Disabled'
        ],
        'config_writable' => [
            'name' => 'config.php Writable',
            'met' => is_writable(__DIR__ . '/config.php') || is_writable(__DIR__),
            'current' => (is_writable(__DIR__ . '/config.php') || is_writable(__DIR__)) ? 'Writable' : 'Not Writable'
        ],
        'uploads_writable' => [
            'name' => 'uploads/ Writable',
            'met' => is_writable(__DIR__ . '/uploads') || is_writable(__DIR__),
            'current' => (is_writable(__DIR__ . '/uploads') || is_writable(__DIR__)) ? 'Writable' : 'Not Writable'
        ]
    ];
    
    $allMet = true;
    foreach ($requirements as $req) {
        if (!$req['met']) {
            $allMet = false;
        }
    }
    
    return [$requirements, $allMet];
}

list($requirements, $requirementsMet) = checkEnv();

// AJAX Database Connection Test
if (isset($_GET['action']) && $_GET['action'] === 'test_db') {
    header('Content-Type: application/json');
    $host = $_POST['db_host'] ?? '';
    $name = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    
    try {
        // Try connecting to the host
        $pdo = new PDO("mysql:host=$host", $user, $pass, [
            PDO::ATTR_TIMEOUT => 4,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo json_encode(['success' => true, 'message' => 'Successfully connected to the MySQL server.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Form Submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    if (!$requirementsMet) {
        $error = "Cannot install. Some environment requirements are not met.";
    } else {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbName = trim($_POST['db_name'] ?? 'helloani');
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';
        
        $adminUser = trim($_POST['admin_user'] ?? 'admin');
        $adminEmail = trim($_POST['admin_email'] ?? 'admin@helloani.com');
        $adminPass = $_POST['admin_pass'] ?? '';
        
        $siteName = trim($_POST['site_name'] ?? 'HelloAni');
        $siteTagline = trim($_POST['site_tagline'] ?? 'Your World of Anime');
        $siteUrl = trim($_POST['site_url'] ?? 'http://localhost:8000');
        
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = trim($_POST['smtp_port'] ?? '587');
        $smtpUser = trim($_POST['smtp_user'] ?? '');
        $smtpPass = $_POST['smtp_pass'] ?? '';
        
        if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($adminUser) || empty($adminEmail) || empty($adminPass) || empty($siteName) || empty($siteUrl)) {
            $error = "All required fields must be filled.";
        } else {
            try {
                // Connect to server - try connecting directly to the database first (essential for shared/free hosts like InfinityFree)
                try {
                    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch (PDOException $e) {
                    // Fallback for localhost where database may not exist yet
                    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `$dbName`");
                }
                
                // Import schema
                if (!file_exists(__DIR__ . '/database/schema.sql')) {
                    throw new Exception("Schema file not found at database/schema.sql.");
                }
                
                $sql = file_get_contents(__DIR__ . '/database/schema.sql');
                // Strip out hardcoded CREATE DATABASE / USE statements from the SQL file so it runs inside the connected database
                $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS.*?;/is', '', $sql);
                $sql = preg_replace('/USE\s+.*?;/is', '', $sql);
                
                $pdo->exec($sql);
                
                // Override Admin account
                $pdo->exec("DELETE FROM users WHERE username = 'admin' OR email = 'admin@helloani.com' OR role = 'superadmin'");
                $hashedPass = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, email_verified, created_at) VALUES (?, ?, ?, 'superadmin', 1, NOW())");
                $stmt->execute([$adminUser, $adminEmail, $hashedPass]);
                
                // Update settings tables
                $settingsUpdates = [
                    'site_name' => $siteName,
                    'site_tagline' => $siteTagline,
                    'smtp_host' => $smtpHost,
                    'smtp_port' => $smtpPort,
                    'smtp_user' => $smtpUser,
                    'smtp_pass' => $smtpPass
                ];
                
                $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE `key` = ?");
                foreach ($settingsUpdates as $key => $val) {
                    $stmt->execute([$val, $key]);
                }
                
                // Generate config.php
                $configContent = "<?php
session_start();
header(\"Referrer-Policy: no-referrer\");

// Redirect to installer if not installed
\$requestUri = parse_url(\$_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
\$requestUri = rtrim(\$requestUri, '/') ?: '/';
if (!file_exists(__DIR__ . '/installed.lock') && \$requestUri !== '/install.php') {
    header('Location: /install.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');

// App Config
define('APP_NAME', " . var_export($siteName, true) . ");
define('APP_TAGLINE', " . var_export($siteTagline, true) . ");
define('APP_VERSION', '1.0.0');
define('APP_URL', " . var_export($siteUrl, true) . ");

// Database
define('DB_HOST', " . var_export($dbHost, true) . ");
define('DB_NAME', " . var_export($dbName, true) . ");
define('DB_USER', " . var_export($dbUser, true) . ");
define('DB_PASS', " . var_export($dbPass, true) . ");
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
define('ADMIN_EMAIL', " . var_export($adminEmail, true) . ");
define('ADMIN_PATH', '/admin');

// Pagination
define('ITEMS_PER_PAGE', 24);
define('COMMENTS_PER_PAGE', 20);

// Demo Mode
define('DEMO_MODE', false);
";
                
                if (file_put_contents(__DIR__ . '/config.php', $configContent) === false) {
                    throw new Exception("Unable to write to config.php. Check permissions.");
                }
                
                // Run migrations silently
                ob_start();
                try {
                    require __DIR__ . '/migrate.php';
                    require __DIR__ . '/migrate_languages_networks.php';
                    require __DIR__ . '/database/migrate_rbac_security.php';
                } catch (Exception $migrationEx) {
                    // Ignore migration exceptions
                }
                ob_end_clean();
                
                // Make sure uploads folders exist
                $dirs = ['posters', 'banners', 'avatars', 'subtitles'];
                foreach ($dirs as $d) {
                    $dirPath = __DIR__ . '/uploads/' . $d;
                    if (!is_dir($dirPath)) {
                        mkdir($dirPath, 0755, true);
                    }
                }
                
                // Write lock file
                file_put_contents(__DIR__ . '/installed.lock', 'Installed on: ' . date('Y-m-d H:i:s'));
                
                $success = true;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$autodetectUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelloAni - Premium Setup Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #050508;
        }
        .neon-glow {
            box-shadow: 0 0 50px rgba(139, 92, 246, 0.08);
            backdrop-filter: blur(20px);
        }
        .step-transition {
            opacity: 0;
            transform: translateY(12px);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .step-active {
            opacity: 1;
            transform: translateY(0);
        }
        .glass-input {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.25s ease;
        }
        .glass-input:focus {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(139, 92, 246, 0.5);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.15);
            outline: none;
        }
        .step-line-glow {
            box-shadow: 0 0 8px rgba(139, 92, 246, 0.5);
        }
        .indicator-glow {
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.25);
        }
        /* Loading spinner styling */
        .double-bounce1, .double-bounce2 {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: #8b5cf6;
            opacity: 0.6;
            position: absolute;
            top: 0;
            left: 0;
            animation: bounce 2.0s infinite ease-in-out;
        }
        .double-bounce2 {
            animation-delay: -1.0s;
        }
        @keyframes bounce {
            0%, 100% { transform: scale(0.0) }
            50% { transform: scale(1.0) }
        }

        /* Custom scrollbar styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.01);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.3);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.55);
        }
        /* Firefox */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(139, 92, 246, 0.3) rgba(255, 255, 255, 0.01);
        }
    </style>
</head>
<body class="text-gray-100 min-h-screen flex items-center justify-center py-6 sm:py-12 px-4 relative overflow-x-hidden overflow-y-auto">
    <!-- Neon Background Gradients -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-purple-600/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-cyan-600/10 rounded-full blur-[100px] pointer-events-none"></div>
    
    <div class="max-w-2xl w-full bg-[#0a0a0f]/85 border border-purple-500/15 rounded-2xl sm:rounded-3xl p-5 sm:p-8 md:p-10 neon-glow relative z-10">
        <!-- Logo -->
        <div class="text-center mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-black tracking-wider text-white">
                Hello<span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-500 to-cyan-400">Ani</span>
            </h1>
            <p class="text-[10px] sm:text-xs uppercase tracking-widest text-purple-400 font-semibold mt-1">Platform Setup Wizard</p>
        </div>

        <?php if ($success): ?>
            <!-- Installation Complete -->
            <div class="text-center py-4 sm:py-6">
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-green-500/10 border border-green-500/35 rounded-full flex items-center justify-center mx-auto mb-5 sm:mb-6 text-green-400 text-2xl sm:text-3xl">
                    <i class="fas fa-check-double"></i>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">Setup Completed Successfully!</h2>
                <p class="text-gray-400 text-xs sm:text-sm max-w-md mx-auto mb-6 sm:mb-8 px-2">
                    HelloAni has been fully installed. The database was populated, admin credentials set, and configurations saved to <code>config.php</code>.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-3 sm:gap-4 px-4">
                    <a href="/login" class="px-6 sm:px-8 py-2.5 sm:py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs sm:text-sm font-bold rounded-xl transition shadow-lg shadow-purple-500/15 text-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>Go to User Login
                    </a>
                    <a href="/admin/login" class="px-6 sm:px-8 py-2.5 sm:py-3 bg-white/5 border border-white/10 hover:bg-white/10 text-white text-xs sm:text-sm font-bold rounded-xl transition text-center">
                        <i class="fas fa-shield-alt mr-2"></i>Admin Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Wizard Steps Progress -->
            <div class="flex items-center justify-between mb-8 sm:mb-10 px-1 sm:px-2 max-w-lg mx-auto text-[10px] sm:text-xs font-bold text-gray-500">
                <div class="flex flex-col items-center gap-1 sm:gap-1.5 step-indicator-1 text-purple-400">
                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-purple-500 flex items-center justify-center bg-purple-950/40 text-purple-400 text-xs transition-all duration-300">1</span>
                    <span class="hidden sm:inline-block">System</span>
                </div>
                <div class="h-0.5 flex-1 bg-white/5 mx-1 sm:mx-2 -mt-4 transition-all duration-300 step-line-1"></div>
                <div class="flex flex-col items-center gap-1 sm:gap-1.5 step-indicator-2">
                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-white/5 flex items-center justify-center bg-white/3 text-gray-500 text-xs transition-all duration-300">2</span>
                    <span class="hidden sm:inline-block">Database</span>
                </div>
                <div class="h-0.5 flex-1 bg-white/5 mx-1 sm:mx-2 -mt-4 transition-all duration-300 step-line-2"></div>
                <div class="flex flex-col items-center gap-1 sm:gap-1.5 step-indicator-3">
                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-white/5 flex items-center justify-center bg-white/3 text-gray-500 text-xs transition-all duration-300">3</span>
                    <span class="hidden sm:inline-block">App & Admin</span>
                </div>
                <div class="h-0.5 flex-1 bg-white/5 mx-1 sm:mx-2 -mt-4 transition-all duration-300 step-line-3"></div>
                <div class="flex flex-col items-center gap-1 sm:gap-1.5 step-indicator-4">
                    <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-white/5 flex items-center justify-center bg-white/3 text-gray-500 text-xs transition-all duration-300">4</span>
                    <span class="hidden sm:inline-block">Mail</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-3 sm:p-4 bg-red-500/10 border border-red-500/25 rounded-xl text-red-400 text-xs sm:text-sm flex items-start gap-2.5 sm:gap-3">
                    <i class="fas fa-exclamation-circle mt-0.5 text-sm sm:text-base"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="installForm">
                <!-- STEP 1: REQUIREMENTS -->
                <div id="step-1" class="step-transition step-active">
                    <h3 class="text-lg sm:text-xl font-bold text-white mb-3 sm:mb-4">Environment Check</h3>
                    <p class="text-gray-400 text-xs sm:text-sm mb-5 sm:mb-6 leading-relaxed">
                        To install HelloAni, please ensure that your server meets the following requirements:
                    </p>
                    <div class="space-y-2 mb-6 sm:mb-8 max-h-[240px] sm:max-h-[320px] overflow-y-auto pr-1">
                        <?php foreach ($requirements as $key => $req): ?>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.01] border border-white/[0.04] text-xs sm:text-sm">
                                <span class="text-gray-300 font-medium mr-2"><?= htmlspecialchars($req['name']) ?></span>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] sm:text-xs text-gray-500"><?= htmlspecialchars($req['current']) ?></span>
                                    <?php if ($req['met']): ?>
                                        <span class="text-green-400"><i class="fas fa-check-circle text-sm sm:text-base"></i></span>
                                    <?php else: ?>
                                        <span class="text-red-400" title="Required"><i class="fas fa-times-circle text-sm sm:text-base"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex justify-end border-t border-white/5 pt-4">
                        <button type="button" onclick="nextStep(2)" class="px-5 sm:px-6 py-2.5 sm:py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs sm:text-sm font-bold rounded-xl transition shadow-lg shadow-purple-500/15 <?= !$requirementsMet ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$requirementsMet ? 'disabled' : '' ?>>
                            Continue Setup <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: DATABASE CONFIG -->
                <div id="step-2" class="step-transition hidden">
                    <h3 class="text-lg sm:text-xl font-bold text-white mb-3 sm:mb-4">Database Configuration</h3>
                    <p class="text-gray-400 text-xs sm:text-sm mb-5 sm:mb-6 leading-relaxed">
                        Enter your MySQL database credentials. The installer will create the database automatically.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-5">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Database Host</label>
                            <input type="text" name="db_host" id="db_host" value="localhost" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Database Name</label>
                            <input type="text" name="db_name" id="db_name" value="helloani" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Database User</label>
                            <input type="text" name="db_user" id="db_user" value="root" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Database Password</label>
                            <input type="password" name="db_pass" id="db_pass" value="" placeholder="Leave blank if none" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm">
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-3 mb-6 sm:mb-8">
                        <button type="button" onclick="testConnection()" class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 text-[10px] sm:text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 w-max">
                            <i class="fas fa-plug text-purple-400"></i>Test Connection
                        </button>
                        <div id="dbTestResult" class="hidden text-[10px] sm:text-xs py-2 px-3 rounded-lg w-full transition-all"></div>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/5 pt-4 gap-4">
                        <button type="button" onclick="prevStep(1)" class="px-4 sm:px-6 py-2.5 sm:py-3 text-xs sm:text-sm font-bold text-gray-400 hover:text-white transition">
                            <i class="fas fa-arrow-left mr-2 text-xs"></i>Back
                        </button>
                        <button type="button" onclick="nextStep(3)" class="px-5 sm:px-6 py-2.5 sm:py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs sm:text-sm font-bold rounded-xl transition">
                            Next Step <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: APP & ADMIN CONFIG -->
                <div id="step-3" class="step-transition hidden">
                    <h3 class="text-lg sm:text-xl font-bold text-white mb-3 sm:mb-4">Application & Admin Setup</h3>
                    <p class="text-gray-400 text-xs sm:text-sm mb-5 sm:mb-6 leading-relaxed">
                        Configure the application metadata and your super administrator credentials.
                    </p>
                    
                    <h4 class="text-xs sm:text-sm font-bold text-purple-400 uppercase tracking-widest mb-3 border-b border-purple-500/10 pb-1">App Configuration</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-5">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">App Name</label>
                            <input type="text" name="site_name" value="HelloAni" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">App Tagline</label>
                            <input type="text" name="site_tagline" value="Your World of Anime" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">App URL</label>
                            <input type="url" name="site_url" value="<?= htmlspecialchars($autodetectUrl) ?>" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                    </div>

                    <h4 class="text-xs sm:text-sm font-bold text-purple-400 uppercase tracking-widest mb-3 border-b border-purple-500/10 pb-1 mt-6">Admin Account</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-6 sm:mb-8">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Admin Username</label>
                            <input type="text" name="admin_user" value="admin" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Admin Email</label>
                            <input type="email" name="admin_email" value="admin@helloani.com" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Admin Password</label>
                            <input type="password" name="admin_pass" id="admin_pass" placeholder="Enter secure password" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">Confirm Admin Password</label>
                            <input type="password" id="admin_pass_confirm" placeholder="Repeat password" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm" required>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/5 pt-4 gap-4">
                        <button type="button" onclick="prevStep(2)" class="px-4 sm:px-6 py-2.5 sm:py-3 text-xs sm:text-sm font-bold text-gray-400 hover:text-white transition">
                            <i class="fas fa-arrow-left mr-2 text-xs"></i>Back
                        </button>
                        <button type="button" onclick="nextStep(4)" class="px-5 sm:px-6 py-2.5 sm:py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs sm:text-sm font-bold rounded-xl transition">
                            Next Step <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 4: MAIL SETTINGS & INSTALLATION -->
                <div id="step-4" class="step-transition hidden">
                    <h3 class="text-lg sm:text-xl font-bold text-white mb-3 sm:mb-4">Mail Server Configuration</h3>
                    <p class="text-gray-400 text-xs sm:text-sm mb-5 sm:mb-6 leading-relaxed">
                        Configure your SMTP server settings to enable emails (activation links, password reset codes). You can skip this or configure it later.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-6 sm:mb-8">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">SMTP Host (Optional)</label>
                            <input type="text" name="smtp_host" placeholder="smtp.gmail.com" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">SMTP Port</label>
                            <input type="text" name="smtp_port" value="587" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">SMTP Username (Gmail/etc.)</label>
                            <input type="text" name="smtp_user" placeholder="your-email@gmail.com" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] sm:text-xs font-semibold text-gray-400">SMTP Password</label>
                            <input type="password" name="smtp_pass" placeholder="App password or password" class="glass-input px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm">
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/5 pt-4 gap-4">
                        <button type="button" onclick="prevStep(3)" class="px-4 sm:px-6 py-2.5 sm:py-3 text-xs sm:text-sm font-bold text-gray-400 hover:text-white transition">
                            <i class="fas fa-arrow-left mr-2 text-xs"></i>Back
                        </button>
                        <button type="submit" name="install" onclick="showLoader()" class="px-6 sm:px-8 py-2.5 sm:py-3 bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white text-xs sm:text-sm font-bold rounded-xl transition shadow-lg shadow-purple-500/10">
                            <i class="fas fa-cogs mr-1.5"></i> Complete Installation
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Installer Loader Modal -->
    <div id="loaderModal" class="fixed inset-0 z-[1000] hidden items-center justify-center bg-[#050508]/85 backdrop-blur-md">
        <div class="text-center p-8 max-w-sm mx-auto bg-[#0a0a0f] border border-purple-500/20 rounded-2xl shadow-xl">
            <div class="flex justify-center mb-6">
                <div class="relative w-12 h-12">
                    <div class="double-bounce1"></div>
                    <div class="double-bounce2"></div>
                </div>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Installing Platform</h3>
            <p class="text-xs text-gray-400 leading-relaxed">Please wait, creating database, running migrations and setting up configurations...</p>
        </div>
    </div>

    <script>
        function nextStep(step) {
            // Validation
            if (step === 3) {
                // Ensure database settings are present
                if (!document.getElementById('db_host').value || !document.getElementById('db_name').value || !document.getElementById('db_user').value) {
                    alert('Please fill in database details.');
                    return;
                }
            }
            if (step === 4) {
                // Check passwords match
                const pass = document.getElementById('admin_pass').value;
                const confirm = document.getElementById('admin_pass_confirm').value;
                if (!pass) {
                    alert('Please choose an admin password.');
                    return;
                }
                if (pass !== confirm) {
                    alert('Admin passwords do not match!');
                    return;
                }
            }

            // Hide all steps & remove animation classes
            for (let i = 1; i <= 4; i++) {
                const stepEl = document.getElementById('step-' + i);
                stepEl.classList.add('hidden');
                stepEl.classList.remove('step-active');
                
                // Indicators reset
                const ind = document.querySelector('.step-indicator-' + i);
                if (ind) {
                    ind.classList.remove('text-purple-400');
                    const span = ind.querySelector('span');
                    span.className = "w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-white/5 flex items-center justify-center bg-white/3 text-gray-500 text-xs transition-all duration-300";
                }
                
                // Lines reset
                const line = document.querySelector('.step-line-' + i);
                if (line) {
                    line.classList.remove('bg-purple-500', 'step-line-glow');
                    line.classList.add('bg-white/5');
                }
            }
            
            // Show new step with animation
            const newStepEl = document.getElementById('step-' + step);
            newStepEl.classList.remove('hidden');
            // Allow layout trigger for CSS animation to run
            void newStepEl.offsetWidth;
            newStepEl.classList.add('step-active');
            
            // Activate current indicator and previous ones
            for (let i = 1; i <= step; i++) {
                const ind = document.querySelector('.step-indicator-' + i);
                if (ind) {
                    ind.classList.add('text-purple-400');
                    const span = ind.querySelector('span');
                    if (i === step) {
                        span.className = "w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-purple-500 flex items-center justify-center bg-purple-950/40 text-purple-400 text-xs transition-all duration-300 indicator-glow";
                    } else {
                        span.className = "w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-purple-500 flex items-center justify-center bg-purple-900 text-purple-300 text-xs transition-all duration-300";
                    }
                }
                if (i < step) {
                    const line = document.querySelector('.step-line-' + i);
                    if (line) {
                        line.classList.remove('bg-white/5');
                        line.classList.add('bg-purple-500', 'step-line-glow');
                    }
                }
            }
        }

        function prevStep(step) {
            nextStep(step);
        }

        function testConnection() {
            const btn = document.querySelector('#step-2 button');
            const result = document.getElementById('dbTestResult');
            
            result.className = "hidden text-xs py-2 px-3 rounded-lg w-full";
            result.innerHTML = '';
            
            const host = document.getElementById('db_host').value;
            const name = document.getElementById('db_name').value;
            const user = document.getElementById('db_user').value;
            const pass = document.getElementById('db_pass').value;
            
            const formData = new FormData();
            formData.append('db_host', host);
            formData.append('db_name', name);
            formData.append('db_user', user);
            formData.append('db_pass', pass);
            
            result.classList.remove('hidden');
            result.classList.add('bg-white/5', 'text-gray-400');
            result.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Testing connection...';
            
            fetch('install.php?action=test_db', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                result.className = "text-[10px] sm:text-xs py-2 px-3 rounded-lg w-full mt-3 transition-all";
                if (data.success) {
                    result.classList.add('bg-green-500/10', 'border', 'border-green-500/25', 'text-green-400');
                    result.innerHTML = '<i class="fas fa-check-circle mr-1.5"></i>' + data.message;
                } else {
                    result.classList.add('bg-red-500/10', 'border', 'border-red-500/25', 'text-red-400');
                    result.innerHTML = '<i class="fas fa-exclamation-circle mr-1.5"></i>' + data.error;
                }
            })
            .catch(err => {
                result.className = "text-[10px] sm:text-xs py-2 px-3 rounded-lg w-full mt-3 bg-red-500/10 border border-red-500/25 text-red-400";
                result.innerHTML = '<i class="fas fa-exclamation-circle mr-1.5"></i>Failed to execute AJAX request.';
            });
        }

        function showLoader() {
            if (document.getElementById('installForm').checkValidity()) {
                document.getElementById('loaderModal').classList.remove('hidden');
                document.getElementById('loaderModal').classList.add('flex');
            }
        }
    </script>
</body>
</html>
