<?php
// Database migration for languages & networks
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$queries = [
    // Languages table
    "CREATE TABLE IF NOT EXISTS languages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        native_name VARCHAR(100),
        code VARCHAR(10) UNIQUE NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        order_num INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // Networks table
    "CREATE TABLE IF NOT EXISTS networks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        logo_url VARCHAR(255) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        order_num INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // Anime-Languages junction table
    "CREATE TABLE IF NOT EXISTS anime_languages (
        anime_id INT NOT NULL,
        language_id INT NOT NULL,
        PRIMARY KEY (anime_id, language_id),
        FOREIGN KEY (anime_id) REFERENCES anime(id) ON DELETE CASCADE,
        FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
    )",

    // Anime-Networks junction table
    "CREATE TABLE IF NOT EXISTS anime_networks (
        anime_id INT NOT NULL,
        network_id INT NOT NULL,
        PRIMARY KEY (anime_id, network_id),
        FOREIGN KEY (anime_id) REFERENCES anime(id) ON DELETE CASCADE,
        FOREIGN KEY (network_id) REFERENCES networks(id) ON DELETE CASCADE
    )"
];

// Execute table creation
foreach ($queries as $q) {
    try {
        db()->query($q);
        echo "OK: " . substr(trim(preg_replace('/\s+/', ' ', $q)), 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// Seed Languages
$seedLanguages = [
    ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'code' => 'hindi', 'order_num' => 1],
    ['name' => 'Tamil', 'native_name' => 'தமிழ்', 'code' => 'tamil', 'order_num' => 2],
    ['name' => 'Telugu', 'native_name' => 'తెలుగు', 'code' => 'telugu', 'order_num' => 3],
    ['name' => 'Bengali', 'native_name' => 'বাংলা', 'code' => 'bengali', 'order_num' => 4],
    ['name' => 'Malayalam', 'native_name' => 'മലയാളം', 'code' => 'malayalam', 'order_num' => 5],
    ['name' => 'Kannada', 'native_name' => 'ಕನ್ನಡ', 'code' => 'kannada', 'order_num' => 6],
    ['name' => 'English', 'native_name' => 'English', 'code' => 'english', 'order_num' => 7],
    ['name' => 'Japanese', 'native_name' => '日本語', 'code' => 'japanese', 'order_num' => 8],
    ['name' => 'Korean', 'native_name' => '한국어', 'code' => 'korean', 'order_num' => 9],
];

echo "\nSeeding Languages...\n";
foreach ($seedLanguages as $lang) {
    try {
        $existing = db()->fetch("SELECT id FROM languages WHERE code = ?", [$lang['code']]);
        if (!$existing) {
            db()->insert('languages', [
                'name' => $lang['name'],
                'native_name' => $lang['native_name'],
                'code' => $lang['code'],
                'is_active' => 1,
                'order_num' => $lang['order_num']
            ]);
            echo "Added language: " . $lang['name'] . "\n";
        } else {
            echo "Skipped (already exists): " . $lang['name'] . "\n";
        }
    } catch (Exception $e) {
        echo "Error seeding language " . $lang['name'] . ": " . $e->getMessage() . "\n";
    }
}

// Seed Networks
$seedNetworks = [
    ['name' => 'Disney Channel', 'logo_url' => '/uploads/networks/disney.png', 'order_num' => 1],
    ['name' => 'Hungama', 'logo_url' => '/uploads/networks/hungama.png', 'order_num' => 2],
    ['name' => 'Sony YAY!', 'logo_url' => '/uploads/networks/sonyyay.png', 'order_num' => 3],
    ['name' => 'Cartoon Network', 'logo_url' => '/uploads/networks/cn.png', 'order_num' => 4],
    ['name' => 'Prime Video', 'logo_url' => '/uploads/networks/prime.png', 'order_num' => 5],
    ['name' => 'Netflix', 'logo_url' => '/uploads/networks/netflix.png', 'order_num' => 6],
    ['name' => 'Disney+ Hotstar', 'logo_url' => '/uploads/networks/hotstar.png', 'order_num' => 7],
    ['name' => 'Crunchyroll', 'logo_url' => '/uploads/networks/crunchyroll.png', 'order_num' => 8],
];

// Ensure network upload directory exists
$netDir = __DIR__ . '/uploads/networks/';
if (!file_exists($netDir)) {
    mkdir($netDir, 0777, true);
}

echo "\nSeeding Networks...\n";
foreach ($seedNetworks as $net) {
    try {
        $existing = db()->fetch("SELECT id FROM networks WHERE name = ?", [$net['name']]);
        if (!$existing) {
            db()->insert('networks', [
                'name' => $net['name'],
                'logo_url' => $net['logo_url'],
                'is_active' => 1,
                'order_num' => $net['order_num']
            ]);
            echo "Added network: " . $net['name'] . "\n";
        } else {
            echo "Skipped (already exists): " . $net['name'] . "\n";
        }
    } catch (Exception $e) {
        echo "Error seeding network " . $net['name'] . ": " . $e->getMessage() . "\n";
    }
}

// Associate existing demo animes with some languages and networks for demonstration
echo "\nAssociating demo data...\n";
try {
    $animes = db()->fetchAll("SELECT id FROM anime LIMIT 5");
    $dbLanguages = db()->fetchAll("SELECT id FROM languages");
    $dbNetworks = db()->fetchAll("SELECT id FROM networks");

    if (!empty($animes) && !empty($dbLanguages) && !empty($dbNetworks)) {
        // Clear any old mapping to avoid duplicates
        db()->query("DELETE FROM anime_languages");
        db()->query("DELETE FROM anime_networks");

        // Solo Leveling (1st anime)
        db()->insert('anime_languages', ['anime_id' => $animes[0]['id'], 'language_id' => $dbLanguages[1]['id']]); // Tamil
        db()->insert('anime_languages', ['anime_id' => $animes[0]['id'], 'language_id' => $dbLanguages[0]['id']]); // Hindi
        db()->insert('anime_languages', ['anime_id' => $animes[0]['id'], 'language_id' => $dbLanguages[6]['id']]); // English
        db()->insert('anime_networks', ['anime_id' => $animes[0]['id'], 'network_id' => $dbNetworks[7]['id']]); // Crunchyroll
        db()->insert('anime_networks', ['anime_id' => $animes[0]['id'], 'network_id' => $dbNetworks[5]['id']]); // Netflix

        // Jujutsu Kaisen (2nd anime)
        if (isset($animes[1])) {
            db()->insert('anime_languages', ['anime_id' => $animes[1]['id'], 'language_id' => $dbLanguages[1]['id']]); // Tamil
            db()->insert('anime_languages', ['anime_id' => $animes[1]['id'], 'language_id' => $dbLanguages[2]['id']]); // Telugu
            db()->insert('anime_languages', ['anime_id' => $animes[1]['id'], 'language_id' => $dbLanguages[6]['id']]); // English
            db()->insert('anime_networks', ['anime_id' => $animes[1]['id'], 'network_id' => $dbNetworks[7]['id']]); // Crunchyroll
        }

        // Demon Slayer (3rd anime)
        if (isset($animes[2])) {
            db()->insert('anime_languages', ['anime_id' => $animes[2]['id'], 'language_id' => $dbLanguages[0]['id']]); // Hindi
            db()->insert('anime_languages', ['anime_id' => $animes[2]['id'], 'language_id' => $dbLanguages[1]['id']]); // Tamil
            db()->insert('anime_networks', ['anime_id' => $animes[2]['id'], 'network_id' => $dbNetworks[6]['id']]); // Hotstar
            db()->insert('anime_networks', ['anime_id' => $animes[2]['id'], 'network_id' => $dbNetworks[5]['id']]); // Netflix
        }

        echo "Demo associations created successfully!\n";
    }
} catch (Exception $e) {
    echo "Error creating demo associations: " . $e->getMessage() . "\n";
}

echo "\nMigration script complete!\n";
