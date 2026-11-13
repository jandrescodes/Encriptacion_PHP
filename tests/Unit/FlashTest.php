<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Flash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function it_writes_the_session_keys_read_by_the_layout(): void
    {
        Flash::warning('Cuidado');

        $this->assertSame('Cuidado', $_SESSION['message']);
        $this->assertSame('warning', $_SESSION['icon']);
    }

    #[Test]
    public function each_shortcut_sets_its_icon(): void
    {
        foreach (['success', 'error', 'warning', 'info'] as $icon) {
            Flash::$icon("msg {$icon}");

            $this->assertSame($icon, $_SESSION['icon']);
        }
    }

    #[Test]
    public function pull_returns_the_flash_once_and_clears_it(): void
    {
        Flash::success('Hecho');

        $this->assertSame(['message' => 'Hecho', 'icon' => 'success'], Flash::pull());
        $this->assertNull(Flash::pull());
        $this->assertArrayNotHasKey('message', $_SESSION);
        $this->assertArrayNotHasKey('icon', $_SESSION);
    }

    #[Test]
    public function pull_ignores_a_message_without_icon(): void
    {
        $_SESSION['message'] = 'huérfano';

        $this->assertNull(Flash::pull());
    }
}
