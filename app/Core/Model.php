<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    public function __construct(protected \mysqli $db)
    {
    }
}
