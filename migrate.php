<?php
// Migration: Add new tables for notifications, contact messages, anime requests
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        name VARCHAR(100),
        email VARCHAR(100),
        subject VARCHAR(200),
        message TEXT NOT NULL,
        status ENUM('unread','read','replied') DEFAULT 'unread',
        admin_reply TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS anime_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        anime_title VARCHAR(255) NOT NULL,
        anime_alt_title VARCHAR(255),
        description TEXT,
        reference_url VARCHAR(500),
        status ENUM('pending','approved','rejected','added') DEFAULT 'pending',
        admin_note TEXT,
        added_anime_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS title VARCHAR(255) AFTER type",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS episode_id INT AFTER target_id",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS server_name VARCHAR(100) AFTER episode_id",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS details TEXT AFTER reason"
];

foreach($queries as $q) {
    try { db()->query($q); echo "OK: " . substr($q, 0, 60) . "...\n"; }
    catch(Exception $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
}
echo "\nMigration complete!\n";
