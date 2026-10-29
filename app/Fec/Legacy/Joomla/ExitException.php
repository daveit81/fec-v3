<?php

namespace App\Fec\Legacy\Joomla;

use Error;

/**
 * Raised where legacy code would call jexit()/exit: the runner turns it into a
 * response. An Error, as RedirectException: exit ended the request on the live
 * site, past any "catch (Exception $e)" of the legacy code around it.
 */
final class ExitException extends Error {}
