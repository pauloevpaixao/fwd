<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Environment;

class ResetTest extends TestCase
{
    public function testReset()
    {
        $this->artisan('reset')->assertExitCode(0);

        $this->assertReset();
    }

    public function testResetWithClear()
    {
        $this->artisan('reset --clear')->assertExitCode(0);

        $this->assertReset();

        $this->assertCommandCalled('artisan', ['clear-compiled']);
        $this->assertCommandCalled('artisan', ['cache:clear']);
        $this->assertCommandCalled('artisan', ['config:clear']);
        $this->assertCommandCalled('artisan', ['route:clear']);
        $this->assertCommandCalled('artisan', ['view:clear']);
    }

    public function testResetWithClearLogs()
    {
        $this->artisan('reset --clear-logs')->assertExitCode(0);

        $this->assertReset();

        $this->assertDockerComposeExec(
            'app rm -f',
            base_path('storage/logs/*.log')
        );
    }

    public function testResetWithNoSeed()
    {
        $this->artisan('reset --no-seed')->assertExitCode(0);

        $this->assertReset(true);
    }

    public function testResetWithDusk()
    {
        $this->mock(Environment::class, function ($mock) {
            $mock->shouldReceive('getContextEnv')
                ->once()
                ->with('.env.dusk.local')
                ->andReturn(base_path('tests/fixtures/.env.dusk.local'));
        })->makePartial();

        $this->artisan('reset .env.dusk.local')->assertExitCode(0);

        $this->assertCommandCalled('composer', ['install']);
        $this->assertCommandCalled('mysql-raw', ['-e', 'drop database if exists dusk']);
        $this->assertCommandCalled('mysql-raw', ['-e', 'create database dusk']);
        $this->assertCommandCalled('mysql-raw', ['-e', 'grant all on dusk.* to docker@"%"']);

        $this->assertDockerComposeExec(
            '-e DB_DATABASE=dusk',
            '-e DB_USERNAME=docker',
            '-e DB_PASSWORD=secret',
            'app php artisan migrate:fresh --seed'
        );

        $this->assertCommandCalled('yarn', ['install']);
        $this->assertCommandCalled('yarn', ['dev']);
    }

    protected function assertReset($noSeed = false)
    {
        $this->assertCommandCalled('composer', ['install']);
        $this->assertCommandCalled('mysql-raw', ['-e', 'drop database if exists docker']);
        $this->assertCommandCalled('mysql-raw', ['-e', 'create database docker']);
        $this->assertCommandCalled('mysql-raw', ['-e', 'grant all on docker.* to docker@"%"']);

        $this->assertDockerComposeExec(
            '-e DB_DATABASE=docker',
            '-e DB_USERNAME=docker',
            '-e DB_PASSWORD=secret',
            'app php artisan migrate:fresh ' . (! $noSeed ? '--seed' : '')
        );

        $this->assertCommandCalled('yarn', ['install']);
        $this->assertCommandCalled('yarn', ['dev']);
    }
}
