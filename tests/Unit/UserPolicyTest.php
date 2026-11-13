<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Policy\UserPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserPolicyTest extends TestCase
{
    #[Test]
    public function an_admin_cannot_delete_their_own_account(): void
    {
        $policy = new UserPolicy();

        $this->assertFalse($policy->canDelete(1, 1));
        $this->assertTrue($policy->canDelete(1, 2));
    }

    #[Test]
    public function an_admin_cannot_remove_their_own_admin_flag(): void
    {
        $policy = new UserPolicy();

        $this->assertFalse($policy->canSetAdminFlag(1, 1, false));
        $this->assertTrue($policy->canSetAdminFlag(1, 1, true));
        $this->assertTrue($policy->canSetAdminFlag(1, 2, false));
    }
}
