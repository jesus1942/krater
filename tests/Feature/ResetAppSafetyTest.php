<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ResetAppSafetyTest extends TestCase
{
    public function test_reset_app_is_blocked_in_production_even_with_force(): void
    {
        $this->app->detectEnvironment(function () {
            return 'production';
        });

        Artisan::shouldReceive('call')->never();

        $this->artisan('reset:app', ['--force' => true])
            ->expectsOutput('reset:app esta bloqueado en produccion.')
            ->assertExitCode(1);
    }

    public function test_reset_app_keeps_its_destructive_flow_outside_production_when_forced(): void
    {
        $this->app->detectEnvironment(function () {
            return 'testing';
        });

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh --seed --force')
            ->ordered()
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'DemoSeeder', '--force' => true])
            ->ordered()
            ->andReturn(0);

        $this->artisan('reset:app', ['--force' => true])
            ->expectsOutput('Running migrate:fresh')
            ->expectsOutput('Seeding database')
            ->expectsOutput('App has been reset successfully')
            ->assertExitCode(0);
    }
}
