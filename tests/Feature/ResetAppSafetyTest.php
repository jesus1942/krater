<?php

namespace Tests\Feature;

use Crater\Console\Commands\ResetApp;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class ResetAppSafetyTest extends TestCase
{
    public function test_reset_app_is_blocked_in_production_even_with_force(): void
    {
        $this->app->detectEnvironment(function () {
            return 'production';
        });

        Artisan::shouldReceive('call')->never();

        $tester = $this->commandTester();

        $status = $tester->execute(['--force' => true]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'reset:app esta bloqueado en produccion.',
            $tester->getDisplay()
        );
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

        $tester = $this->commandTester();

        $status = $tester->execute(['--force' => true]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Running migrate:fresh', $tester->getDisplay());
        $this->assertStringContainsString('Seeding database', $tester->getDisplay());
        $this->assertStringContainsString('App has been reset successfully', $tester->getDisplay());
    }

    private function commandTester(): CommandTester
    {
        $command = new ResetApp();
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }
}
