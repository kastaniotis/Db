<?php

namespace Iconic\Db\Exception;

class TooManyResultsException extends GeneralDatabaseException {
    public const MESSAGE = 'database.result.too-many';
}