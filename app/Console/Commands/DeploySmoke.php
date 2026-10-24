<?php

namespace Crater\Console\Commands;

use Crater\Services\Data\DeploySnapshotService;
use Illuminate\Console\Command;
use Throwable;
use GuzzleHttp\Client;
use Crater\Services\Data\MySqlBackupService;

class DeploySmoke extends Command
{
    protected $signature = 'ena:smoke {--http : Verifica ademas el servicio desplegado y sus assets}';
    protected $description = 'Guarda conteos institucionales y aborta el deploy ante perdidas no declaradas';

    public function handle(DeploySnapshotService $service)
    {
        try {
            if (! app()->environment('testing')) {
                app(MySqlBackupService::class)->version();
            }
            $report = $service->check();
            $this->line('SuiteEna deploy smoke: '.json_encode($report, JSON_UNESCAPED_SLASHES));

            if ($report['passed'] && $this->option('http')) {
                $base = rtrim(config('app.url'), '/');
                if (parse_url($base, PHP_URL_SCHEME) !== 'https') {
                    throw new \RuntimeException('El smoke HTTP requiere APP_URL HTTPS.');
                }
                $client = new Client(['base_uri' => $base, 'timeout' => 30, 'http_errors' => false, 'allow_redirects' => false]);
                $checks = [];
                foreach (['/ping' => 200, '/login' => 200, '/api/v1/bootstrap' => 401,
                    '/api/v1/students' => 401, '/api/v1/backups' => 401] as $path => $expected) {
                    $response = $client->get($path, ['headers' => ['Accept' => strpos($path, '/api/') === 0 ? 'application/json' : 'text/html']]);
                    $checks[] = ['path' => $path, 'expected' => $expected, 'actual' => $response->getStatusCode()];
                    if ($response->getStatusCode() !== $expected) {
                        throw new \RuntimeException('Fallo el smoke HTTP.');
                    }
                }
                foreach (json_decode(file_get_contents(public_path('mix-manifest.json')), true, 512, JSON_THROW_ON_ERROR) as $local => $remote) {
                    $response = $client->get($remote);
                    $matches = $response->getStatusCode() === 200
                        && hash_equals(hash_file('sha256', public_path(ltrim($local, '/'))), hash('sha256', (string) $response->getBody()));
                    $checks[] = ['path' => $remote, 'actual' => $response->getStatusCode(), 'matches_build' => $matches];
                    if (! $matches) {
                        throw new \RuntimeException('El frontend servido no coincide con el deploy.');
                    }
                }
                $this->line('SuiteEna HTTP smoke: '.json_encode(['passed' => true, 'checks' => $checks], JSON_UNESCAPED_SLASHES));
            }

            return $report['passed'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            // No volcar DSN, SQL ni credenciales de una excepcion del proveedor.
            $this->error('No se pudo verificar la integridad del deploy ('.class_basename($e).').');

            return self::FAILURE;
        }
    }
}
