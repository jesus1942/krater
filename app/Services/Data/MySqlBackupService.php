<?php

namespace Crater\Services\Data;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class MySqlBackupService
{
    public function version(): string
    {
        $version = (string) DB::selectOne('SELECT VERSION() AS v')->v;
        if ($version !== config('ena-operations.mysql_version')) {
            throw new BackupOperationException('MySQL no coincide con la version exacta aprobada.');
        }

        return $version;
    }

    public function fingerprints(): array
    {
        $result = [];
        $tables = DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
        foreach ($tables as $table) {
            $name = (string) array_values((array) $table)[0];
            $keys = DB::select('SHOW KEYS FROM `'.str_replace('`', '``', $name).'` WHERE Key_name = ?', ['PRIMARY']);
            usort($keys, function ($a, $b) { return $a->Seq_in_index <=> $b->Seq_in_index; });
            $columns = array_map(function ($key) { return $key->Column_name; }, $keys);
            if (! $columns) {
                $columns = array_map(function ($column) { return $column->Field; }, DB::select('SHOW COLUMNS FROM `'.str_replace('`', '``', $name).'`'));
            }
            $query = DB::table($name);
            foreach ($columns as $column) {
                $query->orderBy($column);
            }
            $hash = hash_init('sha256');
            $count = 0;
            foreach ($query->cursor() as $row) {
                // Hex binario, estable aun con BLOBs o texto fuera de UTF-8.
                foreach ((array) $row as $column => $value) {
                    $encoded = $value === null ? 'null' : bin2hex((string) $value);
                    hash_update($hash, strlen($column).':'.$column.':'.strlen($encoded).':'.$encoded.';');
                }
                hash_update($hash, "\n");
                $count++;
            }
            $result[$name] = ['count' => $count, 'sha256' => hash_final($hash)];
        }
        ksort($result);

        return $result;
    }

    protected function disk()
    {
        $name = config('ena-operations.backup_disk');
        $disk = config('filesystems.disks.'.$name, []);
        if (($disk['driver'] ?? '') !== 's3' || ! filter_var($disk['endpoint'] ?? '', FILTER_VALIDATE_URL)
            || parse_url($disk['endpoint'], PHP_URL_SCHEME) !== 'https') {
            throw new BackupOperationException('El backup requiere un bucket S3 privado por HTTPS.');
        }
        foreach (['bucket', 'region', 'key', 'secret'] as $required) {
            if (empty($disk[$required])) {
                throw new BackupOperationException('Falta configurar el bucket de backups.');
            }
        }
        if (strlen((string) config('ena-operations.encryption_key')) < 32) {
            throw new BackupOperationException('Falta la clave de cifrado del backup.');
        }
        if (! preg_match('#^[a-zA-Z0-9_/-]+$#', config('ena-operations.backup_prefix'))
            || strpos(config('ena-operations.backup_prefix'), '..') !== false) {
            throw new BackupOperationException('Prefijo de backups invalido.');
        }

        return Storage::disk($name);
    }

    protected function workspace(): string
    {
        $directory = storage_path('app/backup-temp/ena-'.bin2hex(random_bytes(12)));
        if (! mkdir($directory, 0700, true)) {
            throw new BackupOperationException('No se pudo crear el directorio privado temporal.');
        }

        return $directory;
    }

    protected function credentials(string $directory, array $connection): string
    {
        $path = $directory.'/client.cnf';
        $quote = function ($value) {
            return '"'.strtr((string) $value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r']).'"';
        };
        $data = "[client]\n";
        foreach (['host' => 'host', 'port' => 'port', 'user' => 'username', 'password' => 'password'] as $option => $key) {
            $data .= $option.'='.$quote($connection[$key] ?? '')."\n";
        }
        file_put_contents($path, $data);
        chmod($path, 0600);

        return $path;
    }

    protected function run(array $arguments, $input = null): void
    {
        $process = new Process($arguments, null, null, $input, config('ena-operations.timeout'));
        $process->run();
        if (! $process->isSuccessful()) {
            // La salida SQL puede contener datos o claves: no se imprime ni se registra.
            throw new BackupOperationException('Fallo el cliente MySQL (codigo '.(int) $process->getExitCode().').');
        }
    }

    protected function cleanup(string $directory): void
    {
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    public function backup(): array
    {
        $disk = $this->disk();
        $version = $this->version();
        $directory = $this->workspace();
        try {
            $connection = config('database.connections.'.config('database.default'));
            $before = $this->fingerprints();
            $sql = $directory.'/database.sql';
            $credentials = $this->credentials($directory, $connection);
            $this->run([config('ena-operations.dump_binary'), '--defaults-extra-file='.$credentials,
                '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces',
                '--routines', '--events', '--triggers', '--hex-blob', '--skip-comments', '--set-gtid-purged=OFF', '--column-statistics=0',
                '--result-file='.$sql, $connection['database']]);
            chmod($sql, 0600);
            if (! is_file($sql) || filesize($sql) < 100 || $before !== $this->fingerprints()) {
                throw new BackupOperationException('Los datos cambiaron durante el backup: reintentar, sin podar backups previos.');
            }
            $manifest = ['format' => 1, 'created_at' => now()->utc()->toIso8601String(),
                'environment' => app()->environment(), 'mysql_version' => $version,
                'revision' => config('ena-operations.revision'), 'tables' => $before,
                'sql_sha256' => hash_file('sha256', $sql)];
            file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES));
            $archive = $directory.'/backup.zip';
            $zip = new ZipArchive();
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new BackupOperationException('No se pudo crear el backup cifrado.');
            }
            $zip->setPassword(config('ena-operations.encryption_key'));
            foreach (['database.sql', 'manifest.json'] as $file) {
                if (! $zip->addFile($directory.'/'.$file, $file) || ! $zip->setEncryptionName($file, ZipArchive::EM_AES_256)) {
                    $zip->close();
                    throw new BackupOperationException('No se pudo cifrar el backup con AES-256.');
                }
            }
            if (! $zip->close()) {
                throw new BackupOperationException('No se pudo finalizar el backup cifrado.');
            }
            chmod($archive, 0600);
            $key = trim(config('ena-operations.backup_prefix'), '/').'/'.now()->utc()->format('Ymd\THis\Z').'-'.bin2hex(random_bytes(6)).'.zip';
            $stream = fopen($archive, 'rb');
            try {
                if (! $disk->put($key, $stream, ['visibility' => 'private'])) {
                    throw new BackupOperationException('No se pudo subir el backup.');
                }
            } finally {
                fclose($stream);
            }
            $manifest['key'] = $key;
            $manifest['archive_sha256'] = hash_file('sha256', $archive);
            $manifest['bytes'] = filesize($archive);
            $this->download($key, $directory.'/remote.zip', $manifest['archive_sha256']);
            // Manifest publicado al final: solo los objetos verificados son backups validos.
            if (! $disk->put($key.'.json', json_encode($manifest, JSON_UNESCAPED_SLASHES), ['visibility' => 'private'])) {
                throw new BackupOperationException('No se pudo publicar el manifest del backup.');
            }
            $pruned = $this->prune();

            return ['passed' => true, 'key' => $key, 'mysql_version' => $version, 'bytes' => $manifest['bytes'],
                'archive_sha256' => $manifest['archive_sha256'], 'tables' => count($before), 'pruned' => $pruned];
        } finally {
            $this->cleanup($directory);
        }
    }

    protected function records(): array
    {
        $disk = $this->disk();
        $prefix = trim(config('ena-operations.backup_prefix'), '/').'/';
        $records = [];
        foreach ($disk->files(trim($prefix, '/')) as $path) {
            if (preg_match('#^'.preg_quote($prefix, '#').'[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}\.zip\.json$#', $path)) {
                $record = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
                if (($record['key'] ?? null) !== substr($path, 0, -5) || ($record['environment'] ?? '') !== app()->environment()
                    || ! isset($record['created_at'], $record['archive_sha256'], $record['mysql_version'])) {
                    throw new BackupOperationException('Manifest remoto invalido: se cancela la retencion.');
                }
                $records[] = $record;
            }
        }
        usort($records, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });

        return $records;
    }

    protected function prune(): int
    {
        $records = $this->records();
        $keep = (new BackupRetention())->keep($records);
        $pruned = 0;
        foreach ($records as $record) {
            if (! in_array($record['key'], $keep, true)) {
                if (! $this->disk()->delete([$record['key'], $record['key'].'.json'])) {
                    throw new BackupOperationException('No se pudo completar la retencion.');
                }
                $pruned++;
            }
        }

        return $pruned;
    }

    protected function download(string $key, string $path, string $expected): void
    {
        $remote = $this->disk()->readStream($key);
        if (! is_resource($remote)) {
            throw new BackupOperationException('No se pudo leer el backup remoto.');
        }
        $local = fopen($path, 'wb');
        chmod($path, 0600);
        try {
            stream_copy_to_stream($remote, $local);
        } finally {
            fclose($remote);
            fclose($local);
        }
        if (! hash_equals($expected, hash_file('sha256', $path))) {
            throw new BackupOperationException('El checksum del backup no coincide.');
        }
    }

    public function restoreTest(): array
    {
        // Ninguna opcion permite restaurar sobre la base operativa.
        $source = config('database.connections.'.config('database.default'));
        $target = config('ena-operations.restore_connection');
        if (! app()->environment('staging', 'restore-test') || ($source['database'] ?? '') !== 'krater_staging'
            || empty($target['host']) || $target['host'] === $source['host'] || empty($target['username']) || empty($target['password'])) {
            throw new BackupOperationException('La restauracion requiere staging y un servidor MySQL aislado.');
        }
        $records = $this->records();
        if (! $records) {
            throw new BackupOperationException('No hay un backup verificado para restaurar.');
        }
        $record = $records[0];
        if ($record['mysql_version'] !== config('ena-operations.mysql_version')) {
            throw new BackupOperationException('La version del backup no coincide con la version exacta aprobada.');
        }
        $directory = $this->workspace();
        $original = config('database.default');
        try {
            $this->download($record['key'], $directory.'/backup.zip', $record['archive_sha256']);
            $zip = new ZipArchive();
            if ($zip->open($directory.'/backup.zip') !== true) {
                throw new BackupOperationException('No se pudo abrir el archivo remoto.');
            }
            $zip->setPassword(config('ena-operations.encryption_key'));
            foreach (['database.sql', 'manifest.json'] as $file) {
                $stream = $zip->getStream($file);
                if (! is_resource($stream)) {
                    $zip->close();
                    throw new BackupOperationException('El archivo no se pudo descifrar.');
                }
                $local = fopen($directory.'/'.$file, 'wb');
                chmod($directory.'/'.$file, 0600);
                stream_copy_to_stream($stream, $local);
                fclose($local);
                fclose($stream);
            }
            $zip->close();
            $inside = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            if ($inside['tables'] !== $record['tables'] || $inside['mysql_version'] !== $record['mysql_version']
                || ! hash_equals($inside['sql_sha256'], hash_file('sha256', $directory.'/database.sql'))) {
                throw new BackupOperationException('El contenido del backup no coincide con su manifest.');
            }
            config(['database.connections.ena_restore' => $target, 'database.default' => 'ena_restore']);
            DB::purge('ena_restore');
            $version = $this->version();
            $database = 'krater_restore_test_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
            DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4');
            config(['database.connections.ena_restore.database' => $database]);
            DB::purge('ena_restore');
            $credentials = $this->credentials($directory, $target);
            $input = fopen($directory.'/database.sql', 'rb');
            try {
                $this->run([config('ena-operations.restore_binary'), '--defaults-extra-file='.$credentials, $database], $input);
            } finally {
                fclose($input);
            }
            $actual = $this->fingerprints();
            if ($actual !== $inside['tables']) {
                throw new BackupOperationException('Los conteos o huellas de la restauracion difieren del backup.');
            }
            if (Artisan::call('ena:auditar-niveles', ['--json' => true]) !== 0) {
                throw new BackupOperationException('Fallo la auditoria de niveles restaurados.');
            }
            $audit = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            // No guardar nombres, DNI ni propuestas con datos personales en evidencia publica.
            $summary = [];
            foreach ($audit['companies'] as $company) {
                $summary[] = ['company_id' => $company['company']['id'], 'models' => $company['models']];
            }
            $report = ['passed' => true, 'created_at' => now()->utc()->toIso8601String(), 'key' => $record['key'],
                'target_database' => $database, 'mysql_version' => $version, 'table_fingerprints_match' => true,
                'tables' => count($actual), 'archive_sha256' => $record['archive_sha256'], 'audit' => $summary];
            if (! $this->disk()->put(trim(config('ena-operations.backup_prefix'), '/').'/restore-tests/'.gmdate('Ymd\THis\Z').'.json',
                json_encode($report, JSON_UNESCAPED_SLASHES), ['visibility' => 'private'])) {
                throw new BackupOperationException('No se pudo registrar la evidencia de restauracion.');
            }

            return $report;
        } finally {
            config(['database.default' => $original]);
            DB::purge('ena_restore');
            $this->cleanup($directory);
        }
    }
}
