<?php

namespace App\Fec\Legacy\Joomla\Mvc;

use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\Pagination;
use App\Fec\Legacy\Joomla\Request;

/**
 * JModelList of Joomla 2.5.28 (libraries/joomla/application/component/modellist.php),
 * as com_partnerdesk's dashboard uses it (phase 5): the context "com_x.name", the
 * stateful list (list.limit from the user state or configuration.php's list_limit,
 * list.start, list.ordering/direction checked against filter_fields), getItems(),
 * getTotal(), getStart(), getPagination() and the store ids that cache them, and
 * getUserStateFromRequest() that resets the page when a filter changes.
 */
#[\AllowDynamicProperties]
class ModelList extends Model
{
    /** @var array<string, mixed> */
    protected $cache = [];

    protected $context = null;

    /** @var list<string> */
    protected $filter_fields = [];

    protected $query = [];

    /** JModel's guess of the component, from the class prefix: PartnerdeskModelDashboard -> com_partnerdesk. */
    protected $option = null;

    private ?string $lastStoreId = null;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (isset($config['filter_fields'])) {
            $this->filter_fields = (array) $config['filter_fields'];
        }
        if (empty($this->option)) {
            $class = substr((string) strrchr('\\'.static::class, '\\'), 1);
            $this->option = preg_match('/(.*)Model/i', $class, $m) ? 'com_'.strtolower($m[1]) : '';
        }
        if (empty($this->context)) {
            $this->context = strtolower($this->option.'.'.$this->getName());
        }
    }

    protected function _getListQuery()
    {
        $current = $this->getStoreId();
        if ($this->lastStoreId !== $current || empty($this->query)) {
            $this->lastStoreId = $current;
            $this->query = $this->getListQuery();
        }

        return $this->query;
    }

    public function getItems()
    {
        $store = $this->getStoreId();
        if (isset($this->cache[$store])) {
            return $this->cache[$store];
        }
        $items = $this->_getList($this->_getListQuery(), (int) $this->getStart(), (int) $this->getState('list.limit'));
        if ($this->_db->getErrorNum()) {
            $this->setError($this->_db->getErrorMsg());

            return false;
        }

        return $this->cache[$store] = $items;
    }

    protected function getListQuery()
    {
        return $this->getDbo()->getQuery(true);
    }

    public function getPagination()
    {
        $store = $this->getStoreId('getPagination');
        if (isset($this->cache[$store])) {
            return $this->cache[$store];
        }
        $limit = (int) $this->getState('list.limit') - (int) $this->getState('list.links');

        return $this->cache[$store] = new Pagination((int) $this->getTotal(), (int) $this->getStart(), $limit);
    }

    protected function getStoreId($id = '')
    {
        $id .= ':'.$this->getState('list.start');
        $id .= ':'.$this->getState('list.limit');
        $id .= ':'.$this->getState('list.ordering');
        $id .= ':'.$this->getState('list.direction');

        return md5($this->context.':'.$id);
    }

    public function getTotal()
    {
        $store = $this->getStoreId('getTotal');
        if (isset($this->cache[$store])) {
            return $this->cache[$store];
        }
        $total = (int) $this->_getListCount($this->_getListQuery());
        if ($this->_db->getErrorNum()) {
            $this->setError($this->_db->getErrorMsg());

            return false;
        }

        return $this->cache[$store] = $total;
    }

    public function getStart()
    {
        $store = $this->getStoreId('getstart');
        if (isset($this->cache[$store])) {
            return $this->cache[$store];
        }
        $start = (int) $this->getState('list.start');
        $limit = (int) $this->getState('list.limit');
        $total = (int) $this->getTotal();
        if ($limit > 0 && $start > $total - $limit) {
            $start = max(0, (int) (ceil($total / $limit) - 1) * $limit);
        }

        return $this->cache[$store] = $start;
    }

    protected function populateState($ordering = null, $direction = null)
    {
        if ($this->context) {
            $app = Factory::getApplication();
            $limit = (int) $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'uint');
            $this->setState('list.limit', $limit);

            $value = (int) $app->getUserStateFromRequest($this->context.'.limitstart', 'limitstart', 0);
            $this->setState('list.start', $limit !== 0 ? (int) (floor($value / $limit) * $limit) : 0);

            $value = $app->getUserStateFromRequest($this->context.'.ordercol', 'filter_order', $ordering);
            if (! in_array($value, $this->filter_fields, true)) {
                $value = $ordering;
                $app->setUserState($this->context.'.ordercol', $value);
            }
            $this->setState('list.ordering', $value);

            $value = $app->getUserStateFromRequest($this->context.'.orderdirn', 'filter_order_Dir', $direction);
            if (! in_array(strtoupper((string) $value), ['ASC', 'DESC', ''], true)) {
                $value = $direction;
                $app->setUserState($this->context.'.orderdirn', $value);
            }
            $this->setState('list.direction', $value);
        } else {
            $this->setState('list.start', 0);
            $this->setState('list.limit', 0);
        }
    }

    /** JModelList::getUserStateFromRequest(): a filter that changes sends the list back to its first page. */
    public function getUserStateFromRequest($key, $request, $default = null, $type = 'none', $resetPage = true)
    {
        $app = Factory::getApplication();
        $old = $app->getUserState($key);
        $current = $old !== null ? $old : $default;
        $new = Request::getVar($request, null, 'default', $type);
        if ($current != $new && $resetPage) {
            Request::setVar('limitstart', 0);
        }
        if ($new !== null) {
            $app->setUserState($key, $new);
        } else {
            $new = $current;
        }

        return $new;
    }
}
