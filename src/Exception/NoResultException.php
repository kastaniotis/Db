<?php

namespace Iconic\Db\Exception;

use Exception;

class NoResultException extends Exception
{
    public const MESSAGE = 'database.result.empty';

    public function __construct(Exception $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }
}