<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JDatabaseException: what Joomla 2.5.28's driver threw on a failing query
 * (message = the server's error plus " SQL=" and the statement, code = the
 * server's error number). Thrown by the layer only where it is switched on
 * (Database::throwErrors(), the standalone endpoints).
 */
class DatabaseException extends \Exception
{
}
