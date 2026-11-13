<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\PasswordPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    #[Test]
    public function it_rejects_passwords_shorter_than_the_minimum(): void
    {
        $this->assertSame('Password must be at least 8 characters long', PasswordPolicy::validate('1234567'));
        $this->assertNotNull(PasswordPolicy::validate(''));
    }

    #[Test]
    public function it_accepts_the_minimum_length(): void
    {
        $this->assertNull(PasswordPolicy::validate('12345678'));
    }
}
