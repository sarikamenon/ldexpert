<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Repositories\SettingsRepositoryInterface;

final class SettingsService
{
    public function __construct(
        private readonly SettingsRepositoryInterface $repository,
    ) {}

    public function getGroup(string $group): array
    {
        return $this->repository->getGroup($group);
    }

    public function getAllGroups(): array
    {
        return [
            'general' => $this->getGroup('general'),
            'email' => $this->getGroup('email'),
            'system' => $this->getGroup('system'),
        ];
    }

    public function updateSettings(array $settings): void
    {
        if (isset($settings['site_name'])) {
            $this->repository->set('site_name', $settings['site_name'], 'string', 'general');
        }

        if (isset($settings['support_email'])) {
            $this->repository->set('support_email', $settings['support_email'], 'string', 'general');
        }

        if (isset($settings['records_per_page'])) {
            $this->repository->set('records_per_page', $settings['records_per_page'], 'integer', 'general');
        }

        if (isset($settings['maintenance_mode'])) {
            $this->repository->set('maintenance_mode', $settings['maintenance_mode'], 'boolean', 'system');
        }

        if (isset($settings['smtp_host'])) {
            $this->repository->set('smtp_host', $settings['smtp_host'], 'string', 'email');
        }

        if (isset($settings['smtp_port'])) {
            $this->repository->set('smtp_port', $settings['smtp_port'], 'integer', 'email');
        }

        if (isset($settings['smtp_username'])) {
            $this->repository->set('smtp_username', $settings['smtp_username'], 'string', 'email');
        }

        if (isset($settings['smtp_password'])) {
            $this->repository->set('smtp_password', $settings['smtp_password'], 'string', 'email', true);
        }
    }
}
