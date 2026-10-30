<?php

namespace App\Fec\Legacy\Joomla;

use App\Fec\Legacy\Joomla\Mvc\Controller;
use App\Fec\Legacy\Joomla\Mvc\Model;
use App\Fec\Legacy\Joomla\Mvc\ModelList;
use App\Fec\Legacy\Joomla\Mvc\View;

/**
 * The compatibility layer's switchboard.
 *
 * Our legacy components were written against Joomla 2.5 and use a small,
 * known set of its classes (JFactory, JRequest, JUri, JRoute, JDocument,
 * JPagination, the MVC bases, the database API). This namespace provides
 * classes with the same names and the signatures our code actually calls,
 * backed by Laravel; nothing here is Joomla. aliases() binds the global
 * names once per process; boot() defines the constants and the helpers a
 * component file expects when it is included (_JEXEC, JPATH_*, jimport)
 * and resets the per-request singletons.
 *
 * JPATH_SITE points at app/Fec/Legacy/site: a virtual docroot that holds
 * only the ported components and the lib slices, so every legacy
 * require_once JPATH_SITE . '/components/...' resolves to ported code.
 * JPATH_ROOT is the real legacy docroot instead (fec.legacy.docroot): our
 * code uses it only for files (images/rtp, images/blog), which must land
 * where the live hostname serves them, not inside the repository.
 */
final class Runtime
{
    private static bool $aliased = false;

    private static bool $booted = false;

    /** @var array<string, class-string> global Joomla name => our class */
    public const ALIASES = [
        'JFactory' => Factory::class,
        'JRequest' => Request::class,
        'JUri' => Uri::class,
        'JURI' => Uri::class,
        'JRoute' => Route::class,
        'JDocument' => Document::class,
        'JDocumentHtml' => Document::class,
        'JRegistry' => Registry::class,
        'JDate' => Date::class,
        'JPagination' => Pagination::class,
        'JHtml' => Html::class,
        'JHTML' => Html::class,
        'JText' => Text::class,
        'JSession' => Session::class,
        'JUtility' => Utility::class,
        'JComponentHelper' => ComponentHelper::class,
        'JFilterOutput' => FilterOutput::class,
        'JString' => Str::class,
        'JResponse' => Response::class,
        'JError' => Error::class,
        'JException' => Exception::class,
        'JLoader' => Loader::class,
        'JFilterInput' => FilterInput::class,
        'JTable' => Table::class,
        'JTableContent' => Tables\Content::class,
        'JTableAsset' => Tables\Asset::class,
        'JController' => Controller::class,
        'JControllerLegacy' => Controller::class,
        'JView' => View::class,
        'JViewLegacy' => View::class,
        'JModel' => Model::class,
        'JModelLegacy' => Model::class,
        'JModelList' => ModelList::class,
        'JPlugin' => Plugin::class,
        'JPluginHelper' => PluginHelper::class,
        'JModuleHelper' => ModuleHelper::class,
        'JDispatcher' => Dispatcher::class,
        'JCategories' => Categories::class,
        'JCategoryNode' => CategoryNode::class,
        'JMail' => Mail::class,
        'JApplicationCli' => ApplicationCli::class,
        'JLog' => Log::class,
    ];

    /** Bind the global names. Safe to call more than once; never loaded where a real Joomla runs. */
    public static function aliases(): void
    {
        if (self::$aliased) {
            return;
        }
        foreach (self::ALIASES as $global => $class) {
            if (! class_exists($global, false)) {
                class_alias($class, $global);
            }
        }
        self::$aliased = true;
    }

    /** The virtual docroot of the ported legacy code. */
    public static function sitePath(): string
    {
        return rtrim((string) config('fec.legacy.site_path', app_path('Fec/Legacy/site')), '/');
    }

    /** The real legacy docroot: uploaded files live there (JPATH_ROOT). */
    public static function docroot(): string
    {
        $root = rtrim((string) config('fec.legacy.docroot', ''), '/');

        return $root !== '' ? $root : self::sitePath();
    }

    /**
     * Prepare the process for including legacy component files and reset the
     * per-request state (document head, request vars, menu, errors).
     */
    public static function boot(string $component): void
    {
        self::environment();
        $site = self::sitePath();

        // Component paths change per dispatched component; the constants are
        // defined once per process (PHP-FPM/LiteSpeed serve one request per
        // process lifetime of these globals, and the CLI runs one component).
        self::constant('JPATH_COMPONENT', $site.'/components/'.$component);
        self::constant('JPATH_COMPONENT_SITE', $site.'/components/'.$component);
        self::constant('JPATH_COMPONENT_ADMINISTRATOR', $site.'/administrator/components/'.$component);

        Factory::reset();
        Request::reset();
        Route::reset();
        Uri::reset();
        Controller::reset();
        Dispatcher::reset();
        PluginHelper::reset();
        Categories::reset();
    }

    /**
     * The global names, the constants (_JEXEC, DS, JPATH_*) and the global helpers a
     * legacy file expects when it is included, without touching any per-request state.
     * TemplateLib needs exactly this: the lib starts with "defined('_JEXEC') or
     * die(...)", and the 404 page renders the shell's newsletter blocks with no
     * component run before it (a die() there sent a 200 and half a page).
     */
    public static function environment(): void
    {
        self::aliases();
        if (self::$booted) {
            return;
        }
        $site = self::sitePath();
        self::constant('_JEXEC', 1);
        self::constant('DS', DIRECTORY_SEPARATOR);
        self::constant('JPATH_ROOT', self::docroot());
        self::constant('JPATH_SITE', $site);
        self::constant('JPATH_BASE', $site);
        self::constant('JPATH_ADMINISTRATOR', $site.'/administrator');
        self::constant('JPATH_THEMES', $site.'/templates');
        self::constant('JPATH_CACHE', storage_path('framework/cache/legacy'));
        // jimport() and the other global helpers our legacy code calls.
        require_once __DIR__.'/functions.php';
        self::$booted = true;
    }

    private static function constant(string $name, mixed $value): void
    {
        if (! defined($name)) {
            define($name, $value);
        }
    }
}
