<?php
defined('_JEXEC') or die;

class FecsearchControllerApi extends JControllerLegacy
{
  public function search()
  {
      
    if ($option === 'com_fecsearch' || $option === 'com_search') {
    $doc->setMetaData('robots', 'noindex, nofollow, noarchive');
}
    $app = JFactory::getApplication();
    $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
    $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
    $app->setHeader('Pragma', 'no-cache', true);
    $app->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive', true);

    $model = $this->getModel('Api');
    $out = $model->search();
    echo json_encode($out, defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0);
    $app->close();
  }
}
