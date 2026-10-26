<?php

namespace App\Fec\Legacy\Joomla\Mvc;

use App\Fec\Legacy\Joomla\Document;
use App\Fec\Legacy\Joomla\Factory;

/**
 * JView of Joomla 2.5: display() echoes loadTemplate(), which includes
 * views/<name>/tmpl/<layout>[_<tpl>].php with $this bound to the view;
 * setLayout/getLayout, get() through the default model, getModel/setModel,
 * assign/assignRef, escape(). Template overrides (templates/fecv2/html/)
 * are honoured when the ported site tree carries them.
 */
#[\AllowDynamicProperties]
class View
{
    protected string $_name;

    protected string $_basePath;

    protected string $_layout = 'default';

    protected string $_layoutExt = 'php';

    protected string $_template = '';

    protected string $_output = '';

    protected string $_charset = 'UTF-8';

    protected string $_escape = 'htmlspecialchars';

    /** @var array<string, Model> */
    protected array $_models = [];

    protected string $_defaultModel = '';

    /** @var list<string> */
    protected array $_templatePaths = [];

    public ?Document $document = null;

    public function __construct(array $config = [])
    {
        if (isset($config['name'])) {
            $this->_name = strtolower($config['name']);
        } elseif (preg_match('/View(.*)$/i', static::class, $m)) {
            $this->_name = strtolower($m[1]);
        } else {
            $this->_name = strtolower(static::class);
        }
        $this->_basePath = $config['base_path'] ?? constant('JPATH_COMPONENT');
        if (! empty($config['layout'])) {
            $this->setLayout((string) $config['layout']);
        }
        $this->document = Factory::getDocument();

        $component = basename($this->_basePath);
        $this->_templatePaths = array_filter([
            constant('JPATH_THEMES').'/fecv2/html/'.$component.'/'.$this->_name,
            $this->_basePath.'/views/'.$this->_name.'/tmpl',
        ], 'is_dir');
        foreach ((array) ($config['template_path'] ?? []) as $path) {
            array_unshift($this->_templatePaths, $path);
        }
    }

    public function display(?string $tpl = null)
    {
        echo $this->loadTemplate($tpl);
    }

    public function loadTemplate(?string $tpl = null)
    {
        $file = $tpl !== null && $tpl !== '' ? $this->_layout.'_'.$tpl : $this->_layout;
        $file = preg_replace('/[^A-Z0-9_\.-]/i', '', $file);
        $found = null;
        foreach ($this->_templatePaths as $dir) {
            $candidate = $dir.'/'.$file.'.'.$this->_layoutExt;
            if (is_file($candidate)) {
                $found = $candidate;
                break;
            }
        }
        if ($found === null) {
            throw new \RuntimeException('Legacy layout "'.$file.'" not found for view '.$this->_name);
        }
        $this->_template = $found;

        unset($tpl, $file, $found, $dir, $candidate);
        ob_start();
        try {
            include $this->_template;
        } finally {
            $this->_output = (string) ob_get_clean();
        }

        return $this->_output;
    }

    public function setLayout(string $layout)
    {
        if (str_contains($layout, ':')) {
            [, $layout] = explode(':', $layout, 2);
        }
        $previous = $this->_layout;
        $this->_layout = $layout;

        return $previous;
    }

    public function getLayout()
    {
        return $this->_layout;
    }

    public function setLayoutExt(string $value)
    {
        $previous = $this->_layoutExt;
        $this->_layoutExt = $value;

        return $previous;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getModel(?string $name = null)
    {
        $name = strtolower($name ?? $this->_defaultModel);

        return $this->_models[$name] ?? null;
    }

    public function setModel(Model $model, bool $default = false)
    {
        $name = strtolower($model->getName());
        $this->_models[$name] = $model;
        if ($default) {
            $this->_defaultModel = $name;
        }

        return $model;
    }

    /** Joomla's get(): the default model's get<Property>(), then the view property. */
    public function get(string $property, ?string $default = null)
    {
        $model = $this->getModel($default);
        if ($model !== null) {
            $method = 'get'.ucfirst($property);
            if (method_exists($model, $method)) {
                return $model->{$method}();
            }
        }

        return $this->{$property} ?? null;
    }

    public function assign(string|array $name, mixed $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->{$k} = $v;
            }

            return true;
        }
        $this->{$name} = $value;

        return true;
    }

    public function assignRef(string $name, mixed &$value)
    {
        $this->{$name} = &$value;

        return true;
    }

    public function escape(mixed $var)
    {
        return htmlspecialchars((string) $var, ENT_COMPAT, $this->_charset);
    }

    public function addTemplatePath(string|array $path)
    {
        foreach ((array) $path as $p) {
            array_unshift($this->_templatePaths, rtrim($p, '/'));
        }
    }

    public function _addPath(string $type, string|array $path)
    {
        if ($type === 'template') {
            $this->addTemplatePath($path);
        }
    }

    public function setEscape(string $function)
    {
        $this->_escape = $function;
    }
}
