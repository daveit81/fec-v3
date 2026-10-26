<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * com_atlas front controller.
 *
 * FEC Operator Atlas — Phase 2: editorial dashboard only.
 * Phase 3 will add the public views (operators, operator, changelog)
 * and the sh404SEF routes under /operators/.
 *
 * The whitelist exists so a malformed URL cannot dispatch to an
 * unintended view; everything off-list falls back to the dashboard
 * (which itself 403s anyone not on the editor whitelist).
 */
class AtlasController extends JController
{
  protected $allowedViews = array(
    'operators', 'operator', 'changelog',
    'dashboard',
  );

  public function display($cachable = false, $urlparams = false)
  {
    $view = JRequest::getCmd('view', 'operators');
    if (!in_array($view, $this->allowedViews, true)) {
      $view = 'operators';
    }

    JRequest::setVar('view', $view);
    parent::display($cachable, $urlparams);
    return $this;
  }
}
