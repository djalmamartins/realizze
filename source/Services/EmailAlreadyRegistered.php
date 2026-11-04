<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;

final class EmailAlreadyRegistered extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('E-mail já cadastrado.');
    }
}
