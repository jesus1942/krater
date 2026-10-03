<?php

// Recorrido exclusivo de staging: MySQL real, sin endpoints de depuración.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/../Support/InstitutionScenario.php';

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\InstitutionScenario;

// Corte antes de cualquier lectura/escritura; no existe --force.
if (! app()->environment('staging') || DB::connection()->getDatabaseName() !== 'krater_staging') {
    fwrite(STDERR, "El recorrido exige staging y krater_staging.\n"); exit(1);
}
$company = DB::table('companies')->where('unique_hash', 'suiteena-staging-fixture')->first();
if (! $company) { fwrite(STDERR, "Falta la institución ficticia.\n"); exit(1); }
$customer = DB::table('users')->where('company_id', $company->id)->where('email', 'family.primary.staging@example.invalid')->value('id');
if (! $customer) { fwrite(STDERR, "Falta la familia ficticia.\n"); exit(1); }
$fixture = InstitutionScenario::fixture((int) $company->id, (int) $customer);
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:18993', '-t', 'public', 'server.php'], base_path());
$client = new GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:18993', 'http_errors' => false,
    'timeout' => 30, 'allow_redirects' => false,
    // Cliente ficticio separado del smoke de roles: sus seis ingresos no deben
    // consumir el cupo del formulario web (5/15). El limitador sigue activo.
    'headers' => ['X-Forwarded-For' => '127.0.0.2']]);
// El servidor de ensayo es loopback HTTP; las cookies de staging son Secure.
// Conserva y devuelve los valores cifrados en memoria, sin alterar la configuración
// ni el middleware de sesión/CSRF de la aplicación.
$cookies = [];
$callHttp = function ($method, $path, $options = []) use ($client, &$cookies) {
    if ($cookies) $options['headers']['Cookie'] = implode('; ', array_map(
        fn ($name, $value) => $name.'='.$value, array_keys($cookies), array_values($cookies)));
    $response = $client->request($method, $path, $options);
    foreach ($response->getHeader('Set-Cookie') as $header) {
        $cookie = GuzzleHttp\Cookie\SetCookie::fromString($header);
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    return $response;
};
$token = null; $failed = false; $lastRequest = 'preparación'; $requestCount = 0;
try {
    $server->start();
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try { if ($client->get('/ping')->getStatusCode() === 200) break; } catch (Throwable $e) {}
        usleep(100000);
    }
    $secret = config('staging.admin_password');
    $login = $client->post('/api/v1/auth/login', ['json' => ['username' => 'total-admin.staging@example.invalid',
        'password' => $secret, 'device_name' => 'institution-smoke'], 'headers' => ['Accept' => 'application/json']]);
    $token = (json_decode((string) $login->getBody(), true) ?: [])['token'] ?? null;
    if ($login->getStatusCode() !== 200 || ! $token) throw new RuntimeException('Falló el ingreso API ficticio.');
    // Los informes web exigen una sesión real además del token de API.
    $html = (string) $callHttp('GET', '/login')->getBody();
    if (! preg_match('/name="csrf-token"\s+content="([^"]+)"/', $html, $csrf)) throw new RuntimeException('No se encontró el CSRF del ingreso.');
    $web = $callHttp('POST', '/login', ['form_params' => ['email' => 'total-admin.staging@example.invalid',
        'password' => $secret, '_token' => $csrf[1]]]);
    if ($web->getStatusCode() !== 302) throw new RuntimeException('Falló el ingreso web ficticio: HTTP '.$web->getStatusCode().'.');
    $result = InstitutionScenario::run(function ($method, $path, $payload, $level) use ($callHttp, $token, $company, &$lastRequest, &$requestCount) {
        $lastRequest = $method.' '.$path;
        $requestCount++;
        $options = ['headers' => ['Accept' => str_starts_with($path, '/reports/') ? 'application/pdf' : 'application/json',
            'company' => (string) $company->id, 'school-level' => $level === null ? '' : (string) $level,
            'Authorization' => 'Bearer '.$token]];
        if ($payload) $options['json'] = $payload;
        // Mantiene el limitador real. Si el smoke anterior consumió el cupo,
        // espera su Retry-After y repite una vez; nunca reintenta un 403.
        usleep(400000);
        $response = $callHttp($method, $path, $options);
        if ($response->getStatusCode() === 429) {
            $seconds = max(1, min(60, (int) $response->getHeaderLine('Retry-After')));
            usleep($seconds * 1000000);
            $response = $callHttp($method, $path, $options);
        }
        $body = (string) $response->getBody();
        return ['status' => $response->getStatusCode(), 'json' => json_decode($body, true), 'body' => $body];
    }, $fixture, (int) $company->id, $company->unique_hash);
    echo 'Toda la institución HTTP staging: '.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    $failed = true;
    $detail = get_class($e) === RuntimeException::class ? $e->getMessage() : get_class($e);
    if (preg_match('/cURL error (\d+)/', $e->getMessage(), $curl)) $detail .= ' (cURL '.$curl[1].')';
    fwrite(STDERR, 'Toda la institución HTTP staging falló tras '.$requestCount.' solicitudes; última: '.$lastRequest.'. '.$detail."\n");
} finally {
    if ($token) DB::table('personal_access_tokens')->where('id', explode('|', $token, 2)[0])->where('name', 'institution-smoke')->delete();
    $server->stop();
}
exit($failed ? 1 : 0);
