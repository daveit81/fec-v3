<?php
/**
 * com_content for v3: our port of the core component of Joomla 2.5.28
 * (components/com_content), reduced to what the fecv2 template overrides
 * need to run as they ran: the article and category views with the model
 * steps that came before the override (the row, its state and dates, the
 * category tree, the text as show_intro decides, the content plugins, the
 * document title and metas), and the route helper the lib loads by path.
 * Written by hand from the original, the original's comments where they
 * explain a choice; every omission is listed in PORT-NOTES.md.
 */
defined('_JEXEC') or die;

require_once JPATH_COMPONENT.'/helpers/route.php';

$controller = JController::getInstance('Content');
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();
