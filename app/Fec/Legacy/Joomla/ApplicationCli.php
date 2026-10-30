<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JApplicationCli as cli/fec_generate_llmstxt.php uses it: getInstance($class)
 * builds the subclass, execute() runs its doExecute(), out() prints a line,
 * close($code) ends the run with that exit code (ExitException, which the CLI
 * runner turns into the command's status). No return types: the legacy subclass
 * overrides doExecute() without one.
 */
class ApplicationCli
{
    public static function getInstance($name = null)
    {
        $class = $name ?: static::class;

        return new $class;
    }

    public function execute()
    {
        $this->doExecute();
    }

    protected function doExecute()
    {
    }

    public function out($text = '', $nl = true)
    {
        echo $text.($nl ? "\n" : '');

        return $this;
    }

    public function close($code = 0)
    {
        throw new ExitException('', (int) $code);
    }
}
