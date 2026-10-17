<?php
header('Content-Type: application/json');
echo json_encode([
    'name' => 'HelloAni',
    'short_name' => 'HelloAni',
    'description' => 'Premium Anime Streaming Platform',
    'start_url' => '/',
    'display' => 'standalone',
    'background_color' => '#0a0a0f',
    'theme_color' => '#8b5cf6',
    'orientation' => 'any',
    'icons' => [
        ['src' => '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/assets/img/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml']
    ],
    'categories' => ['entertainment'],
    'lang' => 'en',
    'dir' => 'ltr'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
