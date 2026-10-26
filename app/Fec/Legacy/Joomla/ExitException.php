<?php

namespace App\Fec\Legacy\Joomla;

use RuntimeException;

/** Raised where legacy code would call jexit()/exit: the runner turns it into a response. */
final class ExitException extends RuntimeException
{
}
