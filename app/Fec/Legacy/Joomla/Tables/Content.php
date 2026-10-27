<?php

namespace App\Fec\Legacy\Joomla\Tables;

use App\Fec\Legacy\Joomla\Database;
use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\FilterOutput;
use App\Fec\Legacy\Joomla\Table;

/**
 * JTableContent (Joomla 2.5): an article row of #__content with its asset
 * "com_content.article.<id>" under the category's asset. check() wants a
 * title, derives the alias, refuses a publish_down before publish_up and
 * cleans the meta fields; store() stamps modified/modified_by on an update
 * and created/created_by on an insert, and refuses an alias already used in
 * the same category. The blog desk saves posts through it.
 */
class Content extends Table
{
    protected bool $_trackAssets = true;

    public function __construct(Database $db)
    {
        parent::__construct('#__content', 'id', $db);
    }

    public function bind(array|object $src, array|string $ignore = []): bool
    {
        if (is_object($src)) {
            $src = get_object_vars($src);
        }
        foreach (['attribs', 'metadata', 'images', 'urls'] as $json) {
            if (isset($src[$json]) && is_array($src[$json])) {
                $src[$json] = (string) json_encode($src[$json], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }
        if (isset($src['rules']) && is_array($src['rules'])) {
            $this->_rules = (string) json_encode($src['rules']);
        }

        return parent::bind($src, $ignore);
    }

    public function check(): bool
    {
        if (trim((string) $this->title) === '') {
            $this->setError('Please provide a valid title');

            return false;
        }
        if (trim((string) $this->alias) === '') {
            $this->alias = $this->title;
        }
        $this->alias = FilterOutput::stringURLSafe((string) $this->alias);
        if (trim(str_replace('-', '', $this->alias)) === '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }
        if (trim(str_replace('&nbsp;', '', (string) $this->fulltext)) === '') {
            $this->fulltext = '';
        }
        $null = $this->_db->getNullDate();
        if ((string) $this->publish_down > $null && (string) $this->publish_down < (string) $this->publish_up) {
            $this->setError('Finish publishing must be after start publishing');

            return false;
        }
        if (! empty($this->metakey)) {
            // keywords: no line breaks, one space after each comma, no empty entries
            $bad = ["\n", "\r", "\"", '<', '>'];
            $after = str_replace($bad, '', (string) $this->metakey);
            $keys = array_filter(array_map('trim', explode(',', $after)), fn ($k) => $k !== '');
            $this->metakey = implode(', ', $keys);
        }
        if (! empty($this->metadesc)) {
            $this->metadesc = str_ireplace(["\"", '<', '>'], '', (string) $this->metadesc);
        }

        return true;
    }

    public function store(bool $updateNulls = false): bool
    {
        $date = Factory::getDate();
        $user = Factory::getUser();
        if ($this->id) {
            $this->modified = $date->toSql();
            $this->modified_by = (int) $user->get('id');
        } else {
            if (! intval((string) $this->created)) {
                $this->created = $date->toSql();
            }
            if (empty($this->created_by)) {
                $this->created_by = (int) $user->get('id');
            }
        }

        // the alias is unique within the category
        $other = new self($this->_db);
        if ($other->load(['alias' => $this->alias, 'catid' => (int) $this->catid]) && ((int) $other->id !== (int) $this->id || empty($this->id))) {
            $this->setError('Another article in this category has this alias');

            return false;
        }

        return parent::store($updateNulls);
    }

    protected function _getAssetName(): string
    {
        return 'com_content.article.'.(int) $this->id;
    }

    protected function _getAssetTitle(): ?string
    {
        return (string) $this->title;
    }

    /** The category's asset; the root asset when the category has none. */
    protected function _getAssetParentId(?Table $table = null, ?int $id = null): int
    {
        if ((int) $this->catid > 0) {
            $this->_db->setQuery('SELECT asset_id FROM '.$this->_db->quoteName('#__categories').' WHERE id = '.(int) $this->catid);
            $assetId = (int) $this->_db->loadResult();
            if ($assetId > 0) {
                return $assetId;
            }
        }

        return parent::_getAssetParentId($table, $id);
    }
}
