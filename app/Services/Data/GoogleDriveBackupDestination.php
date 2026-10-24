<?php

namespace Crater\Services\Data;

use GuzzleHttp\Client;

class GoogleDriveBackupDestination
{
    protected $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client(['timeout' => 1800, 'connect_timeout' => 30,
            'allow_redirects' => false, 'http_errors' => false]);
    }

    /** La cuenta y carpeta pertenecen a la escuela, nunca se elige una por defecto. */
    public function validate(): void
    {
        foreach (['folder_id', 'client_id', 'client_secret', 'refresh_token'] as $field) {
            if (empty(config('ena-operations.external_drive.'.$field))) {
                throw new BackupOperationException('Falta configurar Google Drive de la escuela.');
            }
        }
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', config('ena-operations.external_drive.folder_id'))) {
            throw new BackupOperationException('ID de carpeta Drive invalido.');
        }
    }

    /** Requests sin excepciones que expongan tokens, cuerpos de error o URLs de sesion. */
    protected function request(string $method, string $url, array $options = [])
    {
        // Aplicar tambien si el contenedor inyecta un Client con otros defaults.
        $options = array_merge($options, ['allow_redirects' => false, 'http_errors' => false,
            'timeout' => 1800, 'connect_timeout' => 30]);
        $response = $this->client->request($method, $url, $options);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new BackupOperationException('Google Drive rechazo la operacion (HTTP '.$response->getStatusCode().').');
        }
        return $response;
    }

    /** Upload resumable para evitar cargar todo el ZIP en memoria; luego descarga real. */
    protected function upload(string $token, string $name, string $mime, string $path, array $properties = []): string
    {
        $folder = config('ena-operations.external_drive.folder_id');
        $response = $this->request('POST', 'https://www.googleapis.com/upload/drive/v3/files', [
            'query' => ['uploadType' => 'resumable', 'supportsAllDrives' => 'true', 'fields' => 'id'],
            'headers' => ['Authorization' => 'Bearer '.$token, 'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) filesize($path)],
            'json' => ['name' => $name, 'parents' => [$folder], 'mimeType' => $mime, 'appProperties' => $properties],
        ]);
        $url = $response->getHeaderLine('Location');
        // Nunca enviar OAuth a otro host ni seguir redirects de una sesion.
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'www.googleapis.com'
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || ! in_array(parse_url($url, PHP_URL_PORT), [null, 443], true)) {
            throw new BackupOperationException('La URL de carga Drive no es valida.');
        }
        $stream = fopen($path, 'rb');
        try {
            $response = $this->request('PUT', $url, ['headers' => ['Authorization' => 'Bearer '.$token,
                'Content-Type' => $mime, 'Content-Length' => (string) filesize($path)], 'body' => $stream]);
        } finally { if (is_resource($stream)) { fclose($stream); } }
        $id = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['id'] ?? '';
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $id)) { throw new BackupOperationException('Drive no devolvio un ID valido.'); }
        return $id;
    }

    /** La evidencia se publica solo despues de descargar y comparar el SHA-256. */
    public function copy(string $archive, array $manifest, string $directory): array
    {
        $this->validate();
        if (! hash_equals($manifest['archive_sha256'], hash_file('sha256', $archive))) {
            throw new BackupOperationException('El checksum de origen no coincide.');
        }
        $credentials = config('ena-operations.external_drive');
        $response = $this->request('POST', 'https://oauth2.googleapis.com/token', ['form_params' => [
            'client_id' => $credentials['client_id'], 'client_secret' => $credentials['client_secret'],
            'refresh_token' => $credentials['refresh_token'], 'grant_type' => 'refresh_token',
        ]]);
        $token = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['access_token'] ?? '';
        if (! is_string($token) || $token === '') { throw new BackupOperationException('Drive no devolvio un token valido.'); }
        $folder = config('ena-operations.external_drive.folder_id');
        $metadata = json_decode((string) $this->request('GET', 'https://www.googleapis.com/drive/v3/files/'.$folder, [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'query' => ['supportsAllDrives' => 'true', 'fields' => 'id,mimeType,trashed,capabilities(canAddChildren)'],
        ])->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (($metadata['mimeType'] ?? '') !== 'application/vnd.google-apps.folder' || ! empty($metadata['trashed'])
            || empty($metadata['capabilities']['canAddChildren'])) {
            throw new BackupOperationException('Drive requiere una carpeta habilitada para escritura.');
        }
        $id = $this->upload($token, basename($manifest['key']), 'application/zip', $archive,
            ['suiteena_sha256' => $manifest['archive_sha256'], 'suiteena_environment' => $manifest['environment']]);
        $download = $directory.'/drive-download.zip';
        touch($download); chmod($download, 0600);
        $this->request('GET', 'https://www.googleapis.com/drive/v3/files/'.$id, [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'query' => ['alt' => 'media', 'supportsAllDrives' => 'true'], 'sink' => $download,
        ]);
        if (! hash_equals($manifest['archive_sha256'], hash_file('sha256', $download))) {
            throw new BackupOperationException('El checksum de la copia externa no coincide.');
        }
        $report = ['passed' => true, 'destination' => 'google_drive', 'file_id' => $id,
            'key' => $manifest['key'], 'source_environment' => $manifest['environment'],
            'archive_sha256' => $manifest['archive_sha256'], 'bytes' => filesize($archive),
            'verified_at' => now()->utc()->toIso8601String()];
        file_put_contents($directory.'/drive-manifest.json', json_encode(['backup' => $manifest, 'verification' => $report], JSON_UNESCAPED_SLASHES));
        chmod($directory.'/drive-manifest.json', 0600);
        $report['manifest_file_id'] = $this->upload($token, basename($manifest['key']).'.json', 'application/json', $directory.'/drive-manifest.json');
        return $report;
    }
}
