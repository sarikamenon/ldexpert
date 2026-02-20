<?php

declare(strict_types=1);

namespace App\DTOs;

final class SendInvoiceDTO
{
    public function __construct(
        public readonly ?string $email = null,
        public readonly ?string $message = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? null,
            message: $data['message'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'message' => $this->message,
        ];
    }
}
