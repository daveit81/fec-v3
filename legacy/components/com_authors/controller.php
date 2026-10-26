<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * com_authors front controller.
 *
 * V1: dispatches to view=authors (team listing) and view=author (single profile).
 * V2.1: adds view=methodology + view=methodologyhub (drives /methodology/<topic>).
 * V2.2: adds view=page (editorial / trust / public-service pages — RG, About, Disclosure).
 * V2.4: adds view=dashboard (editorial cockpit).
 * V2.6: adds view=slot + view=slots (RTP database).
 * V2.7.6: adds view=methodologyadmin (metadata editor for trust hub topics).
 * V2.7.13: adds view=fxshieldadmin (FXShield status cockpit, read-only).
 * V2.7.66: adds view=blogdesk (front-end editor of the Casino Bonus Blog).
 *
 * The whitelist exists so a malformed URL can't dispatch to an unintended
 * view; everything off-list falls back to the team listing.
 */
class AuthorsController extends JController
{
  protected $allowedViews = array(
    'authors', 'author', 'methodology', 'methodologyhub', 'page',
    'dashboard', 'slot', 'slots', 'methodologyadmin', 'fxshieldadmin',
    'verification', 'fxreport', 'deskadd', 'blogdesk',
  );

  public function display($cachable = false, $urlparams = false)
  {
    $view = JRequest::getCmd('view', 'authors');
    if (!in_array($view, $this->allowedViews, true)) {
      $view = 'authors';
    }

    JRequest::setVar('view', $view);
    parent::display($cachable, $urlparams);
    return $this;
  }
}
