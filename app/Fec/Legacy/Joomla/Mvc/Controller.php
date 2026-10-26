<?php

namespace App\Fec\Legacy\Joomla\Mvc;

use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\RedirectException;
use App\Fec\Legacy\Joomla\Request;
use App\Fec\Legacy\Joomla\Route;

/**
 * JController of Joomla 2.5, the parts our components use: getInstance()
 * (controller.php or controllers/<type>.php for "type.task"), execute(),
 * display() with model autobinding, getView()/getModel() class loading,
 * registerTask(), setRedirect()/redirect(). Classes are loaded from the
 * ported component under JPATH_COMPONENT.
 */
class Controller
{
    protected string $name;

    protected string $basePath;

    protected string $default_view;

    protected string $model_prefix;

    protected string $task = '';

    protected string $doTask = '';

    /** @var array<string, string> */
    protected array $taskMap = [];

    /** @var list<string> */
    protected array $methods = [];

    protected ?string $redirect = null;

    protected ?string $message = null;

    protected string $messageType = 'message';

    public \App\Fec\Legacy\Joomla\Input $input;

    /** @var array<string, self> */
    private static array $instances = [];

    public static function getInstance(string $prefix, array $config = [])
    {
        $task = Request::getCmd('task', '');
        $type = '';
        if (str_contains($task, '.')) {
            [$type, $task] = explode('.', $task, 2);
            Request::setVar('task', $task);
        }

        $base = $config['base_path'] ?? constant('JPATH_COMPONENT');
        if ($type !== '') {
            $file = $base.'/controllers/'.strtolower($type).'.php';
            $class = ucfirst($prefix).'Controller'.ucfirst($type);
        } else {
            $file = $base.'/controller.php';
            $class = ucfirst($prefix).'Controller';
        }
        if (! class_exists($class, false) && is_file($file)) {
            require_once $file;
        }
        if (! class_exists($class, false)) {
            throw new \RuntimeException('Legacy controller not found: '.$class.' ('.$file.')');
        }

        return self::$instances[$class] ??= new $class($config);
    }

    public function __construct(array $config = [])
    {
        $this->input = Factory::getApplication()->input;
        $this->basePath = $config['base_path'] ?? constant('JPATH_COMPONENT');

        if (isset($config['name'])) {
            $this->name = $config['name'];
        } elseif (preg_match('/^(.*)Controller/i', static::class, $m)) {
            $this->name = strtolower($m[1]);
        } else {
            $this->name = strtolower(static::class);
        }
        $this->default_view = $config['default_view'] ?? $this->name;
        $this->model_prefix = $config['model_prefix'] ?? ucfirst($this->name).'Model';

        foreach ((new \ReflectionClass($this))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if ($method->getDeclaringClass()->getName() !== self::class && ! str_starts_with($name, '_')) {
                $this->methods[] = strtolower($name);
                $this->registerTask($name, $name);
            }
        }
        $this->registerDefaultTask('display');
    }

    public function registerTask(string $task, string $method)
    {
        if (in_array(strtolower($method), $this->methods, true) || $method === 'display') {
            $this->taskMap[strtolower($task)] = $method;
        }

        return $this;
    }

    public function registerDefaultTask(string $method)
    {
        $this->taskMap['__default'] = $method;

        return $this;
    }

    public function execute(string $task)
    {
        $this->task = $task;
        $task = strtolower($task);
        $this->doTask = $this->taskMap[$task] ?? $this->taskMap['__default'] ?? 'display';

        return $this->{$this->doTask}();
    }

    public function display(bool $cachable = false, array|bool $urlparams = false)
    {
        $viewName = Request::getCmd('view', $this->default_view);
        $viewLayout = Request::getCmd('layout', 'default');
        $view = $this->getView($viewName, 'html', '', ['base_path' => $this->basePath, 'layout' => $viewLayout]);
        if ($model = $this->getModel($viewName)) {
            $view->setModel($model, true);
        }
        $view->document = Factory::getDocument();
        $view->display();

        return $this;
    }

    public function getView(string $name = '', string $type = 'html', string $prefix = '', array $config = [])
    {
        $name = $name !== '' ? $name : $this->name;
        $prefix = $prefix !== '' ? $prefix : ucfirst($this->name).'View';
        $class = $prefix.ucfirst($name);
        $file = ($config['base_path'] ?? $this->basePath).'/views/'.strtolower($name).'/view.'.$type.'.php';
        if (! class_exists($class, false) && is_file($file)) {
            require_once $file;
        }
        if (! class_exists($class, false)) {
            throw new \RuntimeException('Legacy view not found: '.$class.' ('.$file.')');
        }
        $config['base_path'] ??= $this->basePath;
        $config['name'] ??= $name;

        return new $class($config);
    }

    public function getModel(string $name = '', string $prefix = '', array $config = [])
    {
        $name = $name !== '' ? $name : $this->name;
        $prefix = $prefix !== '' ? $prefix : $this->model_prefix;
        $class = $prefix.ucfirst($name);
        $file = $this->basePath.'/models/'.strtolower($name).'.php';
        if (! class_exists($class, false) && is_file($file)) {
            require_once $file;
        }
        if (! class_exists($class, false)) {
            return false;
        }
        $config['name'] ??= $name;

        return new $class($config);
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setRedirect(string $url, ?string $msg = null, ?string $type = null)
    {
        $this->redirect = $url;
        if ($msg !== null) {
            $this->message = $msg;
        }
        if ($type !== null) {
            $this->messageType = $type;
        } elseif ($this->messageType === '') {
            $this->messageType = 'message';
        }

        return $this;
    }

    public function setMessage(string $text, string $type = 'message')
    {
        $previous = $this->message;
        $this->message = $text;
        $this->messageType = $type;

        return $previous;
    }

    /** Joomla's redirect(): nothing if no redirect was set, otherwise the application redirect (an exception here). */
    public function redirect()
    {
        if ($this->redirect === null) {
            return false;
        }
        $url = $this->redirect;
        if (str_starts_with($url, 'index.php')) {
            $url = Route::_($url, false);
        }
        if ($this->message !== null) {
            Factory::getApplication()->enqueueMessage($this->message, $this->messageType);
        }
        throw new RedirectException($url, 303);
    }

    public function addViewPath(string $path)
    {
        return $this;
    }

    public function addModelPath(string $path, string $prefix = '')
    {
        return $this;
    }
}
