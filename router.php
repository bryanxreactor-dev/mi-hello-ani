<?php
// PHP built-in server router
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Serve static files
$staticExts = ['css','js','png','jpg','jpeg','gif','webp','svg','ico','woff','woff2','ttf','mp4','webm','vtt','txt','xml','json'];
$ext = pathinfo($path, PATHINFO_EXTENSION);
if (in_array(strtolower($ext), $staticExts)) {
    $file = __DIR__ . $path;
    if (file_exists($file)) return false;
}

// Route everything else through index.php
require __DIR__ . '/index.php';
