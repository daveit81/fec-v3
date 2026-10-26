<?php
/**
 * AuthorsViewMethodologyadmin
 *
 * Frontend admin for the methodology topic registry. Lists all topics in
 * #__methodology_topics, lets authorised editors edit metadata (status,
 * title, tagline, description, author, reviewer, version, last_updated)
 * and bump last_updated to today with a single click.
 *
 * The body content of each methodology page (the prose sections in
 * tmpl/default_<slug>.php) remains in the template file. This view
 * controls only the metadata layer — which is what changes most often.
 *
 * URL: /index.php?option=com_authors&view=methodologyadmin
 *      &edit=<slug>  → renders the edit form
 *      &saved=1      → renders the list with a success flash
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/methodology.php';
require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsViewMethodologyadmin extends JView
{
    public function display($tpl = null)
    {
        // Auth gate — same as the editorial dashboard
        if (!AuthorsModelDashboard::isAuthorized()) {
            throw new Exception('Forbidden', 403);
        }

        $model = new AuthorsModelMethodology();

        $this->topics  = $model->getTopics();
        $this->authors = $this->fetchActiveAuthors();

        // Edit mode
        $editSlug = trim((string)JRequest::getVar('edit', ''));
        if ($editSlug !== '' && isset($this->topics[$editSlug])) {
            $this->editSlug  = $editSlug;
            $this->editTopic = $this->topics[$editSlug];
            $tpl = 'edit';
        } else {
            $this->editSlug  = '';
            $this->editTopic = null;
        }

        $this->saved = (int)JRequest::getVar('saved', 0) === 1;
        $this->error = trim((string)JRequest::getVar('error', ''));

        // Doc setup
        $doc = JFactory::getDocument();
        $doc->setTitle('Methodology pages · Admin · FreeExtraChips');
        $doc->setMetaData('robots', 'noindex,nofollow');

        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/methodology.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/methodology.css') ?: 1)
        );
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/dashboard.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/dashboard.css') ?: 1)
        );

        parent::display($tpl);
    }

    /**
     * Active authors keyed by slug — populates the author/reviewer dropdowns.
     */
    protected function fetchActiveAuthors()
    {
        $db = JFactory::getDbo();
        $db->setQuery(
            "SELECT slug, name, role
             FROM #__casino_authors
             WHERE is_active = 1
             ORDER BY name ASC"
        );
        $rows = (array)$db->loadAssocList();
        $out = array();
        foreach ($rows as $r) {
            if (empty($r['slug'])) continue;
            $out[$r['slug']] = $r;
        }
        return $out;
    }
}
