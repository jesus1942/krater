<?php

return [
    'mysql_version' => '9.7.2',
    'backup_disk' => 'ena_backups',
    'backup_prefix' => env('BACKUP_PREFIX', 'suiteena/'.env('APP_ENV', 'production')),
    'backup_source_environment' => env('BACKUP_SOURCE_ENVIRONMENT', env('APP_ENV', 'production')),
    'dump_binary' => env('BACKUP_DUMP_BINARY', '/usr/local/bin/mysqldump'),
    'restore_binary' => env('BACKUP_RESTORE_BINARY', '/usr/local/bin/mysql'),
    'timeout' => 1800,
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'encryption_key_id' => env('BACKUP_ENCRYPTION_KEY_ID', 'backup-v1'),
    'previous_encryption_keys' => json_decode(env('BACKUP_PREVIOUS_ENCRYPTION_KEYS', '{}'), true) ?: [],
    'external_drive' => [
        'folder_id' => env('BACKUP_DRIVE_FOLDER_ID'),
        'client_id' => env('BACKUP_DRIVE_CLIENT_ID'),
        'client_secret' => env('BACKUP_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('BACKUP_DRIVE_REFRESH_TOKEN'),
    ],
    'revision' => env('RAILWAY_GIT_COMMIT_SHA', 'local'),
    'deployment_id' => env('RAILWAY_DEPLOYMENT_ID', 'local'),
    'restore_allowed_host' => env('RESTORE_ALLOWED_HOST', 'mysql-restore-test.railway.internal'),
    'restore_connection' => [
        'driver' => 'mysql', 'host' => env('RESTORE_DB_HOST'),
        'port' => env('RESTORE_DB_PORT', 3306),
        'username' => env('RESTORE_DB_USERNAME'), 'password' => env('RESTORE_DB_PASSWORD'),
        'database' => null, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
    ],
];
