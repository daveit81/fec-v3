<?php
defined('_JEXEC') or die;

class FecsearchViewResults extends JViewLegacy
{
  public $q = '';

  public function display($tpl = null)
  {
    $app = JFactory::getApplication();
    $this->q = trim($app->input->getString('q', ''));

    // Title + noindex (internal tool page)
    $doc = JFactory::getDocument();
    $qSafe = $this->q !== '' ? $this->q : '';
    if ($qSafe !== '') {
      $doc->setTitle("Search results for '" . htmlspecialchars($qSafe, ENT_QUOTES, 'UTF-8') . "'");
      $doc->setDescription("Internal search results for '" . htmlspecialchars($qSafe, ENT_QUOTES, 'UTF-8') . "'.");
    } else {
      $doc->setTitle('Search results');
      $doc->setDescription('Internal search results.');
    }
    $doc->setMetaData('robots', 'noindex, nofollow');

    parent::display($tpl);
  }
}
