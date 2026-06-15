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
    $profile = $pdo->query("SELECT `value` FROM `settings` WHERE `option`='profile_complete' LIMIT 1")->fetch();
    echo "DB=OK profile_complete=" . ($profile ? $profile['value'] : 'NOT SET') . "\n";

    $users = $pdo->query("SELECT id, email, company_id FROM `users` LIMIT 5")->fetchAll();
    echo "users=" . count($users) . "\n";
    foreach ($users as $u) echo "  id={$u['id']} email={$u['email']} company_id={$u['company_id']}\n";

    $companies = $pdo->query("SELECT id, name FROM `companies` LIMIT 5")->fetchAll();
    echo "companies=" . count($companies) . "\n";
    foreach ($companies as $c) echo "  id={$c['id']} name={$c['name']}\n";

    $csettings = $pdo->query("SELECT `option`, `value` FROM `company_settings` LIMIT 20")->fetchAll();
    echo "company_settings=" . count($csettings) . "\n";
    foreach ($csettings as $s) echo "  {$s['option']}={$s['value']}\n";

    $currencies = $pdo->query("SELECT COUNT(*) AS c FROM `currencies`")->fetch();
    echo "currencies=" . $currencies['c'] . "\n";
} catch (Exception $e) {
    echo "DB_ERROR=" . $e->getMessage() . "\n";
}

$dir = __DIR__ . '/../storage/logs';
echo "\nstorage_logs_writable=" . (is_writable($dir) ? 'SI' : 'NO') . "\n";
$logs = glob($dir . '/*.log') ?: [];
echo "logs=" . implode(', ', array_map('basename', $logs)) . "\n";
if ($logs) {
    usort($logs, fn($a, $b) => filemtime($b) - filemtime($a));
    $lines = file($logs[0]);
    echo "\n--- LOG " . basename($logs[0]) . " ---\n";
    echo implode('', array_slice($lines, -80));
}
