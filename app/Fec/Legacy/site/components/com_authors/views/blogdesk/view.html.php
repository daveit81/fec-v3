<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.view');
jimport('joomla.html.pagination');

require_once JPATH_COMPONENT . '/helpers/blogdesk.php';
require_once JPATH_COMPONENT . '/models/blogdesk.php';

/**
 * Blog desk view (V2.7.66) — /index.php?option=com_authors&view=blogdesk
 *
 *   layout=default   the author's posts (search, state filter, pagination)
 *   layout=edit      the editor: title, body, image, a "Details" fold with
 *                    type / provider / date / byline (desk users only)
 *
 * Access: AuthorsHelperBlogdesk::context(); a guest gets a 403 with the
 * login link, a logged-in user without a profile a plain 403.
 */
class AuthorsViewBlogdesk extends JView
{
    public $ctx        = null;
    public $posts      = array();
    public $total      = 0;
    public $pagination = null;
    public $search     = '';
    public $filter     = 'all';
    public $post       = null;     // edit: existing row or null (new post)
    public $form       = array();  // edit: field values
    public $detected   = '';
    public $lastUrl    = '';
    public $providers  = array();
    public $kinds      = array();
    public $slots      = array();  // 2.7.72: gid => label of every #__slot_rtp slot
    public $slotRels   = array();
    public $bylines    = array();
    public $token      = '';
    public $imageSrc   = '';
    public $notice     = null;     // array(type, text, url) from the last save

    public function display($tpl = null)
    {
        $doc = JFactory::getDocument();
        $doc->setMetaData('robots', 'noindex, nofollow');

        $this->ctx = AuthorsHelperBlogdesk::context();
        if (!$this->ctx['allowed']) {
            $doc->setTitle('Blog desk — access denied');
            $this->setLayout('default');
            parent::display('403');
            return;
        }

        $base = JURI::root(true) . '/components/com_authors/assets/';
        $assetsPath = JPATH_SITE . '/components/com_authors/assets/';
        $doc->addStyleSheet($base . 'blogdesk.css?v=' . (@filemtime($assetsPath . 'blogdesk.css') ?: 1));
        $doc->addScript($base . 'blogdesk.js?v=' . (@filemtime($assetsPath . 'blogdesk.js') ?: 1));

        $this->token = JUtility::getToken();
        $app = JFactory::getApplication();
        $n = $app->getUserState('com_authors.blogdesk.notice');
        $this->notice = is_array($n) ? $n : null;
        $app->setUserState('com_authors.blogdesk.notice', null);
        if ($this->notice === null) {
            // session-free fallback (2.7.70): the controller appends &n= to the redirect
            $flag = JRequest::getCmd('n', '', 'get');
            $texts = array('pub' => array('ok', 'Published.'), 'sched' => array('ok', 'Scheduled.'), 'draft' => array('info', 'Draft saved.'), 'err' => array('error', 'The post was not saved.'));
            if (isset($texts[$flag])) $this->notice = array('type' => $texts[$flag][0], 'text' => $texts[$flag][1], 'url' => '');
        }
        $model = new AuthorsModelBlogdesk();
        AuthorsHelper::loadBlogHelper();

        if ($this->getLayout() === 'edit') {
            $this->prepareEdit($model);
            $doc->setTitle(($this->post ? 'Edit post' : 'New post') . ' — Blog desk');
        } else {
            $this->prepareList($model);
            $doc->setTitle('Blog desk — Casino Bonus Blog');
        }
        parent::display($tpl);
    }

    protected function prepareList($model)
    {
        $this->search = JRequest::getString('q', '', 'get');
        $f = JRequest::getCmd('filter', 'all', 'get');
        $this->filter = in_array($f, array('all', 'published', 'draft', 'scheduled'), true) ? $f : 'all';
        $limit = AuthorsModelBlogdesk::LIST_LIMIT;
        $start = max(0, (int) JRequest::getInt('limitstart', 0));
        list($this->posts, $this->total) = $model->listPosts($this->ctx, $this->search, $this->filter, $limit, $start);
        if ($start >= $this->total && $this->total > 0) {
            $start = 0;
            list($this->posts, $this->total) = $model->listPosts($this->ctx, $this->search, $this->filter, $limit, 0);
        }
        $this->pagination = new JPagination($this->total, $start, $limit);
    }

    protected function prepareEdit($model)
    {
        $app = JFactory::getApplication();
        $id  = (int) JRequest::getInt('id', 0);
        $this->post = null;
        if ($id > 0) {
            $this->post = $model->loadPost($id);
            if (!$this->post || !AuthorsModelBlogdesk::canEdit($this->ctx, $this->post)) {
                $app->setUserState('com_authors.blogdesk.notice', array('type' => 'error', 'text' => 'That post is not yours to edit.', 'url' => ''));
                $app->redirect(AuthorsHelperBlogdesk::deskUrl());
                return;
            }
        }

        $tz = new DateTimeZone(JFactory::getConfig()->get('offset', 'UTC'));
        $toLocal = function ($utcSql) use ($tz) {
            if ($utcSql === '' || strpos($utcSql, '0000-00-00') === 0) return '';
            try { $d = new DateTime($utcSql, new DateTimeZone('UTC')); } catch (Exception $e) { return ''; }
            $d->setTimezone($tz);
            return $d->format('Y-m-d\TH:i');
        };

        $meta = ($this->post && isset($this->post['meta'])) ? $this->post['meta'] : array();
        $form = array(
            'id'         => $id,
            'title'      => $this->post ? (string) $this->post['title'] : '',
            'body'       => $this->post ? AuthorsModelBlogdesk::editableBody($this->post) : '',
            'mode'       => 'auto',
            'state'      => $this->post ? (int) $this->post['state'] : 1,
            'publish_up' => $this->post ? $toLocal((string) $this->post['publish_up']) : '',
            'kind'       => $this->post && !empty($meta['kind']) ? (string) $meta['kind'] : '',
            'provider'   => ($this->post && !empty($meta['provider_slug'])) ? (string) $meta['provider_slug'] : '',
            'byline'     => '',
            'slot'       => ($this->post && !empty($meta['slot_gid'])) ? (string) (int) $meta['slot_gid'] : '',
            'slot_rel'   => ($this->post && !empty($meta['slot_rel'])) ? (string) $meta['slot_rel'] : '',
        );

        // a failed save keeps what was typed
        $draft = $app->getUserState('com_authors.blogdesk.draft');
        if (is_array($draft) && (int) $draft['id'] === $id) {
            foreach (array('title', 'body', 'mode', 'publish_up', 'kind', 'provider', 'byline', 'slot', 'slot_rel') as $k) if (isset($draft[$k])) $form[$k] = $draft[$k];
            $form['state'] = isset($draft['state']) ? (int) $draft['state'] : $form['state'];
            $app->setUserState('com_authors.blogdesk.draft', null);
        }
        $this->form = $form;

        $this->lastUrl = $this->post ? AuthorsHelperBlogdesk::postUrl($this->post) : '';

        $this->detected = ($this->post && $meta) ? AuthorsHelperBlogtext::describeMeta($meta) : '';
        $this->providers = AuthorsHelperBlogdesk::providerOptions();
        $this->kinds = AuthorsHelperBlogdesk::kindOptions();
        $this->slots = AuthorsHelperBlogslot::options();
        $this->slotRels = AuthorsHelperBlogslot::relOptions();
        $this->bylines = !empty($this->ctx['is_desk']) ? AuthorsHelperBlogdesk::activeAuthors() : array();

        $this->imageSrc = '';
        if ($this->post) {
            $images = json_decode((string) $this->post['images'], true);
            $path = (is_array($images) && !empty($images['image_intro'])) ? (string) $images['image_intro'] : '';
            if ($path !== '') {
                $this->imageSrc = function_exists('fec_blog_image_src') ? fec_blog_image_src($path, 480, 288) : JURI::root(true) . '/' . ltrim($path, '/');
            }
        }
    }
}
