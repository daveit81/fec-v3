<?php
/**
 * AuthorsControllerMethodologyadmin
 *
 * Save endpoint for the frontend methodology topic editor. Standard form
 * POST (not AJAX) — receives the edit form, validates, persists via
 * AuthorsModelMethodology::saveTopic(), redirects back to the list with
 * a flash flag.
 *
 * Tasks:
 *   save  — validate + persist + redirect
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/methodology.php';
require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsControllerMethodologyadmin extends JController
{
    public function save()
    {
        // Auth gate
        if (!AuthorsModelDashboard::isAuthorized()) {
            JFactory::getApplication()->enqueueMessage('Forbidden', 'error');
            $this->setRedirect(JRoute::_('index.php?option=com_authors&view=methodologyadmin', false));
            return;
        }

        // CSRF
        if (!JRequest::checkToken('post')) {
            $this->redirectWithError('Invalid form token. Please retry.');
            return;
        }

        $slug = trim((string)JRequest::getVar('slug', '', 'post'));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->redirectWithError('Invalid slug.');
            return;
        }

        $data = array(
            'slug'              => $slug,
            'status'            => (string)JRequest::getVar('status',            'coming_soon', 'post'),
            'title'             => (string)JRequest::getVar('title',             '', 'post', 'string'),
            'tagline'           => (string)JRequest::getVar('tagline',           '', 'post', 'string'),
            'short_description' => (string)JRequest::getVar('short_description', '', 'post', 'string'),
            'description'       => (string)JRequest::getVar('description',       '', 'post', 'string'),
            'author_slug'       => (string)JRequest::getVar('author_slug',       '', 'post'),
            'reviewer_slug'     => (string)JRequest::getVar('reviewer_slug',     '', 'post'),
            'version'           => (string)JRequest::getVar('version',           '', 'post'),
            'last_updated'      => (string)JRequest::getVar('last_updated',      '', 'post'),
            'tmpl'              => (string)JRequest::getVar('tmpl',              '', 'post'),
            'external_url'      => (string)JRequest::getVar('external_url',      '', 'post', 'string'),
            'sort_order'        => (int)   JRequest::getVar('sort_order',        0,  'post', 'int'),
        );

        // Minimum required
        if (trim($data['title']) === '') {
            $this->redirectWithError('Title is required.');
            return;
        }

        $model  = new AuthorsModelMethodology();
        $result = $model->saveTopic($data);

        if ($result === false) {
            $this->redirectWithError('Could not save changes. Check the error log.');
            return;
        }

        // Success → list with flash
        $this->setRedirect(
            JRoute::_('index.php?option=com_authors&view=methodologyadmin&saved=1', false),
            'Saved "' . $data['title'] . '".'
        );
    }

    protected function redirectWithError($msg)
    {
        $this->setRedirect(
            JRoute::_('index.php?option=com_authors&view=methodologyadmin&error=' . urlencode($msg), false)
        );
    }
}
