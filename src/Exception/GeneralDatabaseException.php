<?php

namespace Iconic\Db\Exception;

use Exception;
use RuntimeException;

abstract class GeneralDatabaseException extends RuntimeException {
    public const MESSAGE = 'database.general.error';
    public function __construct(Exception $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }
}