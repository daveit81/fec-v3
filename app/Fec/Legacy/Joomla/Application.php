<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JApplication (site) for a component: the menu, the input, redirects and
 * messages. A redirect or close() throws, and the runner turns the
 * exception into the HTTP response; nothing here ends the PHP process.
 */
class Application
{
    public Input $input;

    private ?Menu $menu = null;

    /** @var list<array{message: string, type: string}> */
    private array $messages = [];

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> JApplication's registry: what the code set() for a later get() (fecclustermeta) */
    private array $registry = [];

    public function __construct()
    {
        $this->input = new Input;
    }

    /**
     * JSite::initialise(): language, session, user and the system plugins' onAfterInitialise.
     * The standalone endpoints (r.php, subscribe.php) call it before their work; none of them
     * reads what it prepared, so nothing to do.
     */
    public function initialise(array $options = []): void
    {
    }

    public function getMenu(?string $name = null, array $options = []): Menu
    {
        return $this->menu ??= new Menu;
    }

    public function getName(): string
    {
        return 'site';
    }

    public function isSite(): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function getCfg(string $varname, mixed $default = null): mixed
    {
        return Factory::getConfig()->get($varname, $default);
    }

    public function getTemplate(bool $params = false): string|object
    {
        return $params ? (object) ['template' => 'fecv2', 'params' => new Registry] : 'fecv2';
    }

    /** @var array<string, Registry> JSite::getParams() is computed once per option */
    private array $params = [];

    /**
     * JSite::getParams() of Joomla 2.5: the component's options with the active
     * menu item's params merged over them, and the page_title (the menu item's
     * title, else the site name), page_description (the site's MetaDesc),
     * page_rights and robots defaults the views read.
     */
    public function getParams(?string $option = null): Registry
    {
        $option = $option !== null && $option !== '' ? $option : (string) Request::getCmd('option', '');
        if (! isset($this->params[$option])) {
            $params = clone ComponentHelper::getParams($option);
            $config = Factory::getConfig();
            $title = (string) $config->get('sitename', '');
            $menu = $this->getMenu()->getActive();
            if (is_object($menu)) {
                $params->merge($menu->params instanceof Registry ? $menu->params : new Registry((string) $menu->params));
                $title = (string) $menu->title;
            } else {
                $temp = clone ComponentHelper::getParams('com_menus');
                $params->merge($temp);
                $title = (string) $temp->get('page_title', $title);
            }
            $params->def('page_title', $title);
            $params->def('page_description', (string) $config->get('MetaDesc', ''));
            $params->def('page_rights', (string) $config->get('MetaRights', ''));
            $params->def('robots', (string) $config->get('robots', ''));
            $this->params[$option] = $params;
        }

        return $this->params[$option];
    }

    public function getPathway(): Pathway
    {
        return new Pathway;
    }

    public function getRouter(): object
    {
        return new \stdClass;
    }

    /** Joomla 2.5: redirect($url, $msg = '', $msgType = 'message', $moved = false) */
    public function redirect(string $url, string $msg = '', string $msgType = 'message', bool $moved = false): never
    {
        if ($msg !== '') {
            $this->enqueueMessage($msg, $msgType);
        }
        throw new RedirectException($url, $moved ? 301 : 303);
    }

    public function enqueueMessage(string $msg, string $type = 'message'): void
    {
        $this->messages[] = ['message' => $msg, 'type' => $type];
    }

    /** @return list<array{message: string, type: string}> */
    public function getMessageQueue(): array
    {
        return $this->messages;
    }

    public function setHeader(string $name, string $value, bool $replace = false): void
    {
        $this->headers[$name] = $value;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function sendHeaders(): void
    {
    }

    public function close(int $code = 0): never
    {
        throw new ExitException('application closed');
    }

    public function getUserState(string $key, mixed $default = null): mixed
    {
        return Factory::getSession()->get($key, $default, 'state');
    }

    public function setUserState(string $key, mixed $value): mixed
    {
        return Factory::getSession()->set($key, $value, 'state');
    }

    public function getUserStateFromRequest(string $key, string $request, mixed $default = null, string $type = 'none'): mixed
    {
        $old = $this->getUserState($key, $default);
        $new = Request::getVar($request, $old, 'default', $type);
        $this->setUserState($key, $new);

        return $new;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->registry) ? $this->registry[$key] : $default;
    }

    public function set(string $key, mixed $value = null): mixed
    {
        $previous = $this->registry[$key] ?? null;
        $this->registry[$key] = $value;

        return $previous;
    }

    /** The plugins imported so far (JPluginHelper::importPlugin) answer the event, in order. */
    public function triggerEvent(string $event, ?array $args = null): array
    {
        return Dispatcher::getInstance()->trigger($event, $args ?? []);
    }

    public function registerEvent(string $event, mixed $handler): void
    {
    }
}
