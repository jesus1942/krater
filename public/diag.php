<?php
header('Content-Type: text/plain');
echo "PHP " . PHP_VERSION . "\n";
echo "DB_HOST=" . getenv('DB_HOST') . "\n";
echo "DB_PORT=" . getenv('DB_PORT') . "\n";
echo "DB_DATABASE=" . getenv('DB_DATABASE') . "\n";
echo "DB_USERNAME=" . getenv('DB_USERNAME') . "\n";
echo "APP_KEY=" . (getenv('APP_KEY') ? 'SET' : 'MISSING') . "\n";
echo "database_created=" . (file_exists(__DIR__ . '/../storage/app/database_created') ? 'YES' : 'NO') . "\n";
try {
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8',
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $row = $pdo->query("SELECT value FROM settings WHERE key='profile_complete' LIMIT 1")->fetch();
    echo "DB=OK profile_complete=" . ($row ? $row['value'] : 'NOT SET') . "\n";
} catch (Exception $e) {
    echo "DB_ERROR=" . $e->getMessage() . "\n";
}
