<?php

namespace Tests\Isolation;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;

class PlatformCleanupTest extends TestCase
{
    use CreatesApplication;

    public function test_actualizador_de_crater_eliminado(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('#^api/v1/(check/update|update/)#', $route->uri());
        }
        $this->assertArrayNotHasKey('crater:update', Artisan::all());
        $this->assertFalse(class_exists('Crater\\Space\\Updater'));
        $this->assertFileDoesNotExist(resource_path('assets/js/views/settings/UpdateAppSetting.vue'));
    }

    public function test_api_inexistente_responde_404_json(): void
    {
        foreach (['/api/v1/check/update', '/api/v1/no-existe', '/api/otra/cosa'] as $uri) {
            $this->getJson($uri)->assertStatus(404)->assertExactJson(['message' => 'Not Found.']);
            $this->postJson($uri)->assertStatus(404)->assertExactJson(['message' => 'Not Found.']);
        }
        $this->getJson('/api/ping')->assertOk();
    }

    public function test_canal_stderr_separa_info_de_warning(): void
    {
        $stdout = tempnam(sys_get_temp_dir(), 'out');
        $stderr = tempnam(sys_get_temp_dir(), 'err');
        config(['logging.channels.stderr.stdout' => $stdout, 'logging.channels.stderr.stderr' => $stderr]);

        $log = Log::channel('stderr');
        $log->info('linea informativa');
        $log->notice('aviso');
        $log->warning('advertencia');
        $log->error('falla');

        $out = file_get_contents($stdout);
        $err = file_get_contents($stderr);
        $this->assertStringContainsString('linea informativa', $out);
        $this->assertStringContainsString('aviso', $out);
        $this->assertStringNotContainsString('advertencia', $out);
        $this->assertStringNotContainsString('falla', $out);
        $this->assertStringContainsString('advertencia', $err);
        $this->assertStringContainsString('falla', $err);
        $this->assertStringNotContainsString('linea informativa', $err);
        @unlink($stdout);
        @unlink($stderr);
    }

    public function test_php_fpm_registra_solo_warning_y_access_log_a_stdout(): void
    {
        $conf = file_get_contents(base_path('php-fpm-logging.conf'));
        $this->assertMatchesRegularExpression('/^log_level = warning$/m', $conf);
        $this->assertMatchesRegularExpression('#^access\.log = /proc/self/fd/1$#m', $conf);
        $this->assertStringContainsString(
            'php-fpm-logging.conf /usr/local/etc/php-fpm.d/zz-ena-logging.conf',
            file_get_contents(base_path('Dockerfile'))
        );
    }
}
