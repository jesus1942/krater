<?php

return [
    'mysql_version' => '9.7.2',
    'backup_disk' => 'ena_backups',
    'backup_prefix' => env('BACKUP_PREFIX', 'suiteena/'.env('APP_ENV', 'production')),
    'dump_binary' => env('BACKUP_DUMP_BINARY', 'mysqldump'),
    'restore_binary' => env('BACKUP_RESTORE_BINARY', 'mysql'),
    'timeout' => 1800,
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'revision' => env('RAILWAY_GIT_COMMIT_SHA', 'local'),
    'deployment_id' => env('RAILWAY_DEPLOYMENT_ID', 'local'),
    'restore_connection' => [
        'driver' => 'mysql', 'host' => env('RESTORE_DB_HOST'),
        'port' => env('RESTORE_DB_PORT', 3306),
        'username' => env('RESTORE_DB_USERNAME'), 'password' => env('RESTORE_DB_PASSWORD'),
        'database' => null, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
    ],
];
