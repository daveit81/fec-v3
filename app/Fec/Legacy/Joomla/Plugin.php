<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JPlugin of Joomla 2.5: the base of our plugins (plgContentFeccounts,
 * plgSystemFecclustermeta...). The constructor takes the dispatcher and the
 * plugin row (name, type, params as the JSON of #__extensions.params), as
 * JPluginHelper built it; loadLanguage() is a no-op (no language files).
 */
#[\AllowDynamicProperties]
class Plugin
{
    protected ?Registry $params = null;

    protected string $_name = '';

    protected string $_type = '';

    public function __construct(mixed &$subject, array|object $config = [])
    {
        $config = (array) $config;
        if (isset($config['params'])) {
            $this->params = $config['params'] instanceof Registry ? $config['params'] : new Registry((string) $config['params']);
        } else {
            $this->params = new Registry;
        }
        $this->_name = (string) ($config['name'] ?? '');
        $this->_type = (string) ($config['type'] ?? '');
    }

    public function loadLanguage(string $extension = '', string $basePath = ''): bool
    {
        return true;
    }
}
