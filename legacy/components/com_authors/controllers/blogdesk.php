<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/helpers/blogdesk.php';
require_once JPATH_COMPONENT . '/models/blogdesk.php';

/**
 * Blog desk controller (V2.7.66).
 *
 *   task=blogdesk.save      POST: title, body, mode, state, publish_up, kind,
 *                           provider, byline (desk only), remove_image, image (file)
 *   task=blogdesk.cancel    back to the list
 *
 * Both tasks require a logged-in user allowed by AuthorsHelperBlogdesk::context()
 * and a valid form token. After a save the editor comes back to the same
 * post with a message that says what was detected (kind, provider, Game
 * facts) and links to the public page.
 */
class AuthorsControllerBlogdesk extends JController
{
    public function save()
    {
        $app = JFactory::getApplication();
        $ctx = AuthorsHelperBlogdesk::context();
        if (!$ctx['allowed']) {
            $this->notice('error', 'You cannot use the blog desk with this account.');
            $this->setRedirect(AuthorsHelperBlogdesk::deskUrl());
            return;
        }
        if (!JRequest::checkToken('post')) {
            $this->notice('error', 'The form expired. Your text is still in the browser: go back, copy it and retry.');
            $this->setRedirect(AuthorsHelperBlogdesk::deskUrl());
            return;
        }

        $id   = (int) JRequest::getVar('id', 0, 'post', 'int');
        $data = array(
            'id'           => $id,
            'title'        => JRequest::getVar('title', '', 'post', 'string', JREQUEST_ALLOWRAW),
            'body'         => JRequest::getVar('body', '', 'post', 'string', JREQUEST_ALLOWRAW),
            'mode'         => JRequest::getCmd('mode', 'auto', 'post'),
            'state'        => ($this->requestedState() === 'draft') ? 0 : 1,
            'publish_up'   => JRequest::getVar('publish_up', '', 'post', 'string'),
            'kind'         => JRequest::getCmd('kind', '', 'post'),
            'provider'     => JRequest::getVar('provider', '', 'post', 'string'),
            'byline'       => JRequest::getVar('byline', '', 'post', 'string'),
            'slot'         => JRequest::getVar('slot', '', 'post', 'string'),
            'slot_rel'     => JRequest::getCmd('slot_rel', '', 'post'),
            'remove_image' => (int) JRequest::getVar('remove_image', 0, 'post', 'int'),
        );
        $file = JRequest::getVar('image', null, 'files', 'array');

        $model = new AuthorsModelBlogdesk();
        $res = $model->save($data, $ctx, $file);
        error_log(sprintf('blogdesk save user=%d id=%d state=%s ok=%d %s', (int) $ctx['user']->id, (int) $res['id'], $data['state'] === 0 ? 'draft' : 'publish', $res['ok'] ? 1 : 0, $res['ok'] ? '' : $res['error']));

        if (!$res['ok']) {
            // keep what was typed: stash the form in the session for the edit view
            $app->setUserState('com_authors.blogdesk.draft', $data);
            $this->notice('error', $res['error']);
            $this->setRedirect(AuthorsHelperBlogdesk::editUrl($id) . '&n=err');
            return;
        }

        $what = $res['state'] === 0 ? 'Draft saved' : ($res['scheduled'] ? 'Scheduled' : 'Published');
        $msg  = $what . '. Detected: ' . $res['detected'] . '.';
        if (!empty($res['warning'])) $msg .= ' ' . $res['warning'];
        $app->setUserState('com_authors.blogdesk.draft', null);
        $this->notice($res['state'] === 0 ? 'info' : 'ok', $msg, $res['url']);
        // &n=... lets the edit view say what happened even when the session
        // notice does not survive the redirect
        $this->setRedirect(AuthorsHelperBlogdesk::editUrl($res['id']) . '&n=' . ($res['state'] === 0 ? 'draft' : ($res['scheduled'] ? 'sched' : 'pub')));
    }

    public function cancel()
    {
        JFactory::getApplication()->setUserState('com_authors.blogdesk.draft', null);
        $this->setRedirect(AuthorsHelperBlogdesk::deskUrl());
    }

    /**
     * publish | draft. The Draft checkbox (task_state=draft) decides: a
     * checked checkbox is an ordinary form field, so it survives the
     * form.submit() a site script performs on this form (which drops the
     * clicked button, 2.7.69/2.7.70). task_state_btn is kept for a
     * script-free submit of an older template.
     */
    protected function requestedState()
    {
        $btn = JRequest::getCmd('task_state_btn', '', 'post');
        if ($btn === 'draft' || $btn === 'publish') return $btn;
        return (JRequest::getCmd('task_state', 'publish', 'post') === 'draft') ? 'draft' : 'publish';
    }

    /**
     * Desk notices travel in the user state, not in the Joomla message queue:
     * whether the site template renders that queue is not ours to know.
     */
    protected function notice($type, $text, $url = '')
    {
        JFactory::getApplication()->setUserState('com_authors.blogdesk.notice', array('type' => $type, 'text' => $text, 'url' => $url));
    }
}
