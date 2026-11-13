<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

// Sustituye al exit posterior a redirect(): permite cortar el flujo del controller sin matar PHPUnit.
final class RedirectCaptured extends RuntimeException
{
}
