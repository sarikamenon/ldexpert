<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Settings\Repositories\SettingsRepositoryInterface;
use App\Domain\Settings\Services\SettingsService;
use App\Models\Setting;
use Mockery;
use Tests\TestCase;

final class SettingsServiceTest extends TestCase
{
    public function test_get_group_returns_settings_array(): void
    {
        $repository = Mockery::mock(SettingsRepositoryInterface::class);
        $repository->shouldReceive('getGroup')
            ->once()
            ->with('general')
            ->andReturn(['site_name' => 'Test Site', 'support_email' => 'test@example.com']);

        $service = new SettingsService($repository);
        $result = $service->getGroup('general');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('site_name', $result);
        $this->assertSame('Test Site', $result['site_name']);
    }

    public function test_get_all_groups_returns_all_groups(): void
    {
        $repository = Mockery::mock(SettingsRepositoryInterface::class);
        $repository->shouldReceive('getGroup')
            ->with('general')
            ->andReturn(['site_name' => 'Test Site']);
        $repository->shouldReceive('getGroup')
            ->with('email')
            ->andReturn(['smtp_host' => 'smtp.example.com']);
        $repository->shouldReceive('getGroup')
            ->with('system')
            ->andReturn(['maintenance_mode' => false]);

        $service = new SettingsService($repository);
        $result = $service->getAllGroups();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('general', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('system', $result);
    }

    public function test_update_settings_calls_repository(): void
    {
        $repository = Mockery::mock(SettingsRepositoryInterface::class);
        $setting = new Setting;
        $repository->shouldReceive('set')
            ->with('site_name', 'New Site', 'string', 'general', false)
            ->once()
            ->andReturn($setting);
        $repository->shouldReceive('set')
            ->with('support_email', 'new@example.com', 'string', 'general', false)
            ->once()
            ->andReturn($setting);
        $repository->shouldReceive('set')
            ->with('maintenance_mode', true, 'boolean', 'system', false)
            ->once()
            ->andReturn($setting);

        $service = new SettingsService($repository);
        $service->updateSettings([
            'site_name' => 'New Site',
            'support_email' => 'new@example.com',
            'maintenance_mode' => true,
        ]);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
