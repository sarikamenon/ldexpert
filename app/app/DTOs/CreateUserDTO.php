<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $username = '',
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $email = (string) $data['email'];

        return new self(
            name: (string) $data['name'],
            email: $email,
            password: (string) $data['password'],
            username: (string) ($data['username'] ?? $email),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username !== '' ? $this->username : $this->email,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
