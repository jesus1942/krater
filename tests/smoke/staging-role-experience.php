<?php

// Ensayo HTTP reproducible en la base ficticia de staging. Nunca es un endpoint.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Crater\Services\Access\StagingAccessFixtures;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Se corta antes de consultar o escribir; no existe --force ni URL configurable.
if (! app()->environment('staging') || DB::connection()->getDatabaseName() !== 'krater_staging') {
    fwrite(STDERR, "El ensayo exige staging y krater_staging.\n"); exit(1);
}
$company = DB::table('companies')->where('unique_hash', 'suiteena-staging-fixture')->value('id');
if (! $company) { fwrite(STDERR, "Falta la institucion ficticia.\n"); exit(1); }
$fixtures = app(StagingAccessFixtures::class)->prepareRoleExperience((int) $company);
$secret = config('staging.admin_password');
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:18992', '-t', 'public', 'server.php'], base_path());
$client = new GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:18992', 'http_errors' => false, 'timeout' => 15]);
$tokens = []; $checks = []; $assignment = null; $directorToken = null;

/** Solo registra metodo, ruta y estado; nunca respuestas con PII o tokens. */
function callRoute($client, string $method, string $path, int $expected, array $options = []): array
{
    global $checks;
    $response = $client->request($method, $path, $options);
    $actual = $response->getStatusCode();
    $checks[] = ['method' => $method, 'path' => $path, 'expected' => $expected, 'actual' => $actual];
    if ($actual !== $expected) throw new RuntimeException($method.' '.$path.' devolvio '.$actual.'; se esperaba '.$expected);
    return json_decode((string) $response->getBody(), true) ?: [];
}

/** Solicitudes con contexto y token unicamente en memoria. */
function authOptions(string $token, array $payload = [], ?int $level = null): array
{
    global $company, $fixtures;
    $options = ['headers' => ['Accept' => 'application/json', 'company' => (string) $company,
        'school-level' => (string) ($level ?? $fixtures['level']), 'Authorization' => 'Bearer '.$token]];
    if ($payload) $options['json'] = $payload;
    return $options;
}

try {
    $server->start();
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try { if ($client->get('/ping')->getStatusCode() === 200) break; } catch (Throwable $e) {}
        usleep(100000);
    }
    foreach (['preceptor', 'registrar', 'teacher', 'director', 'vice'] as $key) {
        $email = $key.'.r2b.staging@example.invalid';
        $login = callRoute($client, 'POST', '/api/v1/auth/login', 200, ['json' => [
            'username' => $email, 'password' => hash_hmac('sha256', $email, $secret), 'device_name' => 'r2b-smoke']]);
        $tokens[$key] = $login['token'];
    }
    $directorToken = $tokens['director'];
    foreach (['preceptor', 'teacher'] as $key) {
        $options = authOptions($tokens[$key]);
        foreach (['bootstrap', 'me', 'me/settings?settings%5B0%5D=language', 'languages', 'school-levels', 'students', 'academic-years', 'grade-levels', 'subjects',
            'study-plans', 'divisions', 'enrollments?division_id='.$fixtures['division']] as $path) {
            callRoute($client, 'GET', '/api/v1/'.$path, 200, $options);
        }
        foreach (['dashboard', 'search', 'students/placement-options', 'users', 'backups'] as $path) {
            callRoute($client, 'GET', '/api/v1/'.$path, 403, $options);
        }
    }
    $role = (int) DB::table('roles')->where('company_id', $company)->where('name', 'preceptor_registrar')->value('id');
    $grant = ['role_id' => $role, 'school_level_id' => $fixtures['level'], 'division_id' => $fixtures['division']];
    callRoute($client, 'POST', '/api/v1/users/'.$fixtures['registrar'].'/role-assignments', 403, authOptions($tokens['vice'], $grant));
    $assignment = callRoute($client, 'POST', '/api/v1/users/'.$fixtures['registrar'].'/role-assignments', 201,
        authOptions($directorToken, $grant))['id'];
    $options = authOptions($tokens['registrar']);
    foreach (['bootstrap', 'me', 'me/settings?settings%5B0%5D=language', 'languages', 'school-levels', 'students', 'academic-years',
        'grade-levels', 'subjects', 'study-plans', 'divisions', 'enrollments?division_id='.$fixtures['division']] as $path) {
        callRoute($client, 'GET', '/api/v1/'.$path, 200, $options);
    }
    $placements = callRoute($client, 'GET', '/api/v1/students/placement-options', 200, $options);
    if (array_column($placements['divisions'], 'id') !== [$fixtures['division']]) throw new RuntimeException('El selector amplifico el alcance.');
    $payload = ['first_name' => 'Ensayo ficticio', 'last_name' => 'R2b', 'dni' => 'FICTICIO-R2B-'.bin2hex(random_bytes(5)),
        'academic_year_id' => $fixtures['year'], 'grade_level_id' => $fixtures['grade'], 'division_id' => $fixtures['division'], 'status' => 'active'];
    $id = callRoute($client, 'POST', '/api/v1/students', 201, authOptions($tokens['registrar'], $payload))['student']['id'];
    $basic = ['first_name' => 'Ensayo actualizado', 'last_name' => 'R2b'];
    callRoute($client, 'PUT', '/api/v1/students/'.$id, 200, authOptions($tokens['registrar'], $basic));
    $out = $payload; $out['dni'] .= '-OTRO'; $out['division_id'] = $fixtures['other_division'];
    callRoute($client, 'POST', '/api/v1/students', 403, authOptions($tokens['registrar'], $out));
    callRoute($client, 'DELETE', '/api/v1/students/'.$id, 403, $options);
    callRoute($client, 'PUT', '/api/v1/students/'.$id.'/relocate', 403, authOptions($tokens['registrar'], $payload));
    callRoute($client, 'PUT', '/api/v1/students/'.$id, 403, authOptions($tokens['registrar'], $basic + ['status' => 'withdrawn']));
    $secondary = (int) DB::table('school_levels')->where('company_id', $company)->where('code', 'secondary')->value('id');
    callRoute($client, 'GET', '/api/v1/students', 403, authOptions($tokens['registrar'], [], $secondary));
    callRoute($client, 'POST', '/api/v1/role-assignments/'.$assignment.'/revoke', 200, authOptions($directorToken));
    $assignment = null;
    callRoute($client, 'POST', '/api/v1/students', 403, authOptions($tokens['registrar'], $payload));
    echo 'R2b HTTP staging: '.json_encode(['passed' => true, 'checks' => $checks], JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    // Las excepciones de transporte pueden incluir encabezados; no se imprimen.
    fwrite(STDERR, 'R2b HTTP staging fallo despues de '.count($checks).' verificaciones. Revisar estados, sin imprimir secretos.'."\n");
    fwrite(STDERR, json_encode(['checks' => $checks], JSON_UNESCAPED_SLASHES)."\n");
    $failed = true;
} finally {
    if ($assignment && $directorToken) {
        try { callRoute($client, 'POST', '/api/v1/role-assignments/'.$assignment.'/revoke', 200, authOptions($directorToken)); }
        catch (Throwable $e) { $failed = true; }
    }
    // Solo tokens creados por esta corrida, nunca sesiones de otras personas.
    foreach ($tokens as $token) {
        $tokenId = explode('|', $token, 2)[0];
        DB::table('personal_access_tokens')->where('id', $tokenId)->where('name', 'r2b-smoke')->delete();
    }
    $server->stop();
}
exit(empty($failed) ? 0 : 1);
