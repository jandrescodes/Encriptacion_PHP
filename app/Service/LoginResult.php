<?php

declare(strict_types=1);

namespace App\Service;

final readonly class LoginResult
{
    /** @param array<string, mixed>|null $user */
    private function __construct(
        public bool $ok,
        public string $message,
        public string $icon,
        public ?array $user = null,
    ) {
    }

    /** @param array<string, mixed> $user */
    public static function success(string $message, array $user): self
    {
        return new self(true, $message, 'success', $user);
    }

    public static function failure(string $message, string $icon): self
    {
        return new self(false, $message, $icon);
    }
}
