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
    $row = $pdo->query("SELECT `value` FROM `settings` WHERE `option`='profile_complete' LIMIT 1")->fetch();
    echo "DB=OK profile_complete=" . ($row ? $row['value'] : 'NOT SET') . "\n";
    $users = $pdo->query("SELECT COUNT(*) AS c FROM `users`")->fetch();
    echo "users=" . $users['c'] . "\n";
} catch (Exception $e) {
    echo "DB_ERROR=" . $e->getMessage() . "\n";
}

// Ultimas lineas del log de Laravel para ver el error 500
$dir = __DIR__ . '/../storage/logs';
$logs = glob($dir . '/*.log');
echo "\nLOGS_ENCONTRADOS=" . implode(', ', array_map('basename', $logs ?: [])) . "\n";
if ($logs) {
    usort($logs, fn ($a, $b) => filemtime($b) - filemtime($a));
    $lines = file($logs[0]);
    echo "\n--- ULTIMAS 80 LINEAS DE " . basename($logs[0]) . " ---\n";
    echo implode('', array_slice($lines, -80));
} else {
    echo "DIR_LOGS_ESCRIBIBLE=" . (is_writable($dir) ? 'SI' : 'NO') . "\n";
}
